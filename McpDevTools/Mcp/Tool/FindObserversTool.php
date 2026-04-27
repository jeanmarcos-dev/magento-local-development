<?php

declare(strict_types=1);

namespace Development\McpDevTools\Mcp\Tool;

use Development\McpDevTools\Mcp\ToolInterface;
use Magento\Framework\Event\ConfigInterface;

/**
 * MCP tool: list observers registered for a given Magento event.
 *
 * Backed by Magento's already-merged event configuration; no XML scanning.
 */
class FindObserversTool implements ToolInterface
{
    public function __construct(
        private readonly ConfigInterface $eventConfig
    ) {
    }

    public function name(): string
    {
        return 'find_observers';
    }

    public function description(): string
    {
        return 'List observers registered for a given Magento event name (e.g. "sales_order_place_after"). Returns instance class, callback method and shared/disabled flags.';
    }

    /**
     * @return array<int, array{name: ?string, instance: ?string, method: ?string, shared: ?bool, disabled: ?bool}>
     */
    public function execute(string $event): array
    {
        $observers = $this->eventConfig->getObservers($event);
        $result = [];
        foreach ($observers as $name => $observer) {
            $result[] = [
                'name' => is_string($name) ? $name : (isset($observer['name']) ? (string) $observer['name'] : null),
                'instance' => isset($observer['instance']) ? (string) $observer['instance'] : null,
                'method' => isset($observer['method']) ? (string) $observer['method'] : null,
                'shared' => isset($observer['shared']) ? (bool) $observer['shared'] : null,
                'disabled' => isset($observer['disabled']) ? (bool) $observer['disabled'] : null,
            ];
        }

        return $result;
    }
}
