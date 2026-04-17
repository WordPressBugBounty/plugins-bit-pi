<?php

namespace BitApps\Pi\src\Mcp;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Config;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\src\Integrations\CommonActions\ApiRequestHelper;
use ReflectionClass;
use Throwable;

/**
 * MCP client for Streamable HTTP transport with backward compatibility for legacy HTTP+SSE (2024-11-05).
 *
 * Implements:
 *   - Streamable HTTP (primary, 2025-06-18): single endpoint, POST for all JSON-RPC, supports both
 *     application/json and text/event-stream responses, session management, protocol version header.
 *   - Backward compatibility (HTTP+SSE 2024-11-05): on POST initialize 4xx, falls back to GET for
 *     SSE stream, reads 'endpoint' event to get POST URI, uses that for subsequent requests.
 *
 * @see https://modelcontextprotocol.io/specification/2025-06-18/basic/transports
 */
final class McpClient
{
    /**
     * Transport type: Streamable HTTP (recommended).
     */
    public const TRANSPORT_HTTP = 'http';

    /**
     * Transport type: SSE preference (for older servers).
     */
    public const TRANSPORT_SSE = 'sse';

    /**
     * Protocol version per MCP spec.
     */
    private const PROTOCOL_VERSION = '2025-06-18';

    /**
     * HTTP 404 indicates session expired/not found.
     */
    private const HTTP_NOT_FOUND = 404;

    /**
     * MCP server URL.
     *
     * @var string
     */
    private $serverUrl;

    /**
     * Transport preference: 'http' or 'sse'.
     *
     * @var string
     */
    private $serverTransport;

    /**
     * Optional connection ID for auth.
     *
     * @var null|int
     */
    private $connectionId;

    /**
     * Current session ID from server.
     *
     * @var null|string
     */
    private $sessionId;

    /**
     * Legacy POST endpoint from 'endpoint' event (HTTP+SSE 2024-11-05).
     *
     * @var null|string
     */
    private $postEndpoint;

    /**
     * Negotiated protocol version from server initialize result.
     *
     * @var null|string
     */
    private $negotiatedProtocolVersion;

    /**
     * HTTP client instance (reused for session).
     *
     * @var null|HttpClient
     */
    private $http;

    /**
     * JSON-RPC message ID counter (increments per request).
     *
     * @var int
     */
    private $messageId;

    /**
     * Server capabilities from initialize response.
     *
     * @var null|object
     */
    private $serverCapabilities;

    /**
     * Server info from initialize response.
     *
     * @var null|object
     */
    private $serverInfo;

    /**
     * Raw WordPress HTTP response (stored to avoid reflection).
     *
     * @var null|array
     */
    private $lastRawResponse;

    /**
     * Auth query parameters for API Key auth with query_params location.
     *
     * @var array<string, string>
     */
    private $authQueryParams;

    // ===========================================
    // Initialization
    // ===========================================

    /**
     * Constructor.
     *
     * @param string   $serverUrl       MCP server URL (single endpoint)
     * @param string   $serverTransport 'http' (default) or 'sse' (Accept header preference only)
     * @param null|int $connectionId    Optional connection ID for auth
     */
    public function __construct(string $serverUrl, string $serverTransport, $connectionId = null)
    {
        $this->serverUrl = $serverUrl;
        $this->serverTransport = strtolower(trim($serverTransport));
        $this->connectionId = $connectionId;
        $this->sessionId = null;
        $this->postEndpoint = null;
        $this->negotiatedProtocolVersion = null;
        $this->http = null;
        $this->messageId = 0;
        $this->serverCapabilities = null;
        $this->serverInfo = null;
        $this->lastRawResponse = null;
        $this->authQueryParams = [];
    }

    // ===========================================
    // Public API Methods
    // ===========================================

