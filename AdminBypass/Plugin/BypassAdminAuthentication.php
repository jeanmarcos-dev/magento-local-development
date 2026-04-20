<?php

declare(strict_types=1);

namespace Development\AdminBypass\Plugin;

use Development\AdminBypass\Helper\Config;
use Magento\User\Model\User;

class BypassAdminAuthentication
{
    public function __construct(
        private readonly Config $config
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundVerifyIdentity(User $subject, \Closure $proceed, $password): bool
    {
        if (!$this->config->isEnabled()) {
            return $proceed($password);
        }

        return true;
    }
}
