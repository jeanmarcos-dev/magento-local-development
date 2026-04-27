<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\Module\ModuleListInterface;

/**
 * MCP tool: list every installed Magento module with version, sequence, and
 * active state. Intended as the entry-point an LLM uses to orient itself
 * when first attached to an unfamiliar Magento install.
 */
class ListModulesTool implements ToolInterface
{
    public function __construct(
        private readonly ModuleListInterface $moduleList
    ) {
    }

    public function name(): string
    {
        return 'list_modules';
    }

    public function description(): string
    {
        return 'List every installed Magento module with name, setup_version, sequence dependencies and active state.';
    }

    /**
     * @return array<int, array{name: string, setup_version: ?string, sequence: array<int, string>, active: bool}>
     */
    public function execute(): array
    {
        $modules = $this->moduleList->getAll();
        $result = [];
        foreach ($modules as $name => $data) {
            $result[] = [
                'name' => (string) $name,
                'setup_version' => isset($data['setup_version']) ? (string) $data['setup_version'] : null,
                'sequence' => array_values($data['sequence'] ?? []),
                'active' => true,
            ];
        }

        return $result;
    }
}
