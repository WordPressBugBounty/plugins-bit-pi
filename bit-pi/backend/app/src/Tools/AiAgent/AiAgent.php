<?php

namespace BitApps\Pi\src\Tools\AiAgent;

// Prevent direct script access
if (!\defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Deps\BitApps\WPKit\Helpers\JSON;
use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Node;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\Model\Flow;
use BitApps\Pi\Model\FlowLog;
use BitApps\Pi\src\DTO\FlowToolResponseDTO;
use BitApps\Pi\src\Flow\GlobalNodes;
use BitApps\Pi\src\Flow\GlobalNodeVariables;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Tools\AiAgent\ChatModel\ChatModelProvider;
use BitApps\Pi\src\Tools\AiAgent\Memory\MemoryManager;
use BitApps\Pi\src\Tools\AiAgent\Schema\AIToolSchema;
use BitApps\Pi\src\Tools\FlowToolsFactory;

class AiAgent
{
    private const MAX_ITERATIONS = 1;

    protected $nodeInfoProvider;

    private $flowHistoryId;

    private $memory;

    public function __construct(NodeInfoProvider $nodeInfoProvider, $flowHistoryId)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->flowHistoryId = $flowHistoryId;
    }

    public function execute(): array
    {
        $aiAgentData = $this->nodeInfoProvider->getData()['aiAgent'] ?? [];

        $flowId = $this->nodeInfoProvider->getFlowId();

        $flowMap = Flow::select('id', 'map')->where('id', $flowId)->first();

        if (empty($flowMap)) {
            return Utility::formatResponseData(422, [], ['error' => 'Flow not found']);
        }

        $flowMap = $flowMap->map;

        $nodeId = $this->nodeInfoProvider->getNodeId();

        $agentSubNode = $this->findSubNode($flowMap, $nodeId);

        $chatModelNodeId = $agentSubNode['chatModel'] ?? null;

        $memoryNodeId = $agentSubNode['memory'] ?? null;

        $userMessage = MixInputHandler::replaceMixTagValue($aiAgentData['prompt']) ?? '';

        $systemPrompt = MixInputHandler::replaceMixTagValue($aiAgentData['systemPrompt']) ?? '';

        $maxIterations = MixInputHandler::replaceMixTagValue($aiAgentData['maxIterations']) ?? self::MAX_ITERATIONS;

        $responseFormat = $aiAgentData['responseFormat'] ?? 'text';

        $responseFormat = AIToolSchema::buildResponseStructureFormat($aiAgentData['responseJsonSchema'] ?? '', $responseFormat);

        if (empty($chatModelNodeId)) {
            return Utility::formatResponseData(422, [], ['error' => 'Chat model node not configured']);
        }

        if (empty($userMessage)) {
            return Utility::formatResponseData(422, [], ['error' => 'User message is required']);
        }

        $messages = $this->getPromptMessage($memoryNodeId, $userMessage, $systemPrompt);

        $result = (new ChatModelProvider($chatModelNodeId))
            ->completion($agentSubNode, $messages, $maxIterations, $responseFormat);

        $formatResult = $this->formatResponseData($result);

        if (isset($formatResult['conversation_messages'])) {
            unset($formatResult['conversation_messages']);
        }

        if (!empty($memoryNodeId) && $this->memory && $result['status'] === 'success') {
            $this->memory->store($result['conversation_messages'] ?? $messages);
        }

        $appDetails = [
            'app_slug'     => FlowToolsFactory::APP_SLUG,
            'machine_slug' => 'aiAgent',
        ];

        $nodeVariableInstance = GlobalNodeVariables::getInstance($this->flowHistoryId, $this->nodeInfoProvider->getFlowId());

        $nodeVariableInstance->setNodeResponse($nodeId, $formatResult);

        $nodeVariableInstance->setVariables($nodeId, $formatResult);


        if (isset($result['status']) && $result['status'] === 'error') {
            return FlowToolResponseDTO::create(
                FlowLog::STATUS['ERROR'],
                ['prompt' => $userMessage, 'system_prompt' => $systemPrompt],
                $formatResult,
                $result['message'] ?? 'Ai Agent execution failed',
                $appDetails
            );
        }

        return FlowToolResponseDTO::create(
            FlowLog::STATUS['SUCCESS'],
            ['prompt' => $userMessage, 'system_prompt' => $systemPrompt],
            $formatResult,
            'Ai Agent executed successfully',
            $appDetails,
        );
    }

    /**
     * Clear conversation memory.
     *
     * Useful for resetting the conversation history.
     *
     * @return bool True on success, false on failure
     */
    public function clearMemory(): bool
    {
        return $this->memory->clear();
    }

    /**
     * Get memory statistics.
     *
     * @return array Memory statistics including message counts
     */
    public function getMemoryStats(): array
    {
        return $this->memory->getStats();
    }

    /**
     * Format the response data into a cleaner structure.
     *
     * Transforms tool_calls array into a grouped structure by app slug.
     *
     * @param array $responseData The response data to format (modified by reference)
     */
    public function formatResponseData($responseData): array
    {
        $responseData['response'] = JSON::is($responseData['response'], true) ?: $responseData['response'];

        if (empty($responseData['tool_calls'])) {
            return $responseData;
        }

        $tools = [];

        foreach ($responseData['tool_calls'] as $toolCall) {
            $toolName = $toolCall['name'] ?? '';

            if (empty($toolName)) {
                continue;
            }

            if (!isset($tools[$toolName])) {
                $tools[$toolName] = [];
            }

            $tools[$toolName] = [
                'input'  => $toolCall['arguments'] ?? [],
                'output' => $toolCall['result'] ?? [],
            ];
        }

        $responseData['tool_calls'] = $tools;

        return $responseData;
    }

    private function getPromptMessage($memoryNodeId, $userMessage, $systemPrompt)
    {
        $flowId = $this->nodeInfoProvider->getFlowId();

        $nodesInstance = GlobalNodes::getInstance($flowId);


        $useMemory = !empty($memoryNodeId);

        $memoryConfig = [];

        if ($useMemory) {
            $nodeId = $this->nodeInfoProvider->getNodeId();

            $allNodes = $nodesInstance->getAllNodeData();

            $memoryNode = Node::getNodeInfoById($memoryNodeId, $allNodes);

            $memorySlug = $memoryNode['app_slug'] ?? '';

            $fieldMap = JSON::decode(JSON::encode($memoryNode['field_mapping']), true);

            $memoryConfig = MixInputHandler::processData($fieldMap['data'] ?? []);

            if (!empty($memorySlug)) {
                $this->memory = MemoryManager::create($memorySlug, $nodeId, $memoryConfig);
            }
        }

        $messages = [];

        if ($useMemory && $this->memory && $this->memory->exists()) {
            $messages = $this->memory->retrieve();
        } else {
            if (!empty($systemPrompt)) {
                $messages[] = [
                    'role'    => 'system',
                    'content' => $systemPrompt
                ];
            }
        }

        $messages[] = [
            'role'    => 'user',
            'content' => $userMessage
        ];

        return $messages;
    }

    private function findSubNode($flowMap, $nodeId)
    {
        if (empty($flowMap)) {
            return;
        }
        if (isset($flowMap->id) && $flowMap->id === $nodeId) {
            return (array) $flowMap->subNode;
        }

        return $this->findSubNode($flowMap->next ?? [], $nodeId);
    }
}
