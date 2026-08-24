<?php

namespace Armanet\Integration\Observer;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerRegisterSuccess implements ObserverInterface
{
    protected $helper;
    protected $cache;

    public function __construct(
        ConfigHelper $helper,
        CacheInterface $cache
    ) {
        $this->helper = $helper;
        $this->cache = $cache;
    }

    public function execute(Observer $observer)
    {
        if (!$this->helper->isRegistrationEventEnabled()) {
            return;
        }

        $customer = $observer->getEvent()->getCustomer();
        $customerId = (int) $customer->getId();

        $payload = [
            'customer' => [
                'id'     => $customerId,
                'groups' => $this->helper->getGroupNames($customer->getGroupId()),
            ],
        ];

        $cacheKey = 'armanet_user_events_' . $customerId;
        $existing = $this->cache->load($cacheKey);
        $events = $existing ? json_decode($existing, true) : [];
        $events[] = [
            'name'    => 'signup',
            'payload' => $payload,
        ];
        $this->cache->save(json_encode($events), $cacheKey, [], 300);
    }
}
