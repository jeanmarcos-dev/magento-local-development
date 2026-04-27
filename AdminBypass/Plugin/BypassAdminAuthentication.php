<?php

declare(strict_types=1);

namespace Development\AdminBypass\Plugin;

use Development\Core\Model\ProductionGuard;
use Magento\User\Model\User;

class BypassAdminAuthentication
{
    public function __construct(
        private readonly ProductionGuard $guard
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundVerifyIdentity(User $subject, \Closure $proceed, $password): bool
    {
        if (!$this->guard->isEnabled()) {
            return $proceed($password);
        }

        return true;
    }
}
