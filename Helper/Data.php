<?php

namespace Armanet\Integration\Helper;

use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const CONFIG_PATH_API_KEY = 'armanet/settings/api_key';
    const CONFIG_PATH_ENABLE_TRACKING = 'armanet/settings/enable_tracking';
    const CONFIG_PATH_ENABLE_FEED = 'armanet/settings/enable_feed';
    const CONFIG_PATH_DEBUG_MODE = 'armanet/settings/debug_mode';
    const CONFIG_PATH_ENABLE_LOGIN_EVENT = 'armanet/settings/enable_login_event';
    const CONFIG_PATH_ENABLE_REGISTRATION_EVENT = 'armanet/settings/enable_registration_event';
    const CONFIG_PATH_UPC_ATTRIBUTE = 'armanet/settings/upc_attribute';

    protected $groupRepository;
    protected $addressRepository;

    public function __construct(
        Context $context,
        GroupRepositoryInterface $groupRepository,
        AddressRepositoryInterface $addressRepository
    ) {
        $this->groupRepository = $groupRepository;
        $this->addressRepository = $addressRepository;
        parent::__construct($context);
    }

    public function getApiKey()
    {
        return $this->scopeConfig->getValue(
            self::CONFIG_PATH_API_KEY,
            ScopeInterface::SCOPE_STORE,
        );
    }

    public function isTrackingEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLE_TRACKING,
            ScopeInterface::SCOPE_STORE,
        );
    }

    public function isFeedEnabled()
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLE_FEED,
            ScopeInterface::SCOPE_STORE,
        );
    }

    public function isDebugEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_DEBUG_MODE,
            ScopeInterface::SCOPE_STORE,
        );
    }

    public function isLoginEventEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLE_LOGIN_EVENT,
            ScopeInterface::SCOPE_STORE,
        );
    }

    public function isRegistrationEventEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::CONFIG_PATH_ENABLE_REGISTRATION_EVENT,
            ScopeInterface::SCOPE_STORE,
        );
    }

    public function getUpcAttribute(): string
    {
        return (string) ($this->scopeConfig->getValue(
            self::CONFIG_PATH_UPC_ATTRIBUTE,
            ScopeInterface::SCOPE_STORE,
        ) ?: 'upc');
    }

    public function getGroupNames(?int $groupId): array
    {
        if ($groupId === null) {
            return [];
        }

        try {
            return [$this->groupRepository->getById($groupId)->getCode()];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function getBillingAddress(?int $addressId): ?AddressInterface
    {
        if (!$addressId) {
            return null;
        }

        try {
            return $this->addressRepository->getById($addressId);
        } catch (\Exception $e) {
            return null;
        }
    }
}
