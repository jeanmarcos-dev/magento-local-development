<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp;

use Magento\Framework\Interception\PluginListInterface;
use ReflectionClass;
use ReflectionException;

/**
 * Reflection helper that exposes the protected plugin index of Magento's
 * compiled `\Magento\Framework\Interception\PluginList\PluginList`.
 *
 * The public `PluginListInterface::getNext($type, $method)` only returns the
 * resolved chain for an already-known subject+method. To enumerate every
 * plugin attached to a class without iterating its methods, we reach into
 * the protected `$_data` property where Magento stores the merged plugin
 * graph.
 *
 * The internal layout (Magento 2.4.4–2.4.7, observed):
 *
 *   $_data = [
 *       'subjectType_methodName_anything' => [...sorted plugin codes...],
 *       'inh' => [...inheritance graph...],
 *       ...
 *   ];
 *
 * Per-method entries are keyed by `$type . '_' . lcfirst($method)` plus an
 * additional suffix that varies by Magento version. We scan keys conservatively
 * with a prefix match.
 *
 * **Fragility**: this is internal Magento API. The integration test suite
 * pins behavior against Magento 2.4.4 / 2.4.6 / 2.4.7. If a future Magento
 * version changes the shape, callers fall back to `getNext()` for a single
 * subject+method query.
 */
class PluginListReflector
{
    public function __construct(
        private readonly PluginListInterface $pluginList
    ) {
    }

    /**
     * Return the merged plugin index, or `null` if reflection fails.
     *
     * @return array<string,mixed>|null
     */
    public function rawIndex(): ?array
    {
        try {
            $reflection = new ReflectionClass($this->pluginList);
            while ($reflection !== false && !$reflection->hasProperty('_data')) {
                $reflection = $reflection->getParentClass();
            }
            if ($reflection === false) {
                return null;
            }
            $property = $reflection->getProperty('_data');
            $property->setAccessible(true);
            $data = $property->getValue($this->pluginList);

            return is_array($data) ? $data : null;
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * Return all plugin entries keyed under the given subject type prefix.
     *
     * Each returned value is whatever Magento stored — typically a sorted
     * list of plugin codes for one method. Callers further resolve each
     * code to a class via {@see PluginListInterface::getPlugin()} or by
     * inspecting the secondary index Magento keeps under the `'inh'` key.
     *
     * @return array<string,mixed>
     */
    public function indexForSubject(string $subjectType): array
    {
        $data = $this->rawIndex();
        if ($data === null) {
            return [];
        }

        $needle = ltrim($subjectType, '\\');
        $matches = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                continue;
            }
            if (str_starts_with($key, $needle . '_')) {
                $matches[$key] = $value;
            }
        }

        return $matches;
    }
}
