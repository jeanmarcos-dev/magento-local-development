<?php

declare(strict_types=1);

namespace Development\Core\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

/**
 * Shared production guard for `Development_*` development-only modules.
 *
 * Returns `true` (active) when:
 *   - Magento is NOT in production mode, OR
 *   - the per-module `allow_in_production` flag is explicitly set to Yes.
 *
 * Consumers configure the per-module XML config path via virtual types in `di.xml`.
 */
class ProductionGuard
{
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly State $state,
        private readonly string $configPath
    ) {
    }

    public function isEnabled(): bool
    {
        try {
            $mode = $this->state->getMode();
        } catch (LocalizedException) {
            return true;
        }

        if ($mode !== State::MODE_PRODUCTION) {
            return true;
        }

        return $this->scopeConfig->isSetFlag(
            $this->configPath,
            ScopeInterface::SCOPE_STORE
        );
    }
}
