<?php

namespace BitApps\Pi\src\Integrations\McpClientTool;

// Prevent direct script access
if (!defined('ABSPATH')) {
    exit;
}

use BitApps\Pi\Helpers\MixInputHandler;
use BitApps\Pi\Helpers\Utility;
use BitApps\Pi\src\Flow\NodeInfoProvider;
use BitApps\Pi\src\Interfaces\ActionInterface;
use BitApps\Pi\src\Mcp\McpClient;

/**
 * MCP Client Tool Action.
 *
 * Executes MCP tool calls within a Bit Flow workflow.
 * Supports both Streamable HTTP and legacy HTTP+SSE transports.
 */
class McpClientToolAction implements ActionInterface
{
    /**
     * Node information provider.
     *
     * @var NodeInfoProvider
     */
    private $nodeInfoProvider;

    private $arguments;

    private $toolName;

    /**
     * Constructor.
     *
     * @param string $toolName
     * @param array $arguments
     */
    public function __construct(NodeInfoProvider $nodeInfoProvider, $toolName, $arguments)
    {
        $this->nodeInfoProvider = $nodeInfoProvider;
        $this->toolName = $toolName;
        $this->arguments = $arguments;
    }

    /**
     * Execute the MCP tool call.
     *
     * @return array{
     *     status: 'error'|'success',
     *     input: array,
     *     output: array,
     *     message?: string
     * }
     */
    public function execute(): array
    {
        return $this->executeMcpToolAction();
    }

    /**
     * Execute the MCP tool action based on machine slug.
     *
     * @return array{
     *     status_code: int,
     *     input: array,
     *     output: mixed,
     *     message?: string
     * }
     */
    private function executeMcpToolAction(): array
    {
        $configs = $this->nodeInfoProvider->getFieldMapConfigs();
        $connectionId = $configs['connection-id']['value'] ?? null;
        $transport = $configs['server-transport']['value'] ?? null;
        $serverUrl = MixInputHandler::replaceMixTagValue($configs['server-url']['value'] ?? []);

        if (empty($serverUrl)) {
            return Utility::formatResponseData(
                400,
                ['server_url' => $serverUrl],
                null,
                __('Server URL is required.', 'bit-pi')
            );
        }

        $toolArguments = $this->arguments;

        $strPos = strrpos($this->toolName, '_');

        $toolName = substr($this->toolName, 0, $strPos);

        $mcpClient = new McpClient($serverUrl, $transport, $connectionId);
        $result = $mcpClient->callTool($toolName, $toolArguments);
        $mcpClient->terminateSession();

        if (!$result['success']) {
            return Utility::formatResponseData(
                500,
                [
                    'server_url'     => $serverUrl,
                    'tool_name'      => $toolName,
                    'tool_arguments' => $toolArguments,
                ],
                null,
                $result['error'] ?? 'Unknown error'
            );
        }

        return Utility::formatResponseData(
            200,
            [
                'server_url'     => $serverUrl,
                'tool_name'      => $toolName,
                'tool_arguments' => $toolArguments,
            ],
            $result['result'] ?? null
        );
    }
}
