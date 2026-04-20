<?php

declare(strict_types=1);

namespace Development\CustomerBypass\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\State;
use Magento\Framework\Exception\LocalizedException;
use Magento\Store\Model\ScopeInterface;

class Config extends AbstractHelper
{
    private const XML_PATH_ALLOW_IN_PRODUCTION = 'development/customer_bypass/allow_in_production';

    public function __construct(
        Context $context,
        private readonly State $state
    ) {
        parent::__construct($context);
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
            self::XML_PATH_ALLOW_IN_PRODUCTION,
            ScopeInterface::SCOPE_STORE
        );
    }
}
