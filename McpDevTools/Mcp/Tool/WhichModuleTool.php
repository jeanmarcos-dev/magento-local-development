<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\Component\ComponentRegistrar;

/**
 * MCP tool: given a class name or filesystem path, return which Magento
 * component (module/theme/library/language) owns it.
 *
 * One of the highest-ROI tools for an LLM walking an unfamiliar codebase.
 */
class WhichModuleTool implements ToolInterface
{
    private const COMPONENT_TYPES = [
        ComponentRegistrar::MODULE,
        ComponentRegistrar::THEME,
        ComponentRegistrar::LIBRARY,
        ComponentRegistrar::LANGUAGE,
        ComponentRegistrar::SETUP,
    ];

    public function __construct(
        private readonly ComponentRegistrar $componentRegistrar
    ) {
    }

    public function name(): string
    {
        return 'which_module';
    }

    public function description(): string
    {
        return 'Given a fully-qualified class name (e.g. "Magento\\\\Catalog\\\\Model\\\\Product") or a filesystem path inside the Magento install, return the registered component (module/theme/library/language) that owns it. For class names it also returns the expected file path under the module root.';
    }

    /**
     * @return array{query: string, type: ?string, component_name: ?string, registered_path: ?string, expected_file: ?string, matched_via: ?string}
     */
    public function execute(string $query): array
    {
        $trimmed = trim($query);

        if ($trimmed === '') {
            return $this->notFound($query);
        }

        if (str_contains($trimmed, '/') || str_contains($trimmed, DIRECTORY_SEPARATOR)) {
            return $this->matchByPath($trimmed);
        }

        return $this->matchByFqcn($trimmed);
    }

    /**
     * @return array{query: string, type: ?string, component_name: ?string, registered_path: ?string, expected_file: ?string, matched_via: ?string}
     */
    private function matchByPath(string $path): array
    {
        $normalized = rtrim($path, '/');

        foreach (self::COMPONENT_TYPES as $type) {
            foreach ($this->componentRegistrar->getPaths($type) as $componentName => $componentPath) {
                $componentPath = rtrim($componentPath, '/');
                if ($normalized === $componentPath || str_starts_with($normalized, $componentPath . '/')) {
                    return [
                        'query' => $path,
                        'type' => $type,
                        'component_name' => $componentName,
                        'registered_path' => $componentPath,
                        'expected_file' => $normalized,
                        'matched_via' => 'path-prefix',
                    ];
                }
            }
        }

        return $this->notFound($path);
    }

    /**
     * @return array{query: string, type: ?string, component_name: ?string, registered_path: ?string, expected_file: ?string, matched_via: ?string}
     */
    private function matchByFqcn(string $fqcn): array
    {
        $clean = ltrim($fqcn, '\\');

        // PSR-4 modules use a Vendor\Module\Sub\Class layout where the registered
        // path points at the module root and the namespace prefix is Vendor\Module.
        // The Magento module name is Vendor_Module.
        foreach ($this->componentRegistrar->getPaths(ComponentRegistrar::MODULE) as $componentName => $componentPath) {
            $namespacePrefix = str_replace('_', '\\', (string) $componentName) . '\\';
            if (str_starts_with($clean, $namespacePrefix)) {
                $relative = substr($clean, strlen($namespacePrefix));
                $expected = rtrim($componentPath, '/') . '/' . str_replace('\\', '/', $relative) . '.php';

                return [
                    'query' => $fqcn,
                    'type' => ComponentRegistrar::MODULE,
                    'component_name' => $componentName,
                    'registered_path' => rtrim($componentPath, '/'),
                    'expected_file' => $expected,
                    'matched_via' => 'fqcn-namespace-prefix',
                ];
            }
        }

        return $this->notFound($fqcn);
    }

    /**
     * @return array{query: string, type: null, component_name: null, registered_path: null, expected_file: null, matched_via: null}
     */
    private function notFound(string $query): array
    {
        return [
            'query' => $query,
            'type' => null,
            'component_name' => null,
            'registered_path' => null,
            'expected_file' => null,
            'matched_via' => null,
        ];
    }
}
