<?php
namespace Armanet\Integration\Test\Unit\Controller\ProductFeed;

use Armanet\Integration\Controller\ProductFeed\Index;
use Armanet\Integration\Helper\Data;
use ArrayIterator;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Media\Config;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\HTTP\PhpEnvironment\Request;
use Magento\Framework\Pricing\Amount\AmountInterface;
use Magento\Framework\Pricing\PriceInfoInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    protected string $constUA;

    protected int $constMaxPageSize;

    protected string $defaultApiKey = 'abc';

    /** @var Context&\PHPUnit\Framework\MockObject\MockObject */
    protected $contextMock;

    /** @var RawFactory&\PHPUnit\Framework\MockObject\MockObject */
    protected $resultRawFactoryMock;

    /** @var CollectionFactory&\PHPUnit\Framework\MockObject\MockObject */
    protected $collectionFactoryMock;

    /** @var ProductRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject */
    protected $productRepositoryMock;

    /** @var Data&\PHPUnit\Framework\MockObject\MockObject */
    protected $configHelperMock;

    /** @var RequestInterface&\PHPUnit\Framework\MockObject\MockObject */
    protected $requestMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Raw */
    protected $rawResultMock;

    /** @var Index */
    protected $controller;

    /** @var ConfigurableResource */
    protected $configurableResource;

    /** @var StoreManagerInterface&\PHPUnit\Framework\MockObject\MockObject */
    protected $storeManagerMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Raw */
    protected $storeMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Raw */
    protected $connectionMock;

    /** @var ConfigurableType&\PHPUnit\Framework\MockObject\MockObject */
    protected $configurableTypeMock;

    /** @var ResourceConnection&\PHPUnit\Framework\MockObject\MockObject */
    protected $resourceMock;

    /** @var \PHPUnit\Framework\MockObject\MockObject|Raw */
    protected $collectionMock;

    protected function setUp(): void
    {
        $reflection = new \ReflectionClass(Index::class);
        $this->constUA = $reflection->getConstant('UA');
        $this->constMaxPageSize = $reflection->getConstant('MAX_PAGE_SIZE');

        $this->resultRawFactoryMock = $this->createMock(RawFactory::class);
        $this->collectionFactoryMock = $this->createMock(CollectionFactory::class);
        $this->productRepositoryMock = $this->createMock(ProductRepositoryInterface::class);
        $this->configHelperMock = $this->createMock(Data::class);
        $this->configHelperMock->method('isFeedEnabled')->willReturn(true);
        $this->configHelperMock->method('getApiKey')->willReturn($this->defaultApiKey);

        // Create a mock for the request and set it in the context
        $this->requestMock = $this->getMockBuilder(Request::class)->disableOriginalConstructor()->getMock();
        $this->contextMock = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->contextMock->method('getRequest')->willReturn($this->requestMock);

        // Create a mock for the Raw result and have resultRawFactory return it
        $this->rawResultMock = $this->getMockBuilder(Raw::class)->disableOriginalConstructor()->getMock();
        $this->resultRawFactoryMock->method('create')->willReturn($this->rawResultMock);

        $this->storeMock = $this->getMockBuilder(Store::class)->disableOriginalConstructor()->getMock();
        $this->storeMock->method('getId')->willReturn(1);

        $this->storeManagerMock = $this->getMockBuilder(StoreManagerInterface::class)->disableOriginalConstructor()->getMock();
        $this->storeManagerMock->method('getStore')->willReturn($this->storeMock);

        $this->configurableTypeMock = $this->getMockBuilder(ConfigurableType::class)->disableOriginalConstructor()->getMock();
        $this->configurableResource = $this->createMock(ConfigurableResource::class);

        $this->connectionMock = $this->createMock(AdapterInterface::class);
        $this->resourceMock = $this->getMockBuilder(ResourceConnection::class)->disableOriginalConstructor()->getMock();
        $this->resourceMock->method('getConnection')->willReturn($this->connectionMock);
        $this->resourceMock->method('getTableName')->with('catalog_product_index_price')->willReturn('catalog_product_index_price');

        $this->collectionMock = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $this->collectionMock->method('addAttributeToSelect')->willReturnSelf();
        $this->collectionMock->method('addUrlRewrite')->willReturnSelf();
        $this->collectionMock->method('setStoreId')->willReturnSelf();
        $this->collectionMock->method('addAttributeToFilter')->willReturnSelf();
        $this->collectionMock->method('setPageSize')->willReturnSelf();
        $this->collectionMock->method('setCurPage')->willReturnSelf();
        $this->collectionMock->method('clear')->willReturn(null);
        $this->collectionMock->method('getItems')->willReturnSelf();

        // Instantiate the controller with our mocks
        $this->controller = new Index(
            $this->contextMock,
            $this->resultRawFactoryMock,
            $this->collectionFactoryMock,
            $this->productRepositoryMock,
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->configurableTypeMock,
            $this->configurableResource,
            $this->resourceMock,
        );

        $refObj = new \ReflectionObject($this->controller);
        $prop = $refObj->getProperty('_objectManager');
        $prop->setAccessible(true);
    }

    public function testIsReturning404WhenFeedConfigurationIsDisabled()
    {
        $this->configHelperMock->method('isFeedEnabled')->willReturn(false);
        $this->rawResultMock->expects($this->once())->method('setHttpResponseCode')->with(404);

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsReturning404WhenFeedSignatureHeaderIsInvalid()
    {
        $this->mockGetHeaderRequest([
            'X-FeedSign' => hash_hmac('sha256', date('YmdHi', strtotime("-1 minutes")), $this->defaultApiKey), // Use a signature from 1 minute ago
        ]);

        $this->rawResultMock->expects($this->once())->method('setHttpResponseCode')->with(404);

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsReturning404WhenUserAgentHeaderIsInvalid()
    {
        $this->mockGetHeaderRequest([
            'User-Agent' => 'Invalid User Agent',
        ]);

        $this->rawResultMock->expects($this->once())->method('setHttpResponseCode')->with(404);

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithoutProducts()
    {
        $this->mockGetHeaderRequest();
        $this->mockRequestGetParam();

        $this->collectionMock->method('getSize')->willReturn(0);
        $this->collectionMock->method('count')->willReturn(0);
        $this->collectionMock->method('getIterator')->willReturn(new ArrayIterator([]));

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $expectedResult = [
            'data' => [],
            'meta' => [
                'total' => 0,
                'page' => 1,
                'next_page' => 0,
                'page_size' => $this->constMaxPageSize,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();

        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithProductsAndDefaultPagination()
    {
        $this->mockGetHeaderRequest();
        $this->mockRequestGetParam();

        $product1 = $this->createProduct(1, 'Test Product', 'test-product', '10.99');

        $this->collectionMock->method('getSize')->willReturn(1);
        $this->collectionMock->method('count')->willReturn(1);
        $this->collectionMock->method('getIterator')->willReturn(new ArrayIterator([$product1]));

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $expectedResult = [
            'data' => [
                $this->getProductExpectedPayload($product1),
            ],
            'meta' => [
                'total' => 1,
                'page' => 1,
                'next_page' => 0,
                'page_size' => $this->constMaxPageSize,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithProductsAndCustomPagination()
    {
        $this->mockGetHeaderRequest();
        $this->mockRequestGetParam([
            'p' => 2,
            's' => 3,
        ]);

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

        $product4->method('getImage')->willReturn(null);

        $this->collectionMock->method('getPageSize')->willReturn(3);
        $this->collectionMock->method('getSize')->willReturn(10);
        $this->collectionMock->method('count')->willReturn(7);
        $this->collectionMock->method('getIterator')->willReturn(new ArrayIterator([
            $product4,
            $product5,
            $product6
        ]));

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $expectedResult = [
            'data' => [
                $this->getProductExpectedPayload($product4),
                $this->getProductExpectedPayload($product5),
                $this->getProductExpectedPayload($product6),
            ],
            'meta' => [
                'total' => 10,
                'page' => 2,
                'next_page' => 3,
                'page_size' => 3,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithConfigurableProductWithFinalPrice()
    {
        $this->mockGetHeaderRequest();
        $this->mockRequestGetParam([
            'p' => 2,
            's' => 3
        ]);

        $product1 = $this->createProduct(1, 'P 1', 'p-1', '14.99');
        $product2 = $this->createProduct(2, 'P 2', 'p-2', '15.99');
        $product3 = $this->createProduct(3, 'P 3', 'p-3', '16.99');
        $product4 = $this->createProduct(4, 'P 4', 'p-4', '0.0', 'configurable', 11, 13); // with min and max

        $this->collectionMock->method('getPageSize')->willReturn(3);
        $this->collectionMock->method('getSize')->willReturn(10);
        $this->collectionMock->method('count')->willReturn(7);
        $this->collectionMock->method('getIterator')->willReturn(new ArrayIterator([
            $product1,
            $product2,
            $product3,
            $product4,
        ]));

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $expectedResult = [
            'data' => [
                $this->getProductExpectedPayload($product1),
                $this->getProductExpectedPayload($product2),
                $this->getProductExpectedPayload($product3),
                $this->getProductExpectedPayload($product4, 11, 13),
            ],
            'meta' => [
                'total' => 10,
                'page' => 2,
                'next_page' => 3,
                'page_size' => 3,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithConfigurableProductWithDbSearch()
    {
        $this->mockGetHeaderRequest();
        $this->mockRequestGetParam([
            'p' => 2,
            's' => 3
        ]);

        $product1 = $this->createProduct(1, 'P 1', 'p-1', '16.99');
        $product2 = $this->createProduct(2, 'P 2', 'p-2', '0.0', 'configurable', 11);

        $this->collectionMock->method('getPageSize')->willReturn(3);
        $this->collectionMock->method('getSize')->willReturn(10);
        $this->collectionMock->method('count')->willReturn(7);
        $this->collectionMock->method('getIterator')->willReturn(new ArrayIterator([
            $product1,
            $product2,
        ]));

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $this->connectionMock->method('fetchRow')->willReturn(['min_price' => 10, 'max_price' => 13]);

        $expectedResult = [
            'data' => [
                $this->getProductExpectedPayload($product1),
                $this->getProductExpectedPayload($product2, 10, 13),
            ],
            'meta' => [
                'total' => 10,
                'page' => 2,
                'next_page' => 3,
                'page_size' => 3,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithConfigurableProductWithChildCollectionWithDifferentMinAndMaxPrices()
    {
        $this->mockGetHeaderRequest();
        $this->mockRequestGetParam([
            'p' => 2,
            's' => 3
        ]);

        $product1 = $this->createProduct(1, 'P 1', 'p-1', '16.99');
        $product2 = $this->createProduct(2, 'P 2', 'p-2', '0.0', 'configurable', 11);

        $this->collectionMock->method('getPageSize')->willReturn(3);
        $this->collectionMock->method('getSize')->willReturn(10);
        $this->collectionMock->method('count')->willReturn(7);
        $this->collectionMock->method('getIterator')->willReturn(new ArrayIterator([
            $product1,
            $product2,
        ]));

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $this->connectionMock->method('fetchRow')->willReturn(['min_price' => 10]);

        $childCollectionMock = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $childCollectionMock->method('addAttributeToSelect')->willReturnSelf();
        $childCollectionMock->method('addAttributeToFilter')->willReturnSelf();
        $childCollectionMock->method('setStoreId')->willReturnSelf();
        $childCollectionMock->method('setPageSize')->willReturnSelf();
        $childCollectionMock->method('setOrder')->willReturnSelf();
        $childCollectionMock->method('getSize')->willReturn(2);

        $minChild1 = $this->createProduct(101, 'Min Child', 'child-min', '10.00');
        $maxChild1 = $this->createProduct(102, 'Max Child', 'child-max', '13.00');

        $childCollectionMock->method('getFirstItem')->willReturnOnConsecutiveCalls($minChild1, $maxChild1);

        $this->configurableTypeMock->method('getUsedProductCollection')->with($product2)->willReturn($childCollectionMock);

        $expectedResult = [
            'data' => [
                $this->getProductExpectedPayload($product1),
                $this->getProductExpectedPayload($product2, "10.00", "13.00"),
            ],
            'meta' => [
                'total' => 10,
                'page' => 2,
                'next_page' => 3,
                'page_size' => 3,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    public function testIsExecutingWithConfigurableProductWithChildCollectionWithSameMinAndMaxPrices()
    {
        $this->mockGetHeaderRequest();
        $this->mockRequestGetParam([
            'p' => 2,
            's' => 3
        ]);

        $product1 = $this->createProduct(1, 'P 1', 'p-1', '16.99');
        $product2 = $this->createProduct(2, 'P 2', 'p-2', '0.0', 'configurable', 11);

        $this->collectionMock->method('getPageSize')->willReturn(3);
        $this->collectionMock->method('getSize')->willReturn(10);
        $this->collectionMock->method('count')->willReturn(7);
        $this->collectionMock->method('getIterator')->willReturn(new ArrayIterator([
            $product1,
            $product2,
        ]));

        $this->collectionFactoryMock->method('create')->willReturn($this->collectionMock);

        $this->connectionMock->method('fetchRow')->willReturn(['min_price' => 10]);

        $childCollectionMock = $this->getMockBuilder(Collection::class)->disableOriginalConstructor()->getMock();
        $childCollectionMock->method('addAttributeToSelect')->willReturnSelf();
        $childCollectionMock->method('addAttributeToFilter')->willReturnSelf();
        $childCollectionMock->method('setStoreId')->willReturnSelf();
        $childCollectionMock->method('setPageSize')->willReturnSelf();
        $childCollectionMock->method('setOrder')->willReturnSelf();
        $childCollectionMock->method('getSize')->willReturn(2);

        $minChild1 = $this->createProduct(101, 'Min Child', 'child-min', '13.00');
        $maxChild1 = $this->createProduct(102, 'Max Child', 'child-max', '13.00');

        $childCollectionMock->method('getFirstItem')->willReturnOnConsecutiveCalls($minChild1, $maxChild1);

        $this->configurableTypeMock->method('getUsedProductCollection')->with($product2)->willReturn($childCollectionMock);

        $expectedResult = [
            'data' => [
                $this->getProductExpectedPayload($product1),
                $this->getProductExpectedPayload($product2, "13.00", "13.00"),
            ],
            'meta' => [
                'total' => 10,
                'page' => 2,
                'next_page' => 3,
                'page_size' => 3,
            ],
        ];

        $this->rawResultMock->expects($this->once())->method('setContents')->with(json_encode($expectedResult));

        $result = $this->controller->execute();
        $this->assertSame($this->rawResultMock, $result);
    }

    private function createProduct($id, $name, $slug, $price = '10', $type = 'simple', $minPrice = null, $maxPrice = null)
    {
        $productMock = $this->getMockBuilder(Product::class)->disableOriginalConstructor()->getMock();
        $priceInfoMock = $this->getMockBuilder(PriceInfoInterface::class)->getMock();
        $finalPriceMock = $this->getMockBuilder(FinalPrice::class)->disableOriginalConstructor()->onlyMethods(['getMinimalPrice','getMaximalPrice'])->getMock();

        $minAmountMock = $this->getMockBuilder(AmountInterface::class)->getMock();
        $maxAmountMock = $this->getMockBuilder(AmountInterface::class)->getMock();
        $minAmountMock->method('getValue')->willReturn($minPrice);
        $maxAmountMock->method('getValue')->willReturn($maxPrice);

        $finalPriceMock->method('getMinimalPrice')->willReturn($minAmountMock);
        $finalPriceMock->method('getMaximalPrice')->willReturn($maxAmountMock);

        $priceInfoMock->method('getPrice')->with(FinalPrice::PRICE_CODE)->willReturn($finalPriceMock);

        $productMock->method('getPriceInfo')->willReturn($priceInfoMock);
        $productMock->method('getId')->willReturn($id);
        $productMock->method('setStoreId')->willReturnSelf();
        $productMock->method('getName')->willReturn($name);
        $productMock->method('getProductUrl')->willReturn('http://example.com/' . $slug);
        $productMock->method('getImage')->willReturn($slug . '.jpg');
        $productMock->method('getPrice')->willReturn($price);
        $productMock->method('getTypeId')->willReturn($type);

        $productMock->method('__call')->willReturnCallback(function ($name, $args) use ($slug) {
            if ($name === 'getUrlKey') {
                return $slug;
            }
            return null;
        });

        $mediaConfigMock = $this->getMockBuilder(Config::class)->disableOriginalConstructor()->onlyMethods(['getMediaUrl'])->getMock();
        $mediaConfigMock->method('getMediaUrl')->with($slug . '.jpg')->willReturn(sprintf('http://example.com/media/%s.jpg', $slug));
        $productMock->method('getMediaConfig')->willReturn($mediaConfigMock);

        return $productMock;
    }

    private function mockGetHeaderRequest(array $overrides = []): void
    {
        $defaults = [
            'User-Agent' => $this->constUA,
            'X-FeedSign' => hash_hmac('sha256', date('YmdHi', time()), $this->defaultApiKey),
        ];

        $headers = array_change_key_case(array_merge($defaults, $overrides), CASE_LOWER);

        $this->requestMock->expects($this->any())->method('getHeader')
            ->willReturnCallback(function ($name, $default = null) use ($headers) {
                $key = strtolower((string)$name);
                if (array_key_exists($key, $headers)) {
                    return $headers[$key];
                }
                return func_num_args() >= 2 ? $default : null;
            });
    }

    private function mockRequestGetParam(array $overrides = []): void
    {
        $defaults = [
            'p' => 1,
            's' => $this->constMaxPageSize,
            'w' => 1
        ];

        $headers = array_change_key_case(array_merge($defaults, $overrides), CASE_LOWER);

        $this->requestMock->expects($this->any())->method('getParam')
            ->willReturnCallback(function ($name, $default = null) use ($headers) {
                $key = strtolower((string)$name);
                if (array_key_exists($key, $headers)) {
                    return $headers[$key];
                }
                return func_num_args() >= 2 ? $default : null;
            });
    }

    private function getProductExpectedPayload($product, $minPrice = null, $maxPrice = null)
    {
        $payload = [
            'id' => $product->getId(),
            'title' => $product->getName(),
            'link' => $product->getProductUrl(),
            'image_link' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
            'link_key' => $product->getUrlKey(),
            'type' => $product->getTypeId(),
            'price' => $product->getPrice(),
        ];

        if ($minPrice && $maxPrice) {
            if ((float) $minPrice === (float) $maxPrice) {
                $payload['price'] = $minPrice;
            }
            $payload['min_price'] = $minPrice;
            $payload['max_price'] = $maxPrice;
        }

        return $payload;
    }
}
