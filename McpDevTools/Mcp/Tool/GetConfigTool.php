<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * MCP tool: read a Magento configuration value with proper scope resolution.
 *
 * Returns whatever Magento returns — scalar string, nested array (for groups),
 * or null when the path is unset. The LLM is expected to reason about the
 * shape from the path semantics.
 */
class GetConfigTool implements ToolInterface
{
    private const ALLOWED_SCOPES = ['default', 'websites', 'stores'];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function name(): string
    {
        return 'get_config';
    }

    public function description(): string
    {
        return 'Read a Magento config value at a given path with optional scope resolution. Path uses slash form like "web/unsecure/base_url". Scope is one of: default, websites, stores. scope_code is the website/store code or numeric id.';
    }

    /**
     * @return array{path: string, scope: string, scope_code: ?string, value: mixed}
     */
    public function execute(string $path, ?string $scope = 'default', ?string $scope_code = null): array
    {
        $resolvedScope = in_array($scope, self::ALLOWED_SCOPES, true) ? $scope : 'default';
        $value = $this->scopeConfig->getValue($path, $resolvedScope, $scope_code);

        return [
            'path' => $path,
            'scope' => $resolvedScope,
            'scope_code' => $scope_code,
            'value' => $value,
        ];
    }
}
