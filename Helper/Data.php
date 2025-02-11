<?php

namespace Armanet\Integration\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const CONFIG_PATH_API_KEY = 'armanet/settings/api_key';
    const CONFIG_PATH_ENABLE_TRACKING = 'armanet/settings/enable_tracking';
    const CONFIG_PATH_ENABLE_FEED = 'armanet/settings/enable_feed';

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
}
