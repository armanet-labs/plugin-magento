<?php

namespace Armanet\Integration\Helper;

use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const CONFIG_PATH_API_KEY = 'armanet/settings/api_key';
    const CONFIG_PATH_ENABLE_TRACKING = 'armanet/settings/enable_tracking';
    const CONFIG_PATH_ENABLE_FEED = 'armanet/settings/enable_feed';
    const CONFIG_PATH_STALE_BUYER_DAYS = 'armanet/settings/stale_buyer_days';
    const CONFIG_PATH_DEBUG_MODE = 'armanet/settings/debug_mode';
    const CONFIG_PATH_EXCLUDED_GROUPS = 'armanet/settings/excluded_customer_groups';
    const CONFIG_PATH_ENABLE_LOGIN_EVENT = 'armanet/settings/enable_login_event';
    const CONFIG_PATH_ENABLE_REGISTRATION_EVENT = 'armanet/settings/enable_registration_event';
    const CONFIG_PATH_UPC_ATTRIBUTE = 'armanet/settings/upc_attribute';

    protected $customerSession;
    protected $groupRepository;
    private $isExcluded = null;

    public function __construct(
        Context $context,
        \Magento\Customer\Model\Session\Proxy $customerSession,
        GroupRepositoryInterface $groupRepository
    ) {
        $this->customerSession = $customerSession;
        $this->groupRepository = $groupRepository;
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

    public function getStaleBuyerDays(): int
    {
        return (int) ($this->scopeConfig->getValue(
            self::CONFIG_PATH_STALE_BUYER_DAYS,
            ScopeInterface::SCOPE_STORE,
        ) ?: 180);
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

    public function getExcludedCustomerGroups(): array
    {
        $value = $this->scopeConfig->getValue(
            self::CONFIG_PATH_EXCLUDED_GROUPS,
            ScopeInterface::SCOPE_STORE,
        );
        return $value ? explode(',', $value) : [];
    }

    public function isCurrentUserExcluded(): bool
    {
        if ($this->isExcluded !== null) {
            return $this->isExcluded;
        }

        $excludedGroups = $this->getExcludedCustomerGroups();
        if (empty($excludedGroups)) {
            $this->isExcluded = false;
            return false;
        }

        try {
            $group = $this->groupRepository->getById($this->customerSession->getCustomerGroupId());
            $groupName = $group->getCode();
        } catch (\Exception $e) {
            $this->isExcluded = false;
            return false;
        }

        $this->isExcluded = in_array(trim($groupName), array_map('trim', $excludedGroups), true);
        return $this->isExcluded;
    }
}
