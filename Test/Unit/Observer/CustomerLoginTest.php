<?php

namespace Armanet\Integration\Test\Unit\Observer;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Armanet\Integration\Observer\CustomerLogin;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

class CustomerLoginTest extends TestCase
{
    /** @var ConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
    private $helperMock;

    /** @var CacheInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $cacheMock;

    /** @var CustomerLogin */
    private $observer;

    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(ConfigHelper::class);
        $this->cacheMock = $this->createMock(CacheInterface::class);

        $this->observer = new CustomerLogin(
            $this->helperMock,
            $this->cacheMock,
        );
    }

    public function testDoesNothingWhenLoginEventIsDisabled()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(false);

        $this->cacheMock->expects($this->never())->method('save');

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    public function testQueuesLoginEventForCustomer()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);
        $this->helperMock->method('getGroupNames')->with(4)->willReturn(['General']);

        $this->cacheMock->method('load')->willReturn(false);

        $this->cacheMock->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($json) {
                    $events = json_decode($json, true);
                    return count($events) === 1
                        && $events[0]['name'] === 'login'
                        && $events[0]['payload']['customer']['id'] === 42
                        && $events[0]['payload']['customer']['groups'] === ['General'];
                }),
                'armanet_user_events_42',
                [],
                300
            );

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    public function testDoesNotQueueDuplicateLoginEvent()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);

        $existing = json_encode([['name' => 'login', 'payload' => ['customer' => ['id' => 42]]]]);
        $this->cacheMock->method('load')->willReturn($existing);

        $this->cacheMock->expects($this->never())->method('save');

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    public function testIncludesBillingAddressInPayloadWhenAvailable()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);
        $this->helperMock->method('getGroupNames')->willReturn([]);
        $this->cacheMock->method('load')->willReturn(false);

        $regionMock = $this->createMock(RegionInterface::class);
        $regionMock->method('getRegionCode')->willReturn('CA');

        $addressMock = $this->createMock(AddressInterface::class);
        $addressMock->method('getCity')->willReturn('Los Angeles');
        $addressMock->method('getRegion')->willReturn($regionMock);
        $addressMock->method('getCountryId')->willReturn('US');
        $addressMock->method('getPostcode')->willReturn('90001');

        $this->helperMock->method('getBillingAddress')->with(99)->willReturn($addressMock);

        $this->cacheMock->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($json) {
                    $customer = json_decode($json, true)[0]['payload']['customer'];
                    return $customer['city'] === 'Los Angeles'
                        && $customer['state'] === 'CA'
                        && $customer['country'] === 'US'
                        && $customer['postcode'] === '90001';
                }),
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $this->observer->execute($this->createObserverWithCustomer(42, 99));
    }

    public function testPayloadHasEmptyAddressFieldsWhenNoBillingAddress()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);
        $this->helperMock->method('getGroupNames')->willReturn([]);
        $this->cacheMock->method('load')->willReturn(false);

        $this->cacheMock->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($json) {
                    $customer = json_decode($json, true)[0]['payload']['customer'];
                    return $customer['city'] === ''
                        && $customer['state'] === ''
                        && $customer['country'] === ''
                        && $customer['postcode'] === '';
                }),
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $this->observer->execute($this->createObserverWithCustomer(42, null));
    }

    private function createObserverWithCustomer(int $customerId, ?int $billingAddressId = null): Observer
    {
        $customerMock = $this->createMock(CustomerInterface::class);
        $customerMock->method('getId')->willReturn($customerId);
        $customerMock->method('getCreatedAt')->willReturn('2024-01-01 00:00:00');
        $customerMock->method('getDefaultBilling')->willReturn($billingAddressId);
        $customerMock->method('getGroupId')->willReturn(4);

        $event = new \Magento\Framework\Event(['customer' => $customerMock]);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($event);

        return $observerMock;
    }
}
