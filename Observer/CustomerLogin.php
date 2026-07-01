<?php

namespace Armanet\Integration\Observer;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class CustomerLogin implements ObserverInterface
{
    protected $helper;
    protected $cache;
    protected $addressRepository;

    public function __construct(
        ConfigHelper $helper,
        CacheInterface $cache,
        AddressRepositoryInterface $addressRepository
    ) {
        $this->helper = $helper;
        $this->cache = $cache;
        $this->addressRepository = $addressRepository;
    }

    public function execute(Observer $observer)
    {
        if (!$this->helper->isLoginEventEnabled() || $this->helper->isCurrentUserExcluded()) {
            return;
        }

        $customer = $observer->getEvent()->getCustomer();

        $payload = [
            'userId'         => $customer->getId(),
            'accountCreated' => $customer->getCreatedAt(),
            'billingCity'    => null,
            'billingState'   => null,
            'billingCountry' => null,
        ];

        $billingAddressId = $customer->getDefaultBilling();
        if ($billingAddressId) {
            try {
                $address = $this->addressRepository->getById($billingAddressId);
                $region = $address->getRegion();
                $payload['billingCity']    = $address->getCity();
                $payload['billingState']   = $region ? $region->getRegionCode() : null;
                $payload['billingCountry'] = $address->getCountryId();
            } catch (\Exception $e) {
                // address not found or inaccessible — proceed without address data
            }
        }

        $this->queueEvent((int) $customer->getId(), 'login', $payload);
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
