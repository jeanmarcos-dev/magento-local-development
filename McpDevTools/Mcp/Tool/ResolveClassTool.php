<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\Component\ComponentRegistrar;
use Magento\Framework\ObjectManager\ConfigInterface as ObjectManagerConfig;
use ReflectionClass;
use ReflectionException;

/**
 * MCP tool: resolve a class name to all the information an LLM typically
 * needs to start reasoning about it — file location, generated interceptor,
 * applicable preferences, virtual types pointing at it, owning module.
 *
 * High information density per call.
 */
class ResolveClassTool implements ToolInterface
{
    public function __construct(
        private readonly ObjectManagerConfig $omConfig,
        private readonly ComponentRegistrar $componentRegistrar
    ) {
    }

    public function name(): string
    {
        return 'resolve_class';
    }

    public function description(): string
    {
        return 'Given a fully-qualified class or interface name, return its on-disk file path, generated interceptor (if interception is configured), every preference that resolves to it, every virtual type pointing at it, and the owning module.';
    }

    /**
     * @return array{
     *     name: string,
     *     exists: bool,
     *     file_path: ?string,
     *     generated_interceptor: ?string,
     *     preferences_resolving_here: array<int, string>,
     *     virtual_types_pointing_here: array<int, string>,
     *     owning_module: ?string
     * }
     */
    public function execute(string $name): array
    {
        $clean = ltrim($name, '\\');
        $exists = class_exists($clean) || interface_exists($clean) || trait_exists($clean);

        $filePath = $this->resolveFilePath($clean);
        $interceptor = $this->resolveInterceptor($clean);
        $preferencesHere = $this->preferencesResolvingTo($clean);
        $virtualTypesHere = $this->virtualTypesPointingAt($clean);
        $owningModule = $this->resolveOwningModule($clean);

        return [
            'name' => $clean,
            'exists' => $exists,
            'file_path' => $filePath,
            'generated_interceptor' => $interceptor,
            'preferences_resolving_here' => $preferencesHere,
            'virtual_types_pointing_here' => $virtualTypesHere,
            'owning_module' => $owningModule,
        ];
    }

    private function resolveFilePath(string $name): ?string
    {
        try {
            $reflection = new ReflectionClass($name);
            $file = $reflection->getFileName();

            return is_string($file) && $file !== '' ? $file : null;
        } catch (ReflectionException) {
            return null;
        }
    }

    private function resolveInterceptor(string $name): ?string
    {
        // Magento generates `Class\Interceptor` only when at least one plugin
        // is wired against the class. Existence of the generated file is the
        // simplest signal — we don't try to load it (would kick off codegen).
        $candidate = $name . '\\Interceptor';

        return class_exists($candidate, false) || $this->generatedFileExists($candidate)
            ? $candidate
            : null;
    }

    private function generatedFileExists(string $fqcn): bool
    {
        $relative = str_replace('\\', '/', ltrim($fqcn, '\\')) . '.php';
        // Magento writes generated/code under the BP. We check both the legacy
        // and modern locations conservatively.
        foreach (['generated/code', 'var/generation'] as $base) {
            $absolute = BP . '/' . $base . '/' . $relative;
            if (defined('BP') && file_exists($absolute)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    private function preferencesResolvingTo(string $target): array
    {
        $matches = [];
        foreach ($this->omConfig->getPreferences() as $for => $type) {
            if (ltrim((string) $type, '\\') === $target) {
                $matches[] = ltrim((string) $for, '\\');
            }
        }
        sort($matches);

        return $matches;
    }

    /**
     * @return array<int, string>
     */
    private function virtualTypesPointingAt(string $target): array
    {
        $matches = [];
        foreach ($this->omConfig->getVirtualTypes() as $virtualName => $definition) {
            $type = is_array($definition) ? ($definition['type'] ?? null) : (is_string($definition) ? $definition : null);
            if (is_string($type) && ltrim($type, '\\') === $target) {
                $matches[] = ltrim((string) $virtualName, '\\');
            }
        }
        sort($matches);

        return $matches;
    }

    private function resolveOwningModule(string $fqcn): ?string
    {
        foreach ($this->componentRegistrar->getPaths(ComponentRegistrar::MODULE) as $moduleName => $modulePath) {
            $namespacePrefix = str_replace('_', '\\', (string) $moduleName) . '\\';
            if (str_starts_with($fqcn, $namespacePrefix)) {
                return (string) $moduleName;
            }
        }

        return null;
    }
}
