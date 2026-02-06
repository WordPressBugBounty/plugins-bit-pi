<?php

namespace BitApps\Pi\src\Tools\AiAgent\ChatModel;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Deps\BitApps\WPKit\Http\Client\HttpClient;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Authorization\AuthorizationFactory;
use BitApps\Pi\src\Authorization\AuthorizationType;
use BitApps\Pi\src\Flow\GlobalNodes;
use BitApps\Pi\src\Flow\NodeExecutor;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Integrations\AIIntegrationHelper;
use BitApps\Pi\src\Tools\AiAgent\Schema\AIToolSchema;

/**
 * ChatModel Service - Reuses existing AI integrations for AI Agent.
 *
 * This service wraps existing integration services and adds tools parameter support
 * for AI Agent function calling without modifying the original integrations.
 */
class ChatModelProvider
{
    public const MAX_ITERATIONS = 1;

    /**
     * Provider name (openai, groq, gemini, claude, deepseek).
     *
     * @var string
     */
    private $chatModelNodeId;

    private static $providerConfig = [
        'openAiChatModel' => [
            'api_url' => 'https://api.openai.com/v1/chat/completions',
        ],
        'groqChatModel' => [
            'api_url' => 'https://api.groq.com/openai/v1/chat/completions',
        ],
        'deepSeekChatModel' => [
            'api_url' => 'https://api.deepseek.com/chat/completions',
        ],
        'openRouterChatModel' => [
            'api_url' => 'https://openrouter.ai/api/v1/chat/completions',
        ],
    ];

    private $chatModelProvider;

    /**
     * Constructor.
     *
     * @param mixed $chatModelNodeId
     */
    public function __construct($chatModelNodeId)
    {
        $this->chatModelNodeId = $chatModelNodeId;
    }

    /**
     * Execute chat completion with tools support.
     *
     * This method wraps existing integrations and adds tools parameter support.
     *
     * @param mixed $agentSubNode
     * @param mixed $messages
     * @param mixed $maxIterations
     * @param null|mixed $responseFormatSchema
     *
     * @return array Response data
     */
    public function completion($agentSubNode, $messages, $maxIterations, $responseFormatSchema = null): array
    {
        $flowId = explode('-', $this->chatModelNodeId)[0];

        $nodesInstance = GlobalNodes::getInstance($flowId);

        $allNodes = $nodesInstance->getAllNodeData();

        $chatModelNodeData = Node::getNodeInfoById($this->chatModelNodeId, $allNodes);

        $chatModelNodeProvider = new NodeInfoProvider($chatModelNodeData);

        $this->chatModelProvider = $chatModelNodeProvider->getAppSlug();

        $payloadData = AIIntegrationHelper::castPayloadTypes($chatModelNodeProvider->getFieldMapData());

        $connectionId = $chatModelNodeProvider->getFieldMapConfigs('connection-id.value');

        $toolSchemas = AIToolSchema::generateSchemas($agentSubNode, $allNodes);

        $accessToken = AuthorizationFactory::getAuthorizationHandler(
            AuthorizationType::API_KEY,
            $connectionId
        )->getAccessToken();

        $payloadData['messages'] = $messages;

        if (!empty($toolSchemas)) {
            $payloadData['tools'] = $toolSchemas;
            $payloadData['tool_choice'] = 'auto';
        }

        $payloadData['response_format'] = $responseFormatSchema;

        return $this->executeAgentLoop($accessToken, $payloadData, $flowId, $maxIterations);
    }

