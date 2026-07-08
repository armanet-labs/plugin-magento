<?php

namespace Armanet\Integration\Test\Unit\Block\CheckoutSuccess;

use Armanet\Integration\Block\CheckoutSuccess\Index;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\Collection as OrderCollection;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private $block;
    private $checkoutSessionMock;
    private $orderMock;
    private $orderItemsMock;
    private $shippingAddressMock;
    private $orderCollectionFactoryMock;
    private $orderCollectionMock;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->orderMock = $this->createMock(Order::class);
        $this->orderItemsMock = [
            $this->createMock(OrderItem::class),
            $this->createMock(OrderItem::class)
        ];
        $this->shippingAddressMock = $this->createMock(OrderAddress::class);

        $this->orderCollectionMock = $this->createMock(OrderCollection::class);
        $this->orderCollectionMock->method('addFieldToFilter')->willReturnSelf();
        $this->orderCollectionMock->method('setOrder')->willReturnSelf();
        $this->orderCollectionMock->method('setPageSize')->willReturnSelf();

        $this->orderCollectionFactoryMock = $this->createMock(OrderCollectionFactory::class);
        $this->orderCollectionFactoryMock->method('create')->willReturn($this->orderCollectionMock);

        $this->block = $objectManager->getObject(Index::class, [
            'checkoutSession'        => $this->checkoutSessionMock,
            'orderCollectionFactory' => $this->orderCollectionFactoryMock,
        ]);
    }

    public function testIsGettingOrder()
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->orderMock);

        $this->assertSame($this->orderMock, $this->block->getOrder());
    }

    public function testIsGettingOrderId()
    {
        $orderId = '000000123';

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->once())
            ->method('getIncrementId')
            ->willReturn($orderId);

        $this->assertEquals($orderId, $this->block->getOrderId());
    }

    public function testIsGettingItems()
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->once())
            ->method('getAllVisibleItems')
            ->willReturn($this->orderItemsMock);

        $this->assertSame($this->orderItemsMock, $this->block->getItems());
    }

    public function testIsGettingOrderTotal()
    {
        $orderTotal = 99.99;

        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->once())
            ->method('getGrandTotal')
            ->willReturn($orderTotal);

        $this->assertEquals($orderTotal, $this->block->getOrderTotal());
    }

    public function testIsGettingShippingAddress()
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->orderMock);

        $this->orderMock->expects($this->once())
            ->method('getShippingAddress')
            ->willReturn($this->shippingAddressMock);

        $this->assertSame($this->shippingAddressMock, $this->block->getShippingAddress());
    }

    public function testGetCustomerDataForFirstTimePurchaseHasNullDaysSinceLastPurchase()
    {
        $this->checkoutSessionMock->method('getLastRealOrder')->willReturn($this->orderMock);

        $this->orderMock->method('getCustomerId')->willReturn(7);
        $this->orderMock->method('getId')->willReturn(55);
        $this->orderMock->method('getBillingAddress')->willReturn(null);

        $this->orderCollectionMock->method('getSize')->willReturn(0);

        $data = $this->block->getCustomerData();

        $this->assertNull($data['daysSinceLastPurchase']);
        $this->assertSame(1, $data['totalOrders']);
        $this->assertTrue($data['isFirstPurchase']);
    }

    public function testGetCustomerDataForReturningCustomerIncludesDaysSinceLastPurchase()
    {
        $this->checkoutSessionMock->method('getLastRealOrder')->willReturn($this->orderMock);

        $this->orderMock->method('getCustomerId')->willReturn(7);
        $this->orderMock->method('getId')->willReturn(55);
        $this->orderMock->method('getBillingAddress')->willReturn(null);
        $this->orderMock->method('getCreatedAt')->willReturn('2024-06-15 00:00:00');

        $this->orderCollectionMock->method('getSize')->willReturn(2);

        $lastOrderMock = $this->createMock(Order::class);
        $lastOrderMock->method('getCreatedAt')->willReturn('2024-06-01 00:00:00');
        $this->orderCollectionMock->method('getFirstItem')->willReturn($lastOrderMock);

        $data = $this->block->getCustomerData();

        $this->assertSame(14, $data['daysSinceLastPurchase']);
        $this->assertSame(3, $data['totalOrders']);
        $this->assertArrayNotHasKey('isFirstPurchase', $data);
    }
}
