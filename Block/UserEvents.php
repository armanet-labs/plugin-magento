<?php

namespace Armanet\Integration\Block;

use Magento\Customer\Model\Session\Proxy as CustomerSessionProxy;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\View\Element\Template;

class UserEvents extends Template
{
    protected $customerSession;
    protected $cache;

    public function __construct(
        Template\Context $context,
        CustomerSessionProxy $customerSession,
        CacheInterface $cache,
        array $data = []
    ) {
        $this->customerSession = $customerSession;
        $this->cache = $cache;
        parent::__construct($context, $data);
    }

    public function getAndClearEvents(): array
    {
        $customerId = (int) $this->customerSession->getCustomerId();
        if (!$customerId) {
            return [];
        }

        $cacheKey = 'armanet_user_events_' . $customerId;
        $cached = $this->cache->load($cacheKey);
        if (!$cached) {
            return [];
        }

        $events = json_decode($cached, true) ?: [];
        $this->cache->remove($cacheKey);
        return $events;
    }
}