    private function executeAgentLoop($accessToken, $payload, $flowId, $maxIterations)
    {
        $headers = [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Bearer ' . $accessToken,
        ];

        $iteration = 0;

        $allToolCalls = [];

        $seenCalls = [];

        $messages = $this->sanitizeMessages($payload['messages']);

        while ($iteration < $maxIterations) {
            ++$iteration;

            $payload['messages'] = $messages; // Update messages with tool call results
            $response = $this->callAiModel($headers, $payload);


            if (isset($response['error'])) {
                $message = $response['error']['message'] ?? 'Unknown error';

                return Utility::formatResponseData(400, $payload, $response, $message);
            }

            $choice = $response['choices'][0] ?? null;
            if (!$choice || empty($choice['message'])) {
                return Utility::formatResponseData(400, $payload, $response, 'Empty model response');
            }

            $message = $choice['message'];
            $messages[] = $message;


            if (!empty($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $toolCall) {
                    $toolCallId = $toolCall['id'];
                    $functionName = $toolCall['function']['name'];
                    $rawArgs = $toolCall['function']['arguments'] ?? '{}';

                    $args = json_decode($rawArgs, true);
                    if (!\is_array($args)) {
                        $args = [];
                    }

                    $signature = md5($functionName . serialize($args));
                    if (isset($seenCalls[$signature])) {
                        return Utility::formatResponseData(
                            400,
                            $payload,
                            $response,
                            'Repeated tool call detected (loop prevention)'
                        );
                    }
                    $seenCalls[$signature] = true;

                    $toolResult = $this->executeTool($functionName, $args, $flowId);

                    $allToolCalls[] = [
                        'id'        => $toolCallId,
                        'name'      => $functionName,
                        'arguments' => $args,
                        'result'    => $toolResult,
                    ];

                    $messages[] = [
                        'role'         => 'tool',
                        'tool_call_id' => $toolCallId,
                        'content'      => wp_json_encode($toolResult),
                    ];
                }

                continue;
            }

            return [
                'status'                => 'success',
                'response'              => $message['content'] ?? '',
                'iterations'            => $iteration,
                'finish_reason'         => $choice['finish_reason'] ?? 'stop',
                'tool_calls'            => $allToolCalls,
                'conversation_messages' => $messages
            ];
        }

        return [
            'status'                => 'error',
            'response'              => 'Max iterations reached',
            'tool_calls'            => $allToolCalls,
            'iterations'            => $iteration,
            'conversation_messages' => $messages
        ];
    }

    private function callAiModel($headers, $payload)
    {
        $apiUrl = self::$providerConfig[$this->chatModelProvider]['api_url'] ?? '';

        $response = (new HttpClient())->request(
            $apiUrl,
            'POST',
            wp_json_encode($payload),
            $headers
        );

        return JSON::decode(JSON::encode($response), true);
    }

    /**
     * Sanitize messages array to ensure tool messages have preceding assistant tool_calls.
     *
     * OpenAI API requires that messages with role 'tool' must be a response to a
     * preceding message with 'tool_calls'. This function removes orphaned tool messages.
     *
     * @param array $messages The messages array to sanitize
     *
     * @return array Sanitized messages array
     */
    private function sanitizeMessages(array $messages): array
    {
        $validToolCallIds = [];
        $sanitizedMessages = [];

        foreach ($messages as $message) {
            $role = $message['role'] ?? '';

            if ($role === 'assistant' && !empty($message['tool_calls'])) {
                foreach ($message['tool_calls'] as $toolCall) {
                    if (isset($toolCall['id'])) {
                        $validToolCallIds[$toolCall['id']] = true;
                    }
                }
                $sanitizedMessages[] = $message;
            } elseif ($role === 'tool') {
                $toolCallId = $message['tool_call_id'] ?? '';
                if (isset($validToolCallIds[$toolCallId])) {
                    $sanitizedMessages[] = $message;
                }
            } else {
                $sanitizedMessages[] = $message;
            }
        }

        return $sanitizedMessages;
    }

    private function executeTool($functionName, $functionArgs, $flowId)
    {
        $nodeInstance = GlobalNodes::getInstance($flowId);

        $nodes = $nodeInstance->getAllNodeData();

        $appSlug = explode('_', $functionName)[0];

        $nodeId = explode('_', $functionName)[1] ?? '';

        $node = Node::getNodeInfoById($nodeId, $nodes);

        $appClass = (new NodeExecutor())->isExistClass($appSlug);

        if (!$appClass) {
            return ['error' => 'Tool class not found for ' . $functionName];
        }

        update_option('ai_agent_tool_args_' . $nodeId, $functionArgs);

        $provider = new NodeInfoProvider($node);

        $instance = new $appClass($provider);

        $output = $instance->execute()['output'] ?? [];

        delete_option('ai_agent_tool_args_' . $nodeId);

        return $output;
    }
}
