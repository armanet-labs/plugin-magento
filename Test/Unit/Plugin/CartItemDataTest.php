<?php

namespace Armanet\Integration\Test\Unit\Plugin;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Armanet\Integration\Plugin\CartItemData;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Checkout\CustomerData\AbstractItem;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;
use PHPUnit\Framework\TestCase;

class CartItemDataTest extends TestCase
{
    /** @var ConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
    private $configHelperMock;

    /** @var ProductResource&\PHPUnit\Framework\MockObject\MockObject */
    private $productResourceMock;

    /** @var CartItemData */
    private $plugin;

    protected function setUp(): void
    {
        $this->configHelperMock = $this->createMock(ConfigHelper::class);
        $this->configHelperMock->method('getUpcAttribute')->willReturn('upc');

        $this->productResourceMock = $this->getMockBuilder(ProductResource::class)
            ->disableOriginalConstructor()
            ->getMock();

        $this->plugin = new CartItemData($this->configHelperMock, $this->productResourceMock);
    }

    public function testReturnsEmptyUpcWhenProductIsNull()
    {
        $itemMock = $this->createMock(QuoteItem::class);
        $itemMock->method('getProduct')->willReturn(null);

        $result = $this->plugin->afterGetItemData(
            $this->createMock(AbstractItem::class),
            [],
            $itemMock
        );

        $this->assertArrayHasKey('upc', $result);
        $this->assertSame('', $result['upc']);
    }

    public function testInjectsUpcFromAttributeRawValue()
    {
        $productMock = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $productMock->method('getId')->willReturn(5);
        $productMock->method('getStoreId')->willReturn(1);

        $itemMock = $this->createMock(QuoteItem::class);
        $itemMock->method('getProduct')->willReturn($productMock);

        $this->productResourceMock->method('getAttributeRawValue')
            ->with(5, 'upc', 1)
            ->willReturn('012345678901');

        $result = $this->plugin->afterGetItemData(
            $this->createMock(AbstractItem::class),
            ['some_key' => 'some_value'],
            $itemMock
        );

        $this->assertSame('012345678901', $result['upc']);
        $this->assertSame('some_value', $result['some_key']);
    }

    public function testReturnsEmptyStringWhenRawValueIsNotString()
    {
        $productMock = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $productMock->method('getId')->willReturn(7);
        $productMock->method('getStoreId')->willReturn(1);

        $itemMock = $this->createMock(QuoteItem::class);
        $itemMock->method('getProduct')->willReturn($productMock);

        $this->productResourceMock->method('getAttributeRawValue')->willReturn(false);

        $result = $this->plugin->afterGetItemData(
            $this->createMock(AbstractItem::class),
            [],
            $itemMock
        );

        $this->assertSame('', $result['upc']);
    }

    public function testUsesConfiguredUpcAttributeCode()
    {
        $this->configHelperMock = $this->createMock(ConfigHelper::class);
        $this->configHelperMock->method('getUpcAttribute')->willReturn('gtin');

        $this->plugin = new CartItemData($this->configHelperMock, $this->productResourceMock);

        $productMock = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $productMock->method('getId')->willReturn(3);
        $productMock->method('getStoreId')->willReturn(1);

        $itemMock = $this->createMock(QuoteItem::class);
        $itemMock->method('getProduct')->willReturn($productMock);

        $this->productResourceMock->expects($this->once())
            ->method('getAttributeRawValue')
            ->with(3, 'gtin', 1)
            ->willReturn('987654321098');

        $result = $this->plugin->afterGetItemData(
            $this->createMock(AbstractItem::class),
            [],
            $itemMock
        );

        $this->assertSame('987654321098', $result['upc']);
    }
}
