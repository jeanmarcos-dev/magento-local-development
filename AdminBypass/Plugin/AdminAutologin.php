<?php

declare(strict_types=1);

namespace Development\AdminBypass\Plugin;

use Development\AdminBypass\Helper\Config;
use Magento\Authorization\Model\ResourceModel\Role\CollectionFactory as RoleCollectionFactory;
use Magento\Authorization\Model\RoleFactory;
use Magento\Backend\Controller\Adminhtml\Auth\Login;
use Magento\Backend\Model\Auth;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\User\Model\ResourceModel\User as UserResource;
use Magento\User\Model\UserFactory;

class AdminAutologin
{
    private const DEFAULT_USERNAME = 'local';
    private const DEFAULT_PASSWORD = 'local123';
    private const DEFAULT_EMAIL = 'john.smith@gmail.com';
    private const DEFAULT_FIRSTNAME = 'john';
    private const DEFAULT_LASTNAME = 'smith';

    public function __construct(
        private readonly Auth $auth,
        private readonly RedirectFactory $resultRedirectFactory,
        private readonly UserFactory $userFactory,
        private readonly UserResource $userResource,
        private readonly RoleFactory $roleFactory,
        private readonly RoleCollectionFactory $roleCollectionFactory,
        private readonly Config $config
    ) {
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function aroundExecute(Login $subject, \Closure $proceed): ResultInterface
    {
        if (!$this->config->isEnabled()) {
            return $proceed();
        }

        if ($this->auth->isLoggedIn()) {
            return $proceed();
        }

        $this->ensureAdminUserExists();

        try {
            $this->auth->login(self::DEFAULT_USERNAME, self::DEFAULT_PASSWORD);

            $redirect = $this->resultRedirectFactory->create();
            $redirect->setPath('*/dashboard');

            return $redirect;
        } catch (\Exception $e) {
            // If autologin fails for any reason, show the normal login form
            return $proceed();
        }
    }

    private function ensureAdminUserExists(): void
    {
        $user = $this->userFactory->create();
        $this->userResource->load($user, self::DEFAULT_USERNAME, 'username');

        if ($user->getId()) {
            return;
        }

        $user->setUserName(self::DEFAULT_USERNAME)
            ->setFirstName(self::DEFAULT_FIRSTNAME)
            ->setLastName(self::DEFAULT_LASTNAME)
            ->setEmail(self::DEFAULT_EMAIL)
            ->setPassword(self::DEFAULT_PASSWORD)
            ->setIsActive(true);
        $user->save();

        $this->assignAdministratorRole($user);
    }

    private function assignAdministratorRole(\Magento\User\Model\User $user): void
    {
        $adminRoleId = $this->getAdministratorsRoleId();

        if (!$adminRoleId) {
            return;
        }

        $role = $this->roleFactory->create();
        $role->setParentId($adminRoleId)
            ->setTreeLevel(2)
            ->setRoleType('U')
            ->setUserId($user->getId())
            ->setUserType(2) // Magento\Authorization\Model\UserContextInterface::USER_TYPE_ADMIN
            ->setRoleName($user->getFirstName());
        $role->save();
    }

    private function getAdministratorsRoleId(): int
    {
        $collection = $this->roleCollectionFactory->create();
        $collection->addFieldToFilter('role_name', 'Administrators')
            ->addFieldToFilter('role_type', 'G');

        return (int) $collection->getFirstItem()->getId();
    }
}
