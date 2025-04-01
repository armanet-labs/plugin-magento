<?php
namespace Armanet\Integration\Test\Unit\Block\CheckoutSuccess;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Armanet\Integration\Block\CheckoutSuccess\Index;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;

class IndexTest extends TestCase
{
    private $block;
    private $checkoutSessionMock;
    private $orderMock;

    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->checkoutSessionMock = $this->createMock(CheckoutSession::class);
        $this->orderMock = $this->createMock(Order::class);

        $this->block = $objectManager->getObject(Index::class, [
            'checkoutSession' => $this->checkoutSessionMock
        ]);
    }

    public function testGetOrder()
    {
        $this->checkoutSessionMock->expects($this->once())
            ->method('getLastRealOrder')
            ->willReturn($this->orderMock);

        $this->assertSame($this->orderMock, $this->block->getOrder());
    }

    public function testGetOrderId()
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
}
