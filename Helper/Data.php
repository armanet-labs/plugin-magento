<?php

namespace Armanet\Integration\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractHelper
{
    const CONFIG_PATH_API_KEY = 'armanet_integration/general/api_key';
    const CONFIG_PATH_LOGGING = 'armanet_integration/general/enable_logging';

    public function getApiKey()
    {
        return $this->scopeConfig->getValue(self::CONFIG_PATH_API_KEY, ScopeInterface::SCOPE_STORE);
    }

    public function isLoggingEnabled()
    {
        return $this->scopeConfig->isSetFlag(self::CONFIG_PATH_LOGGING, ScopeInterface::SCOPE_STORE);
    }
}
