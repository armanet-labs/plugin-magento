<?php

namespace Armanet\Integration\Test\Unit\Observer;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Armanet\Integration\Observer\CustomerLogin;
use Magento\Customer\Api\AddressRepositoryInterface;
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

    /** @var AddressRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $addressRepositoryMock;

    /** @var CustomerLogin */
    private $observer;

    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(ConfigHelper::class);
        $this->cacheMock = $this->createMock(CacheInterface::class);
        $this->addressRepositoryMock = $this->createMock(AddressRepositoryInterface::class);

        $this->observer = new CustomerLogin(
            $this->helperMock,
            $this->cacheMock,
            $this->addressRepositoryMock,
        );
    }

    public function testDoesNothingWhenLoginEventIsDisabled()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(false);
        $this->helperMock->method('isCurrentUserExcluded')->willReturn(false);

        $this->cacheMock->expects($this->never())->method('save');

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    public function testDoesNothingWhenUserIsExcluded()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);
        $this->helperMock->method('isCurrentUserExcluded')->willReturn(true);

        $this->cacheMock->expects($this->never())->method('save');

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    public function testQueuesLoginEventForCustomer()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);
        $this->helperMock->method('isCurrentUserExcluded')->willReturn(false);

        $this->cacheMock->method('load')->willReturn(false);

        $this->cacheMock->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($json) {
                    $events = json_decode($json, true);
                    return count($events) === 1
                        && $events[0]['name'] === 'login'
                        && $events[0]['payload']['userId'] === 42;
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
        $this->helperMock->method('isCurrentUserExcluded')->willReturn(false);

        $existing = json_encode([['name' => 'login', 'payload' => ['userId' => 42]]]);
        $this->cacheMock->method('load')->willReturn($existing);

        $this->cacheMock->expects($this->never())->method('save');

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    public function testIncludesBillingAddressInPayloadWhenAvailable()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);
        $this->helperMock->method('isCurrentUserExcluded')->willReturn(false);
        $this->cacheMock->method('load')->willReturn(false);

        $regionMock = $this->createMock(RegionInterface::class);
        $regionMock->method('getRegionCode')->willReturn('CA');

        $addressMock = $this->createMock(AddressInterface::class);
        $addressMock->method('getCity')->willReturn('Los Angeles');
        $addressMock->method('getRegion')->willReturn($regionMock);
        $addressMock->method('getCountryId')->willReturn('US');

        $this->addressRepositoryMock->method('getById')->with(99)->willReturn($addressMock);

        $this->cacheMock->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($json) {
                    $payload = json_decode($json, true)[0]['payload'];
                    return $payload['billingCity'] === 'Los Angeles'
                        && $payload['billingState'] === 'CA'
                        && $payload['billingCountry'] === 'US';
                }),
                $this->anything(),
                $this->anything(),
                $this->anything()
            );

        $this->observer->execute($this->createObserverWithCustomer(42, 99));
    }

    public function testPayloadHasNullAddressFieldsWhenNoBillingAddress()
    {
        $this->helperMock->method('isLoginEventEnabled')->willReturn(true);
        $this->helperMock->method('isCurrentUserExcluded')->willReturn(false);
        $this->cacheMock->method('load')->willReturn(false);

        $this->cacheMock->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($json) {
                    $payload = json_decode($json, true)[0]['payload'];
                    return $payload['billingCity'] === null
                        && $payload['billingState'] === null
                        && $payload['billingCountry'] === null;
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

        $event = new \Magento\Framework\Event(['customer' => $customerMock]);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($event);

        return $observerMock;
    }
}
