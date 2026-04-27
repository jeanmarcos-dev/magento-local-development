<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\PluginListReflector;
use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\ObjectManager\ConfigInterface as ObjectManagerConfig;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * MCP tool: describe everything an LLM needs to know about a class from
 * Magento's DI graph in one call.
 *
 * Returns constructor argument types (with their resolved preferences),
 * preferences resolving here, virtual types pointing here, and plugins
 * applied to this class. The "high information density" companion to
 * {@see ResolveClassTool}.
 */
class DescribeDiTool implements ToolInterface
{
    public function __construct(
        private readonly ObjectManagerConfig $omConfig,
        private readonly PluginListReflector $pluginReflector
    ) {
    }

    public function name(): string
    {
        return 'describe_di';
    }

    public function description(): string
    {
        return 'Describe a Magento class from a DI perspective in a single call: constructor arg list with types, preferences resolving to it, virtual types pointing at it, plugins applied to it.';
    }

    /**
     * @return array{
     *     class: string,
     *     exists: bool,
     *     constructor_args: array<int, array{name: string, type: ?string, optional: bool}>,
     *     preferences_resolving_here: array<int, string>,
     *     virtual_types_pointing_here: array<int, string>,
     *     plugins_indexed: array<string, mixed>
     * }
     */
    public function execute(string $class): array
    {
        $clean = ltrim($class, '\\');

        return [
            'class' => $clean,
            'exists' => class_exists($clean) || interface_exists($clean),
            'constructor_args' => $this->describeConstructor($clean),
            'preferences_resolving_here' => $this->preferencesResolvingTo($clean),
            'virtual_types_pointing_here' => $this->virtualTypesPointingAt($clean),
            'plugins_indexed' => $this->pluginReflector->indexForSubject($clean),
        ];
    }

    /**
     * @return array<int, array{name: string, type: ?string, optional: bool}>
     */
    private function describeConstructor(string $class): array
    {
        try {
            $reflection = new ReflectionClass($class);
            $constructor = $reflection->getConstructor();
            if ($constructor === null) {
                return [];
            }

            return array_values(array_map(
                fn (\ReflectionParameter $p) => [
                    'name' => $p->getName(),
                    'type' => $this->describeType($p->getType()),
                    'optional' => $p->isOptional(),
                ],
                $constructor->getParameters()
            ));
        } catch (ReflectionException) {
            return [];
        }
    }

    private function describeType(?\ReflectionType $type): ?string
    {
        if ($type instanceof ReflectionNamedType) {
            $name = $type->getName();

            return $type->allowsNull() ? '?' . $name : $name;
        }
        if ($type instanceof ReflectionUnionType) {
            return implode(
                '|',
                array_map(
                    fn (\ReflectionType $t) => $t instanceof ReflectionNamedType ? $t->getName() : (string) $t,
                    $type->getTypes()
                )
            );
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function preferencesResolvingTo(string $target): array
    {
        $result = [];
        foreach ($this->omConfig->getPreferences() as $for => $impl) {
            if (ltrim((string) $impl, '\\') === $target) {
                $result[] = ltrim((string) $for, '\\');
            }
        }
        sort($result);

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function virtualTypesPointingAt(string $target): array
    {
        $result = [];
        foreach ($this->omConfig->getVirtualTypes() as $name => $definition) {
            $type = is_array($definition) ? ($definition['type'] ?? null) : (is_string($definition) ? $definition : null);
            if (is_string($type) && ltrim($type, '\\') === $target) {
                $result[] = ltrim((string) $name, '\\');
            }
        }
        sort($result);

        return $result;
    }
}

