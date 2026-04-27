<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp;

/**
 * Internal contract for an MCP tool exposed by this module.
 *
 * The {@see ServerFactory} iterates an array of these and registers each
 * one against the underlying `Mcp\Server\McpServer` via `$server->tool()`.
 *
 * Schema generation: the SDK reflects the parameter list of {@see execute()}
 * to derive a JSON Schema for the tool's inputs. Use native PHP type hints
 * and document constraints via {@see description()}.
 */
interface ToolInterface
{
    /**
     * Tool identifier exposed to LLM clients (e.g. "list_modules").
     * Must be a valid JSON-RPC method fragment: snake_case, ASCII.
     */
    public function name(): string;

    /**
     * Human-readable description shown to the LLM when listing tools.
     * One sentence is best; the LLM uses this to decide when to call the tool.
     */
    public function description(): string;
}
