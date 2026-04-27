<?php

declare(strict_types=1);

namespace Development\CustomerBypass\Plugin;

use Development\Core\Model\ProductionGuard;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AccountManagement;

class BypassCustomerAuthentication
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly ProductionGuard $guard
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundAuthenticate(
        AccountManagement $subject,
        \Closure $proceed,
        $username,
        $password
    ): CustomerInterface {
        if (!$this->guard->isEnabled()) {
            return $proceed($username, $password);
        }

        return $this->customerRepository->get($username);
    }
}