    /**
     * Get tools from MCP server.
     *
     * Performs MCP lifecycle:
     *   1. POST initialize (handshake)
     *   2. POST notifications/initialized
     *   3. POST tools/list
     *
     * If server returns 404 (session expired), automatically restarts session.
     *
     * @return array{success: false, error: string}|array{success: true, tools: array}
     */
    public function getTools(): array
    {
        return $this->executeWithSessionRetry(
            function ($headers) {
                // Request tools/list
                $toolsResult = $this->sendRequest($this->buildToolsListPayload(), $headers);
                if (!$toolsResult['success']) {
                    return $toolsResult;
                }

                $toolsResponse = $toolsResult['data'];
                $tools = isset($toolsResponse->result->tools) ? $toolsResponse->result->tools : [];

                return ['success' => true, 'tools' => $tools];
            }
        );
    }

    /**
     * Call a tool on the MCP server.
     *
     * @param string               $toolName  Name of the tool to call
     * @param array<string, mixed> $arguments Tool arguments (key-value pairs)
     *
     * @return array{success: false, error: string}|array{success: true, result: mixed}
     */
    public function callTool(string $toolName, array $arguments = []): array
    {
        return $this->executeWithSessionRetry(
            function ($headers) use ($toolName, $arguments) {
                $payload = $this->buildToolCallPayload($toolName, $arguments);
                $result = $this->sendRequest($payload, $headers);

                if (!$result['success']) {
                    return $result;
                }

                $response = $result['data'];
                $content = isset($response->result->content) ? $response->result->content : null;
                $isError = isset($response->result->isError) ? $response->result->isError : false;

                if ($isError) {
                    $errorText = $this->extractTextFromContent($content);

                    return ['success' => false, 'error' => $errorText ?: __('Tool execution failed.', 'bit-pi')];
                }

                return ['success' => true, 'result' => $content];
            }
        );
    }

