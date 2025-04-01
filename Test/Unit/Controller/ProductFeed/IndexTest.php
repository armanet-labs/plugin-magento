<?php
namespace Armanet\Integration\Test\Unit\Controller\ProductFeed;

use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use PHPUnit\Framework\TestCase;
use Magento\Framework\Filesystem\Io\IoInterface;
use Armanet\Integration\Controller\ProductFeed\Index;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Armanet\Integration\Helper\Data;

class IndexTest extends TestCase
{
    /** @var \PHPUnit\Framework\MockObject\MockObject|Context */
    protected $contextMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|RawFactory */
    protected $resultRawFactoryMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|CollectionFactory */
    protected $collectionFactoryMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|ProductRepositoryInterface */
    protected $productRepositoryMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Data */
    protected $configHelperMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|RequestInterface */
    protected $requestMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Raw */
    protected $rawResultMock;

    /** @var Index */
    protected $controller;

    protected string $constUA;
    protected int $constMaxPageSize;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(Index::class);
        $this->constUA = $reflection->getConstant('UA');
        $this->constMaxPageSize = $reflection->getConstant('MAX_PAGE_SIZE');


        $this->resultRawFactoryMock = $this->createMock(RawFactory::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->configHelperMock = $this->createMock(Data::class);

        // Create a mock for the request and set it in the context
        $this->requestMock = $this->getMockBuilder(\Magento\Framework\HTTP\PhpEnvironment\Request::class)->disableOriginalConstructor()->getMock();
        $this->contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        // Create a mock for the Raw result and have resultRawFactory return it
        $this->rawResultMock = $this->getMockBuilder(Raw::class)->disableOriginalConstructor()->getMock();
        $this->resultRawFactoryMock->method('create')->willReturn($this->rawResultMock);

        // Instantiate the controller with our mocks
        $this->controller = new Index(
            $this->contextMock,
            $this->resultRawFactoryMock,
            $this->collectionFactoryMock,
            $this->productRepositoryMock,
            $this->configHelperMock,
        );

        $refObj = new \ReflectionObject($this->controller);
        $prop = $refObj->getProperty('_objectManager');
        $prop->setAccessible(true);
    }

