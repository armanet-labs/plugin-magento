<?php
namespace Armanet\Integration\Test\Unit\Block\CheckoutSuccess;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Armanet\Integration\Block\CheckoutSuccess\Index;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Item as OrderItem;
use Magento\Sales\Model\Order\Address as OrderAddress;

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
            'checkoutSession' => $this->checkoutSessionMock
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
