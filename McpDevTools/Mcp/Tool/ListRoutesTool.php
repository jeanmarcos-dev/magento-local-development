<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\App\Route\Config\Reader as RouteConfigReader;
use Magento\Framework\Config\ScopeInterface;
use Throwable;

/**
 * MCP tool: list routes (route_id → frontName → modules) for a given area.
 *
 * Reads Magento's already-merged route data via the same reader that backs
 * `RouteConfigInterface`. No XML scanning.
 */
class ListRoutesTool implements ToolInterface
{
    private const KNOWN_AREAS = ['frontend', 'adminhtml', 'webapi_rest', 'webapi_soap', 'graphql'];

    public function __construct(
        private readonly RouteConfigReader $routeReader,
        private readonly ScopeInterface $configScope
    ) {
    }

    public function name(): string
    {
        return 'list_routes';
    }

    public function description(): string
    {
        return 'List every route registered in the given Magento area (default: frontend). Returns route_id, frontName and the modules handling it. Areas: frontend, adminhtml, webapi_rest, webapi_soap, graphql.';
    }

    /**
     * @return array{
     *     area: string,
     *     routes: array<int, array{route_id: string, frontName: string, modules: array<int, string>}>
     * }
     */
    public function execute(?string $area = 'frontend'): array
    {
        $resolvedArea = in_array($area, self::KNOWN_AREAS, true) ? $area : 'frontend';

        $previousScope = null;
        try {
            $previousScope = $this->configScope->getCurrentScope();
        } catch (Throwable) {
            // Some scope managers throw when no scope has been set yet.
        }

        $routes = [];
        try {
            $this->configScope->setCurrentScope($resolvedArea);
            $merged = $this->routeReader->read($resolvedArea);
            foreach (is_array($merged) ? $merged : [] as $routerId => $routesByRouter) {
                foreach (is_array($routesByRouter) ? $routesByRouter : [] as $routeId => $routeData) {
                    $routes[] = [
                        'router_id' => (string) $routerId,
                        'route_id' => (string) $routeId,
                        'frontName' => (string) ($routeData['frontName'] ?? ''),
                        'modules' => array_values(array_map('strval', $routeData['modules'] ?? [])),
                    ];
                }
            }
        } finally {
            if (is_string($previousScope)) {
                $this->configScope->setCurrentScope($previousScope);
            }
        }

        return [
            'area' => $resolvedArea,
            'routes' => $routes,
        ];
    }
}
