<?php

namespace Armanet\Integration\Test\Unit\Block\CheckoutSuccess;

use Armanet\Integration\Block\CheckoutSuccess\Index;
use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Checkout\Model\Session\Proxy as CheckoutSession;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Address as OrderAddress;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    private $block;
    private $checkoutSessionMock;
    private $orderMock;
    private $orderItemsMock;
    private $shippingAddressMock;

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

        $this->block = $objectManager->getObject(Index::class, [
            'checkoutSession'        => $this->checkoutSessionMock,
            'orderCollectionFactory' => $this->createMock(OrderCollectionFactory::class),
            'configHelper'           => $this->createMock(ConfigHelper::class),
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
}
