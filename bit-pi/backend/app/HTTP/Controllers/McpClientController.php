<?php

namespace BitApps\Pi\HTTP\Controllers;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Http\Response;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\HTTP\Requests\McpToolsRequest;
use BitApps\Pi\src\Mcp\McpClient;

final class McpClientController
{
    /**
     * Get tools from MCP server.
     * Delegates to McpClient with serverUrl and serverTransport (http or sse).
     *
     * @return Response
     */
    public function getTools(McpToolsRequest $request)
    {
        $validated = $request->validated();
        $serverUrl = MixInputHandler::replaceMixTagValue($validated['serverUrl']);
        $connectionId = $validated['connectionId'] ?? null;
        $serverTransport = $validated['serverTransport'];

        $error = $this->isInvalidURL($serverUrl);
        if ($error) {
            return Response::error($error);
        }

        $mcpClient = new McpClient($serverUrl, $serverTransport, $connectionId);
        $result = $mcpClient->getTools();

        // Terminate session after getting tools (good practice per MCP spec)
        $mcpClient->terminateSession();

        if ($result['success']) {
            return Response::success(['tools' => $result['tools']]);
        }

        return Response::error($result['error']);
    }

    /**
     * Check if the URL is invalid.
     *
     * @param string $url
     *
     * @return bool|string False if valid, error message if invalid
     */
    private function isInvalidURL($url)
    {
        $parsedUrl = wp_parse_url($url);

        if ($parsedUrl === false || !\in_array($parsedUrl['scheme'] ?? '', ['http', 'https'], true)) {
            return __('Only HTTP and HTTPS URLs are allowed.', 'bit-pi');
        }

        return false;
    }
}
