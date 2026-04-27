<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp;

use Development\Core\Model\ProductionGuard;
use Mcp\Server\McpServer;
use RuntimeException;

/**
 * Builds the underlying `Mcp\Server\McpServer` once with all tools registered.
 *
 * Both transports — {@see \Development\McpDevTools\Console\Command\ServeCommand}
 * for stdio and {@see \Development\McpDevTools\Controller\Mcp\Index} for HTTP —
 * call {@see create()} to obtain a configured server, then bind their own
 * transport on top.
 *
 * The production guard is checked here, not inside each tool, so that an
 * unauthorized invocation fails before the SDK does any protocol work.
 */
class ServerFactory
{
    public const SERVER_NAME = 'jeanmarcos-mcp-dev-tools';

    /**
     * @param ToolInterface[] $tools  keyed by tool name (di.xml array)
     */
    public function __construct(
        private readonly ProductionGuard $guard,
        private readonly array $tools
    ) {
    }

    /**
     * Build a fresh `McpServer` with every tool registered.
     *
     * @throws RuntimeException when the production guard is off — callers
     *                         should translate this to the transport-appropriate
     *                         error (JSON-RPC error for HTTP, exit code for stdio).
     */
    public function create(): McpServer
    {
        if (!$this->guard->isEnabled()) {
            throw new RuntimeException(
                'MCP Dev Tools is disabled in production mode. ' .
                'Enable it via Stores → Configuration → ⚠ Development Modules → MCP Dev Tools.'
            );
        }

        $server = new McpServer(self::SERVER_NAME);

        foreach ($this->tools as $tool) {
            if (!$tool instanceof ToolInterface) {
                continue;
            }
            $server->tool(
                $tool->name(),
                $tool->description(),
                [$tool, 'execute']
            );
        }

        return $server;
    }
}
