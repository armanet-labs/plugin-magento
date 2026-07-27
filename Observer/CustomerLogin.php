<?php

namespace Armanet\Integration\Observer;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerLogin implements ObserverInterface
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
        if (!$this->helper->isLoginEventEnabled()) {
            return;
        }

        $customer = $observer->getEvent()->getCustomer();
        $address = $this->helper->getBillingAddress($customer->getDefaultBilling());
        $region = $address ? $address->getRegion() : null;

        $customerData = [
            'id'             => $customer->getId(),
            'country'        => $address ? $address->getCountryId() : '',
            'city'           => $address ? $address->getCity() : '',
            'state'          => $region ? $region->getRegionCode() : '',
            'zip'            => $address ? $address->getPostcode() : '',
            'groups'         => $this->helper->getGroupNames($customer->getGroupId()),
        ];

        $this->queueEvent((int) $customer->getId(), 'login', ['customer' => $customerData]);
    }

    private function queueEvent(int $customerId, string $name, array $payload)
    {
        $cacheKey = 'armanet_user_events_' . $customerId;
        $existing = $this->cache->load($cacheKey);
        $events = $existing ? json_decode($existing, true) : [];

        foreach ($events as $event) {
            if ($event['name'] === $name) {
                return;
            }
        }

        $events[] = ['name' => $name, 'payload' => $payload];
        $this->cache->save(json_encode($events), $cacheKey, [], 300);
    }
}