    public function testIsExecutingWithFeedConfigurationDisabled()
    {
        $this->configHelperMock->method('isFeedEnabled')->willReturn(false);
        $this->rawResultMock->expects($this->once())->method('setHttpResponseCode')->with(404);

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithInvalidHeaders()
    {
        $apiKey = 'abc';

        $this->configHelperMock->method('isFeedEnabled')->willReturn(true);
        $this->configHelperMock->method('getApiKey')->willReturn($apiKey);

        $timestamp = date('YmdHi', time());
        $expectedSig = hash_hmac('sha256', $timestamp, $apiKey);
        var_dump("generado expectedsign en test: ", $expectedSig);

        $this->requestMock->expects($this->any())
        ->method('getHeader')
        ->willReturnCallback(function($headerName) {
            if ($headerName === 'X-FeedSign') {
                return 'Invalid';
            } elseif ($headerName === 'User-Agent') {
                return $this->constUA;
            }
            return null;
        });

        $this->rawResultMock->expects($this->once())->method('setHttpResponseCode')->with(404);

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWhitoutProducts()
    {
        $apiKey = 'abc';

        $this->configHelperMock->method('isFeedEnabled')->willReturn(true);
        $this->configHelperMock->method('getApiKey')->willReturn($apiKey);

        $timestamp = date('YmdHi', time());
        $expectedSig = hash_hmac('sha256', $timestamp, $apiKey);

        $this->requestMock->expects($this->any())
        ->method('getHeader')
        ->willReturnCallback(function($headerName) use ($expectedSig) {
            if ($headerName === 'X-FeedSign') {
                return $expectedSig;
            } elseif ($headerName === 'User-Agent') {
                return $this->constUA;
            }
            return null;
        });

        $this->requestMock->expects($this->any())
        ->method('getParam')
        ->willReturnCallback(function($paramName) {
            if ($paramName === 'p') {
                return 1;
            } elseif ($paramName === 's') {
                return $this->constMaxPageSize;
            }
            return null;
        });

        $collectionMock = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $collectionMock->method('addAttributeToSelect')->willReturnSelf();
        $collectionMock->method('addAttributeToFilter')->willReturnSelf();
        $collectionMock->method('setPageSize')->willReturnSelf();
        $collectionMock->method('setCurPage')->willReturnSelf();
        $collectionMock->method('getSize')->willReturn(0);
        $collectionMock->method('count')->willReturn(0);
        $collectionMock->method('getIterator')->willReturn(new \ArrayIterator([]));

        $this->collectionFactoryMock->method('create')->willReturn($collectionMock);

        $expectedResult = [
            "data" => [],
            "meta" => [
                "total" => 0,
                "page" => 1,
                "next_page" => 0,
                "page_size" => $this->constMaxPageSize,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();

        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithProductsAndDefaultPagination()
    {
        $apiKey = 'abc';
        $currentPage = 1;

        $this->configHelperMock->method('isFeedEnabled')->willReturn(true);
        $this->configHelperMock->method('getApiKey')->willReturn($apiKey);

        $timestamp = date('YmdHi', time());
        $expectedSig = hash_hmac('sha256', $timestamp, $apiKey);

        $this->requestMock->expects($this->any())
        ->method('getHeader')
        ->willReturnCallback(function($headerName) use ($expectedSig) {
            if ($headerName === 'X-FeedSign') {
                return $expectedSig;
            } elseif ($headerName === 'User-Agent') {
                return $this->constUA;
            }
            return null;
        });

        $this->requestMock->expects($this->any())
        ->method('getParam')
        ->willReturnCallback(function($paramName) use ($currentPage) {
            if ($paramName === 'p') {
                return $currentPage;
            } elseif ($paramName === 's') {
                return $this->constMaxPageSize;
            }
            return null;
        });

        $product1 = $this->createProduct(1, 'Test Product', 'test-product', '10.99');

        $collectionMock = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $collectionMock->method('addAttributeToSelect')->willReturnSelf();
        $collectionMock->method('addAttributeToFilter')->willReturnSelf();
        $collectionMock->method('setPageSize')->willReturnSelf();
        $collectionMock->method('setCurPage')->willReturnSelf();
        $collectionMock->method('getSize')->willReturn(1);
        $collectionMock->method('count')->willReturn(1);
        $collectionMock->method('clear')->willReturn(null);
        $collectionMock->method('getIterator')->willReturn(new \ArrayIterator([$product1]));

        $this->collectionFactoryMock->method('create')->willReturn($collectionMock);

        $expectedResult = [
            "data" => [
                [
                    "id" => 1,
                    "title" => "Test Product",
                    "link" => "http://example.com/test-product",
                    "image_link" => "http://example.com/media/test-product.jpg",
                    "price" => "10.99",
                ],
            ],
            "meta" => [
                "total" => 1,
                "page" => 1,
                "next_page" => 0,
                "page_size" => $this->constMaxPageSize,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithProductsAndCustomPagination()
    {
        $apiKey = 'abc';
        $currentPage = 2;
        $pageSize = 3;

        $this->configHelperMock->method('isFeedEnabled')->willReturn(true);
        $this->configHelperMock->method('getApiKey')->willReturn($apiKey);

        $timestamp = date('YmdHi', time());
        $expectedSig = hash_hmac('sha256', $timestamp, $apiKey);

        $this->requestMock->expects($this->any())
        ->method('getHeader')
        ->willReturnCallback(function($headerName) use ($expectedSig) {
            if ($headerName === 'X-FeedSign') {
                return $expectedSig;
            } elseif ($headerName === 'User-Agent') {
                return $this->constUA;
            }
            return null;
        });

        $this->requestMock->expects($this->any())
        ->method('getParam')
        ->willReturnCallback(function($paramName) use ($currentPage, $pageSize) {
            if ($paramName === 'p') {
                return $currentPage;
            } elseif ($paramName === 's') {
                return $pageSize;
            }
            return null;
        });

        $product1 = $this->createProduct(1, 'P 1', 'p-1', '11.99');
        $product2 = $this->createProduct(2, 'P 2', 'p-2', '12.99');
        $product3 = $this->createProduct(3, 'P 3', 'p-3', '13.99');
        $product4 = $this->createProduct(4, 'P 4', 'p-4', '14.99');
        $product5 = $this->createProduct(5, 'P 5', 'p-5', '15.99');
        $product6 = $this->createProduct(6, 'P 6', 'p-6', '16.99');
        $product7 = $this->createProduct(7, 'P 7', 'p-7', '17.99');
        $product8 = $this->createProduct(8, 'P 8', 'p-8', '18.99');
        $product9 = $this->createProduct(9, 'P 9', 'p-9', '19.99');
        $product10 = $this->createProduct(10, 'P 10', 'p-10', '10.99');

        $collectionMock = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();
        $collectionMock->method('addAttributeToSelect')->willReturnSelf();
        $collectionMock->method('addAttributeToFilter')->willReturnSelf();
        $collectionMock->method('setPageSize')->willReturnSelf();
        $collectionMock->method('setCurPage')->willReturnSelf();

        $collectionMock->setPageSize(3);
        $collectionMock->setCurPage(1);

        $collectionMock->method('getPageSize')->willReturn($pageSize);
        $collectionMock->method('getSize')->willReturn(10);
        $collectionMock->method('count')->willReturn(7);
        $collectionMock->method('clear')->willReturn(null);

        $collectionMock->addItem($product1);
        $collectionMock->addItem($product2);
        $collectionMock->addItem($product3);
        $collectionMock->addItem($product4);
        $collectionMock->addItem($product5);
        $collectionMock->addItem($product6);
        $collectionMock->addItem($product7);
        $collectionMock->addItem($product8);
        $collectionMock->addItem($product9);
        $collectionMock->addItem($product10);

        $collectionMock->method('getIterator')->willReturn(new \ArrayIterator([
            // $product1,
            // $product2,
            // $product3,
            $product4,
            $product5,
            $product6,
            // $product7,
            // $product8,
            // $product9,
            // $product10
        ]));

        $collectionMock->getIterator();

        $collectionMock->method('getItems')->willReturnSelf();
        //$collectionMock->method('getIterator')->willReturn(new \ArrayIterator([$product1]));

        $this->collectionFactoryMock->method('create')->willReturn($collectionMock);

        $expectedResult = [
            "data" => [
                [
                    "id" => 4,
                    "title" => "P 4",
                    "link" => "http://example.com/p-4",
                    "image_link" => "http://example.com/media/p-4.jpg",
                    "price" => "14.99",
                ],
                [
                    "id" => 5,
                    "title" => "P 5",
                    "link" => "http://example.com/p-5",
                    "image_link" => "http://example.com/media/p-5.jpg",
                    "price" => "15.99",
                ],
                [
                    "id" => 6,
                    "title" => "P 6",
                    "link" => "http://example.com/p-6",
                    "image_link" => "http://example.com/media/p-6.jpg",
                    "price" => "16.99",
                ],
            ],
            "meta" => [
                "total" => 10,
                "page" => 2,
                "next_page" => 3,
                "page_size" => $pageSize,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    private function createProduct($id, $name, $slug, $price = '10')
    {
        $productMock = $this->getMockBuilder(\Magento\Catalog\Model\Product::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getId', 'getName', 'getProductUrl', 'getImage', 'getPrice', 'getMediaConfig'])
            ->getMock();
        $productMock->method('getId')->willReturn($id);
        $productMock->method('getName')->willReturn($name);
        $productMock->method('getProductUrl')->willReturn('http://example.com/' . $slug);
        $productMock->method('getImage')->willReturn($slug . '.jpg');
        $productMock->method('getPrice')->willReturn($price);

        $mediaConfigMock = $this->getMockBuilder(\Magento\Catalog\Model\Product\Media\Config::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getMediaUrl'])
            ->getMock();
        $mediaConfigMock->method('getMediaUrl')->with($slug . '.jpg')->willReturn(sprintf('http://example.com/media/%s.jpg', $slug));
        $productMock->method('getMediaConfig')->willReturn($mediaConfigMock);

        return $productMock;
    }
}