    /**
     * Terminate the current session.
     *
     * Per spec: Clients SHOULD send DELETE to MCP endpoint with Mcp-Session-Id
     * to explicitly terminate the session when no longer needed.
     *
     * @return array{success: false, error: string}|array{success: true}
     */
    public function terminateSession(): array
    {
        if ($this->sessionId === null) {
            return ['success' => true]; // No session to terminate
        }

        $headers = $this->buildHeaders();
        $headers['Mcp-Session-Id'] = $this->sessionId;

        $response = $this->makeRequest(
            $this->getPostBaseUrl(),
            'DELETE',
            '',
            $headers
        );

        // Clear session regardless of response
        $this->sessionId = null;

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => __('Failed to terminate MCP session.', 'bit-pi')];
        }

        // 405 Method Not Allowed is acceptable - server doesn't support client-initiated termination
        // 404 is acceptable - session was already terminated or expired
        // Any other response is also considered success (session is cleared anyway)
        return ['success' => true];
    }

    /**
     * Get the current session ID (if any).
     */
    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    /**
     * Get server capabilities (available after successful initialization).
     *
     * @return null|object Server capabilities object or null if not initialized
     */
    public function getServerCapabilities(): ?object
    {
        return $this->serverCapabilities;
    }

    /**
     * Get server info (available after successful initialization).
     *
     * @return null|object Server info object (name, version) or null if not initialized
     */
    public function getServerInfo(): ?object
    {
        return $this->serverInfo;
    }

    /**
     * Get the negotiated protocol version (available after successful initialization).
     */
    public function getNegotiatedProtocolVersion(): ?string
    {
        return $this->negotiatedProtocolVersion;
    }

    /**
     * Check if client is using legacy HTTP+SSE transport.
     */
    public function isLegacyTransport(): bool
    {
        return $this->postEndpoint !== null;
    }

    // ===========================================
    // Session Management
    // ===========================================

    /**
     * Get the effective POST base URL (postEndpoint for legacy, or serverUrl for Streamable HTTP).
     */
    private function getPostBaseUrl(): string
    {
        $url = $this->postEndpoint ?? $this->serverUrl;

        if (empty($this->authQueryParams)) {
            return $url;
        }

        $separator = strpos($url, '?') === false ? '?' : '&';

        return $url . $separator . http_build_query($this->authQueryParams);
    }

    /**
     * Validate session ID format per spec: MUST only contain visible ASCII (0x21 to 0x7E).
     */
    private function isValidSessionId(string $sessionId): bool
    {
        return $sessionId !== '' && preg_match('/^[\x21-\x7E]+$/', $sessionId) === 1;
    }

    // ===========================================
    // Legacy Transport Support (HTTP+SSE 2024-11-05)
    // ===========================================

    /**
     * Discover legacy POST endpoint for HTTP+SSE (2024-11-05) servers.
     *
     * Per backwards compatibility spec: Issue GET to server URL, expect SSE stream
     * with 'endpoint' event as first event. Extract POST URI from data.
     *
     * @return array{success: false, error: string}|array{success: true, endpoint: string}
     */
    private function discoverLegacyEndpoint(): array
    {
        $headers = [
            'Accept' => 'text/event-stream',
        ];

        $response = $this->makeRequest(
            $this->serverUrl,
            'GET',
            '',
            $headers
        );

        if (is_wp_error($response)) {
            return ['success' => false, 'error' => __('Failed to connect to legacy MCP server.', 'bit-pi')];
        }

        // Check for non-SSE response (e.g., 405, plain HTML)
        $statusCode = $this->getResponseStatusCode();
        if ($statusCode !== null && $statusCode >= 400) {
            return ['success' => false, 'error' => __('Not a valid MCP server (Streamable HTTP or legacy HTTP+SSE).', 'bit-pi')];
        }

        // Parse SSE body for 'event: endpoint' and 'data: <uri>'
        $body = \is_string($response) ? $response : '';
        $endpoint = $this->extractEndpointFromSse($body);

        if ($endpoint === null) {
            return ['success' => false, 'error' => __('Legacy server did not send endpoint event.', 'bit-pi')];
        }

        return ['success' => true, 'endpoint' => $endpoint];
    }

    // ===========================================
    // SSE Parsing
    // ===========================================

    /**
     * Parse SSE stream and extract data for a specific event type.
     *
     * @param string      $body      Raw SSE body
     * @param null|string $eventType Event type to extract (null = extract all data lines)
     *
     * @return null|string Data content or null if not found
     */
    private function parseSseEvent(string $body, ?string $eventType = null): ?string
    {
        if ($body === '') {
            return null;
        }

        $lines = preg_split('/\r\n|\r|\n/', $body);
        $currentEvent = null;
        $dataParts = [];

        foreach ($lines as $line) {
            $line = trim($line);

            // Track current event type
            if (strpos($line, 'event:') === 0) {
                $currentEvent = trim(substr($line, 6));

                continue;
            }

            // Extract data lines
            if (strpos($line, 'data:') === 0) {
                $data = trim(substr($line, 5));

                // If looking for specific event, only collect matching data
                if ($eventType === null || $currentEvent === $eventType) {
                    $dataParts[] = $data;
                }
            }
        }

        if (empty($dataParts)) {
            return null;
        }

        $result = implode("\n", $dataParts);

        return $result !== '' ? $result : null;
    }

    /**
     * Extract endpoint URI from SSE 'endpoint' event.
     *
     * Legacy SSE format: "event: endpoint\ndata: https://example.com/mcp/endpoint\n"
     *
     * @param string $body Raw SSE body
     *
     * @return null|string Endpoint URI or null if not found
     */
    private function extractEndpointFromSse(string $body): ?string
    {
        $data = $this->parseSseEvent($body, 'endpoint');
        if ($data === null) {
            return null;
        }

        // Data might be JSON-encoded string or plain URL
        $decoded = json_decode($data);

        return \is_string($decoded) ? $decoded : $data;
    }

    /**
     * Execute an operation with automatic session initialization and 404 retry.
     *
     * Per spec: If client receives 404 with Mcp-Session-Id, it MUST start new session.
     *
     * @param callable $operation Function that receives $headers and returns result array
     *
     * @return array{success: false, error: string}|array{success: true, ...}
     */
    private function executeWithSessionRetry(callable $operation): array
    {
        // Initialize session if not already done
        $initResult = $this->ensureSession();
        if (!$initResult['success']) {
            return $initResult;
        }

        $headers = $this->buildHeaders();
        if ($this->sessionId !== null) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        $result = $operation($headers);

        // Check for 404 (session expired) and retry with new session
        if (!$result['success'] && $this->isSessionExpiredError($result)) {
            // Clear session and reinitialize (keep postEndpoint for legacy servers)
            $this->sessionId = null;

            $reinitResult = $this->ensureSession();
            if (!$reinitResult['success']) {
                return $reinitResult;
            }

            $headers = $this->buildHeaders();
            if ($this->sessionId !== null) {
                $headers['Mcp-Session-Id'] = $this->sessionId;
            }

            // Retry operation with new session
            $result = $operation($headers);
        }

        return $result;
    }

    /**
     * Ensure session is initialized.
     *
     * Implements Streamable HTTP with backward compatibility:
     *   1. Try POST initialize to serverUrl (Streamable HTTP)
     *   2. On 4xx: GET serverUrl for 'endpoint' event (legacy HTTP+SSE 2024-11-05)
     *   3. If endpoint found, retry POST initialize to that endpoint
     *
     * @return array{success: false, error: string}|array{success: true}
     */
    private function ensureSession(): array
    {
        // Already have a session
        if ($this->sessionId !== null) {
            return ['success' => true];
        }

        $headers = $this->buildHeaders();

        // 1. Try POST initialize (Streamable HTTP)
        $initResult = $this->sendRequest($this->buildInitPayload(), $headers);

        // Backward compatibility: on 4xx, try legacy HTTP+SSE discovery
        if (!$initResult['success']) {
            $statusCode = $this->getResponseStatusCode();

            // 4xx suggests not Streamable HTTP; try legacy HTTP+SSE
            if ($statusCode !== null && $statusCode >= 400 && $statusCode < 500) {
                $legacyResult = $this->discoverLegacyEndpoint();
                if ($legacyResult['success']) {
                    $this->postEndpoint = $legacyResult['endpoint'];

                    // Retry POST initialize to the discovered endpoint
                    $initResult = $this->sendRequest($this->buildInitPayload(), $headers);
                    if (!$initResult['success']) {
                        return $initResult;
                    }
                } else {
                    // Legacy discovery failed; return original init error
                    return $initResult;
                }
            } else {
                // Non-4xx error; return as is
                return $initResult;
            }
        }

        $initResponse = $initResult['data'];

        // Store server info from initialize result
        if (isset($initResponse->result)) {
            $result = $initResponse->result;

            // Protocol version (for use in subsequent headers)
            if (isset($result->protocolVersion)) {
                $this->negotiatedProtocolVersion = $result->protocolVersion;
            }

            // Server capabilities (for feature detection)
            if (isset($result->capabilities)) {
                $this->serverCapabilities = $result->capabilities;
            }

            // Server info (name, version)
            if (isset($result->serverInfo)) {
                $this->serverInfo = $result->serverInfo;
            }
        }

        // Get session ID from response headers (per spec: Mcp-Session-Id on InitializeResult)
        $sessionId = $this->resolveSessionId($initResponse);
        if ($sessionId !== null) {
            // Validate session ID format (MUST be visible ASCII 0x21-0x7E)
            if ($this->isValidSessionId($sessionId)) {
                $this->sessionId = $sessionId;
            }
        }

        // Update headers with session ID for next request
        if ($this->sessionId !== null) {
            $headers['Mcp-Session-Id'] = $this->sessionId;
        }

        // 2. Send notifications/initialized (per spec: server MUST return 202 Accepted with no body)
        $this->makeRequest(
            $this->getPostBaseUrl(),
            'POST',
            wp_json_encode($this->buildInitializedPayload()),
            $headers
        );

        // Check for 202 Accepted or error
        $statusCode = $this->getResponseStatusCode();
        if ($statusCode !== null && $statusCode >= 400) {
            return ['success' => false, 'error' => __('Server rejected initialized notification.', 'bit-pi')];
        }

        // 202 or 2xx is expected; continue
        return ['success' => true];
    }

    /**
     * Check if error indicates session expired (HTTP 404).
     *
     * @param array $result Result array with 'success' and 'error' keys
     */
    private function isSessionExpiredError(array $result): bool
    {
        if ($result['success']) {
            return false;
        }

        return $this->getResponseStatusCode() === self::HTTP_NOT_FOUND;
    }

    // ===========================================
    // HTTP Request Handling
    // ===========================================

    /**
     * Get HTTP status code from last response.
     */
    private function getResponseStatusCode(): ?int
    {
        if ($this->lastRawResponse === null) {
            return null;
        }

        $code = wp_remote_retrieve_response_code($this->lastRawResponse);

        return is_numeric($code) ? (int) $code : null;
    }

    /**
     * Get or create HTTP client instance.
     */
    private function getHttpClient(): HttpClient
    {
        if ($this->http === null) {
            $this->http = new HttpClient();
        }

        return $this->http;
    }

    /**
     * Make HTTP request and store raw response for header access.
     *
     * Wraps HttpClient::request() and captures the raw WordPress response
     * so we can access headers without repeated reflection calls.
     *
     * @param string $url     Request URL
     * @param string $method  HTTP method
     * @param string $body    Request body
     * @param array  $headers Request headers
     *
     * @return mixed Response body (parsed) or WP_Error
     */
    private function makeRequest(string $url, string $method, string $body, array $headers)
    {
        $http = $this->getHttpClient();
        $response = $http->request($url, $method, $body, $headers);

        // Capture raw response immediately after request (avoids repeated reflection)
        $this->lastRawResponse = $this->extractRawResponseFromClient($http);

        return $response;
    }

    /**
     * Extract raw WordPress response from HttpClient via reflection.
     *
     * Note: This is necessary because HttpClient doesn't expose the raw response
     * with headers. We only call this once per request and cache the result.
     *
     * @return null|array Raw WordPress HTTP response array
     */
    private function extractRawResponseFromClient(HttpClient $http): ?array
    {
        try {
            $ref = new ReflectionClass($http);
            $prop = $ref->getProperty('_requestResponse');
            $prop->setAccessible(true);

            $value = $prop->getValue($http);

            return \is_array($value) ? $value : null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Send a JSON-RPC request and parse the response.
     *
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     *
     * @return array{success: false, error: string}|array{success: true, data: object}
     */
    private function sendRequest(array $payload, array $headers): array
    {
        $response = $this->makeRequest(
            $this->getPostBaseUrl(),
            'POST',
            wp_json_encode($payload),
            $headers
        );

        if (is_wp_error($response)) {
            $errorMessage = $response->get_error_message() ?? __('Failed to connect to MCP server.', 'bit-pi');

            return ['success' => false, 'error' => $errorMessage];
        }

        // Check for HTTP error status codes
        $statusCode = $this->getResponseStatusCode();
        if ($statusCode !== null && $statusCode >= 400) {
            return $this->buildErrorResponse($response, $statusCode);
        }

        // Parse and validate response
        $decoded = $this->normalizeResponse($response);
        if ($decoded === null) {
            return ['success' => false, 'error' => __('Invalid response from MCP server.', 'bit-pi')];
        }

        // Check for JSON-RPC error
        if (isset($decoded->error)) {
            $message = $decoded->error->message ?? __('MCP server returned an error.', 'bit-pi');

            return ['success' => false, 'error' => $message];
        }

        return ['success' => true, 'data' => $decoded];
    }

    /**
     * Build error response from HTTP error status.
     *
     * @param mixed $response   Raw response
     * @param int   $statusCode HTTP status code
     *
     * @return array{success: false, error: string}
     */
    private function buildErrorResponse($response, int $statusCode): array
    {
        // Try to extract error message from response body
        $decoded = $this->normalizeResponse($response);
        if ($decoded !== null && isset($decoded->error->message)) {
            return ['success' => false, 'error' => $decoded->error->message];
        }

        // Specific message for 404
        if ($statusCode === self::HTTP_NOT_FOUND) {
            return ['success' => false, 'error' => __('MCP session expired or not found.', 'bit-pi')];
        }

        // Generic error with status code
        // translators: %d: HTTP status code.
        return ['success' => false, 'error' => \sprintf(__('MCP server error (HTTP %d).', 'bit-pi'), $statusCode)];
    }

    // ===========================================
    // Headers & Authentication
    // ===========================================

    /**
     * Build HTTP headers for MCP requests.
     *
     * Per spec (Protocol Version Header):
     * - Content-Type: application/json
     * - Accept: application/json, text/event-stream (client MUST accept both)
     * - MCP-Protocol-Version: <version> (SHOULD be negotiated version)
     *
     * @return array<string, string>
     */
    private function buildHeaders(): array
    {
        // Accept header: prioritize based on transport preference
        $accept = $this->serverTransport === self::TRANSPORT_SSE
            ? 'text/event-stream, application/json'
            : 'application/json, text/event-stream';

        $headers = [
            'Content-Type'         => 'application/json',
            'Accept'               => $accept,
            'MCP-Protocol-Version' => $this->negotiatedProtocolVersion ?? self::PROTOCOL_VERSION,
        ];

        // Reset auth query params (will be populated if needed)
        $this->authQueryParams = [];

        // Add authentication if connection ID is provided
        if ($this->connectionId !== null) {
            $this->addAuthenticationToHeaders($headers);
        }

        return $headers;
    }

    /**
     * Add authentication credentials to headers or query params.
     *
     * @param array<string, string> $headers Headers array (passed by reference)
     */
    private function addAuthenticationToHeaders(array &$headers): void
    {
        $accessToken = ApiRequestHelper::getAccessToken($this->connectionId);

        if (\is_string($accessToken)) {
            $headers['Authorization'] = $accessToken;

            return;
        }

        if (!\is_array($accessToken)) {
            return;
        }

        $authLocation = $accessToken['authLocation'] ?? null;
        $authData = $accessToken['data'] ?? [];

        if ($authLocation === 'header') {
            $headers = array_merge($headers, $authData);
        } elseif ($authLocation === 'query_params') {
            $this->authQueryParams = $authData;
        }
    }

    // ===========================================
    // JSON-RPC Payload Builders
    // ===========================================

    /**
     * Generate next JSON-RPC message ID.
     *
     * Per JSON-RPC 2.0: IDs should be unique within a session.
     */
    private function nextMessageId(): int
    {
        return ++$this->messageId;
    }

    /**
     * Get client info for initialize request.
     *
     * @return array{name: string, version: string}
     */
    private function getClientInfo(): array
    {
        return [
            'name'    => 'Bit Flows',
            'version' => Config::VERSION,
        ];
    }

    /**
     * Build initialize JSON-RPC payload.
     *
     * @return array<string, mixed>
     */
    private function buildInitPayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $this->nextMessageId(),
            'method'  => 'initialize',
            'params'  => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities'    => [
                    'roots'    => ['listChanged' => true],
                    'sampling' => (object) [],
                ],
                'clientInfo' => $this->getClientInfo(),
            ],
        ];
    }

    /**
     * Build notifications/initialized JSON-RPC payload.
     *
     * Note: Notifications have no 'id' field per JSON-RPC 2.0.
     *
     * @return array<string, mixed>
     */
    private function buildInitializedPayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'method'  => 'notifications/initialized',
        ];
    }

    /**
     * Build tools/list JSON-RPC payload.
     *
     * @return array<string, mixed>
     */
    private function buildToolsListPayload(): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $this->nextMessageId(),
            'method'  => 'tools/list',
        ];
    }

    /**
     * Build tools/call JSON-RPC payload.
     *
     * @param string               $toolName  Tool name
     * @param array<string, mixed> $arguments Tool arguments
     *
     * @return array<string, mixed>
     */
    private function buildToolCallPayload(string $toolName, array $arguments): array
    {
        return [
            'jsonrpc' => '2.0',
            'id'      => $this->nextMessageId(),
            'method'  => 'tools/call',
            'params'  => [
                'name'      => $toolName,
                'arguments' => (object) $arguments,
            ],
        ];
    }

    // ===========================================
    // Response Parsing & Utilities
    // ===========================================

    /**
     * Extract text from MCP content array.
     *
     * MCP tool responses return content as array of {type, text} objects.
     *
     * @param mixed $content Content array from tool response
     */
    private function extractTextFromContent($content): string
    {
        if (!\is_array($content)) {
            return '';
        }

        $texts = array_filter(
            array_map(
                function ($item) {
                    return (\is_object($item) && isset($item->type, $item->text) && $item->type === 'text')
                        ? $item->text
                        : null;
                },
                $content
            )
        );

        return implode("\n", $texts);
    }

    /**
     * Normalize response: handle both JSON and SSE formats.
     *
     * Per spec: Server may respond with application/json or text/event-stream.
     * Client MUST support both formats.
     *
     * @param mixed $response Raw response (string or object)
     */
    private function normalizeResponse($response): ?object
    {
        if (\is_object($response)) {
            return $response;
        }

        if (!\is_string($response)) {
            return null;
        }

        // Try SSE format first, fallback to plain JSON
        $json = $this->extractJsonFromSse($response) ?? $response;
        $decoded = json_decode($json);

        return \is_object($decoded) ? $decoded : null;
    }

    /**
     * Extract JSON from Server-Sent Events format.
     *
     * SSE format: "event: message\nid: ...\ndata: {\"result\":{...}}\n"
     *
     * @param string $body Raw response body
     *
     * @return null|string JSON string or null if not SSE format
     */
    private function extractJsonFromSse(string $body): ?string
    {
        // Extract all data lines (no specific event filter)
        return $this->parseSseEvent($body);
    }

    // ===========================================
    // Session ID Extraction
    // ===========================================

    /**
     * Resolve session ID from init response (headers or body).
     *
     * Per spec: server MAY return Mcp-Session-Id header on InitializeResult.
     * If returned, client MUST include it on all subsequent requests.
     *
     * @param object $initResponse Decoded init response
     */
    private function resolveSessionId($initResponse): ?string
    {
        // Try response headers first (WordPress API)
        if ($this->lastRawResponse !== null) {
            $sessionId = $this->extractHeaderValue($this->lastRawResponse, 'mcp-session-id');
            if ($sessionId !== null) {
                return $sessionId;
            }
        }

        // Try HttpClient response headers as fallback
        $headers = $this->getHttpClient()->getResponseHeaders();
        if ($headers !== null) {
            $sessionId = $this->extractHeaderFromArray($headers, 'mcp-session-id');
            if ($sessionId !== null) {
                return $sessionId;
            }
        }

        // Fallback: check response body (non-standard but some servers use it)
        return $this->extractSessionIdFromBody($initResponse);
    }

    /**
     * Extract header value case-insensitively from WordPress response.
     *
     * @param array  $response   WordPress HTTP response array
     * @param string $headerName Header name (case-insensitive)
     */
    private function extractHeaderValue(array $response, string $headerName): ?string
    {
        $value = wp_remote_retrieve_header($response, $headerName);

        return $this->normalizeHeaderValue($value);
    }

    /**
     * Extract header value case-insensitively from headers array/object.
     *
     * @param array|object $headers    Headers array or object
     * @param string       $headerName Header name (case-insensitive)
     */
    private function extractHeaderFromArray($headers, string $headerName): ?string
    {
        if (!\is_array($headers) && !\is_object($headers)) {
            return null;
        }

        $headersArray = \is_object($headers) ? (array) $headers : $headers;
        $headerNameLower = strtolower($headerName);

        foreach ($headersArray as $name => $value) {
            if (strtolower((string) $name) === $headerNameLower) {
                return $this->normalizeHeaderValue($value);
            }
        }

        return null;
    }

    /**
     * Normalize header value (handle arrays, empty strings, trim).
     *
     * @param mixed $value Header value
     */
    private function normalizeHeaderValue($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (\is_array($value)) {
            $value = reset($value);
            if ($value === false || $value === null || $value === '') {
                return null;
            }
        }

        $normalized = \is_string($value) ? trim($value) : (string) $value;

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Extract session ID from init response body (non-standard fallback).
     *
     * @param object $response Init response object
     */
    private function extractSessionIdFromBody($response): ?string
    {
        if (!\is_object($response)) {
            return null;
        }

        $candidates = ['sessionId', 'session_id', 'sessionID'];

        // Check root level
        foreach ($candidates as $key) {
            if (isset($response->{$key}) && $response->{$key} !== '') {
                $value = $response->{$key};

                return \is_string($value) ? trim($value) : (string) $value;
            }
        }

        // Check result level
        if (isset($response->result) && \is_object($response->result)) {
            foreach ($candidates as $key) {
                if (isset($response->result->{$key}) && $response->result->{$key} !== '') {
                    $value = $response->result->{$key};

                    return \is_string($value) ? trim($value) : (string) $value;
                }
            }
        }

        return null;
    }
}
