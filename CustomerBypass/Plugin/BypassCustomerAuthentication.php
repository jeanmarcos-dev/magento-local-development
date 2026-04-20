<?php

declare(strict_types=1);

namespace Development\CustomerBypass\Plugin;

use Development\CustomerBypass\Helper\Config;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Model\AccountManagement;

class BypassCustomerAuthentication
{
    public function __construct(
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly Config $config
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
        if (!$this->config->isEnabled()) {
            return $proceed($username, $password);
        }

        return $this->customerRepository->get($username);
    }
}
