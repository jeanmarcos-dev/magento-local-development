<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\PluginListReflector;
use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\Interception\PluginListInterface;

/**
 * MCP tool: list every plugin attached to a class (and optionally to a
 * specific method).
 *
 * Two paths:
 *  - When `method` is given: use the public `PluginListInterface::getNext()`
 *    (stable API) to walk the chain in execution order.
 *  - When `method` is omitted: use {@see PluginListReflector} to read the
 *    protected `_data` index (fragile across versions; documented in the
 *    README).
 */
class FindPluginsTool implements ToolInterface
{
    private const PLUGIN_TYPE_BY_CODE = [
        // Magento\Framework\Interception\Definition\Runtime constants — copied
        // here to avoid coupling to a non-public Magento class.
        1 => 'before',
        2 => 'around',
        4 => 'after',
    ];

    public function __construct(
        private readonly PluginListInterface $pluginList,
        private readonly PluginListReflector $reflector
    ) {
    }

    public function name(): string
    {
        return 'find_plugins';
    }

    public function description(): string
    {
        return 'List plugins attached to a Magento class. Pass class as a fully-qualified name (e.g. "Magento\\\\Catalog\\\\Model\\\\Product"). When method is given, returns the resolved chain in execution order using the public PluginList API. When method is omitted, returns every plugin known for the class via reflection on PluginList internals (Magento 2.4.4–2.4.7 tested).';
    }

    /**
     * @return array{
     *     class: string,
     *     method: ?string,
     *     plugins: array<int, array<string, mixed>>,
     *     mode: string
     * }
     */
    public function execute(string $class, ?string $method = null): array
    {
        $type = ltrim($class, '\\');

        if ($method !== null && $method !== '') {
            return [
                'class' => $type,
                'method' => $method,
                'plugins' => $this->resolveChain($type, $method),
                'mode' => 'public-api',
            ];
        }

        return [
            'class' => $type,
            'method' => null,
            'plugins' => $this->scanIndex($type),
            'mode' => 'reflection',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolveChain(string $type, string $method): array
    {
        $code = null;
        $entries = [];

        // PluginListInterface::getNext walks the chain by repeatedly returning
        // the "next" plugin code. We iterate until no more entries are returned.
        while (($next = $this->pluginList->getNext($type, $method, $code)) !== null) {
            // The shape of $next is `[$callType, $code, $nextCode]`.
            // Different Magento builds may return either an indexed array or
            // associative; we read defensively.
            $callType = $this->readField($next, 0, 'type', null);
            $pluginCode = $this->readField($next, 1, 'code', null);
            $nextCode = $this->readField($next, 2, 'next', null);

            if (!is_string($pluginCode) || $pluginCode === '') {
                break;
            }

            $entries[] = [
                'code' => $pluginCode,
                'plugin_class' => $this->resolvePluginInstance($type, $pluginCode),
                'method' => $method,
                'call_type' => is_int($callType) ? (self::PLUGIN_TYPE_BY_CODE[$callType] ?? (string) $callType) : (string) $callType,
            ];

            if ($nextCode === null || $nextCode === $code) {
                break;
            }
            $code = is_string($nextCode) ? $nextCode : null;

            // Safety net: avoid pathological loops in malformed registrations.
            if (count($entries) > 256) {
                break;
            }
        }

        return $entries;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanIndex(string $type): array
    {
        $entries = [];
        foreach ($this->reflector->indexForSubject($type) as $key => $value) {
            $entries[] = [
                'index_key' => $key,
                'raw' => $value,
            ];
        }

        return $entries;
    }

    /**
     * @param array<mixed>|object $next
     */
    private function readField(mixed $next, int $offset, string $assocKey, mixed $default): mixed
    {
        if (is_array($next)) {
            if (array_key_exists($offset, $next)) {
                return $next[$offset];
            }
            if (array_key_exists($assocKey, $next)) {
                return $next[$assocKey];
            }
        }

        return $default;
    }

    private function resolvePluginInstance(string $type, string $code): ?string
    {
        $plugin = $this->pluginList->getPlugin($type, $code);
        if (is_object($plugin)) {
            return get_class($plugin);
        }
        if (is_string($plugin) && $plugin !== '') {
            return ltrim($plugin, '\\');
        }

        return null;
    }
}
