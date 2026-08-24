<?php

namespace Armanet\Integration\Test\Unit\Observer;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Armanet\Integration\Observer\CustomerRegisterSuccess;
use Magento\Customer\Api\Data\CustomerInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

class CustomerRegisterSuccessTest extends TestCase
{
    /** @var ConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
    private $helperMock;

    /** @var CacheInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $cacheMock;

    /** @var CustomerRegisterSuccess */
    private $observer;

    protected function setUp(): void
    {
        $this->helperMock = $this->createMock(ConfigHelper::class);
        $this->cacheMock = $this->createMock(CacheInterface::class);

        $this->observer = new CustomerRegisterSuccess(
            $this->helperMock,
            $this->cacheMock,
        );
    }

    public function testDoesNothingWhenRegistrationEventIsDisabled()
    {
        $this->helperMock->method('isRegistrationEventEnabled')->willReturn(false);

        $this->cacheMock->expects($this->never())->method('save');

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    public function testQueuesSignupEventWithGroups()
    {
        $this->helperMock->method('isRegistrationEventEnabled')->willReturn(true);
        $this->helperMock->method('getGroupNames')->with(1)->willReturn(['General']);
        $this->cacheMock->method('load')->willReturn(false);

        $this->cacheMock->expects($this->once())
            ->method('save')
            ->with(
                $this->callback(function ($json) {
                    $events = json_decode($json, true);
                    return count($events) === 1
                        && $events[0]['name'] === 'signup'
                        && $events[0]['payload']['customer']['id'] === 42
                        && $events[0]['payload']['customer']['groups'] === ['General'];
                }),
                'armanet_user_events_42',
                [],
                300
            );

        $this->observer->execute($this->createObserverWithCustomer(42));
    }

    private function createObserverWithCustomer(int $customerId): Observer
    {
        $customerMock = $this->createMock(CustomerInterface::class);
        $customerMock->method('getId')->willReturn($customerId);
        $customerMock->method('getGroupId')->willReturn(1);

        $event = new \Magento\Framework\Event(['customer' => $customerMock]);

        $observerMock = $this->createMock(Observer::class);
        $observerMock->method('getEvent')->willReturn($event);

        return $observerMock;
    }
}
