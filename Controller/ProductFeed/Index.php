<?php

namespace Armanet\Integration\Controller\ProductFeed;

use Armanet\Integration\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Pricing\Price\FinalPrice;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable as ConfigurableResource;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;

class Index extends Action
{
    /**
     * @var RawFactory
     */
    protected $resultRawFactory;

    /**
     * @var CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var ProductRepositoryInterface
     */
    protected $productRepository;

    /**
     * User agent string for HTTP requests
     */
    protected const UA = 'Mozilla/5.0 (X11; Armanet x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';

    /**
     * Maximum number of products per feed page
     */
    protected const MAX_PAGE_SIZE = 10000;

    /**
     * @var Data
     */
    protected $configHelper;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var ConfigurableType
     */
    protected $configurableType;

    /**
     * @var ConfigurableResource
     */
    protected $configurableResource;

    /**
     * @var ResourceConnection
     */
    protected $resource;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        CollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        Data $configHelper,
        StoreManagerInterface $storeManager,
        ConfigurableType $configurableType,
        ConfigurableResource $configurableResource,
        ResourceConnection $resource
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->configurableType = $configurableType;
        $this->configurableResource = $configurableResource;
        $this->resource = $resource;
    }

    public function execute()
    {
        $request = $this->getRequest();

        // Create a Raw response object for JSON output
        $resultRaw = $this->resultRawFactory->create();

        if (!$this->configHelper->isFeedEnabled() || !$this->configHelper->getApiKey()) {
            $resultRaw->setHttpResponseCode(404);
            return $resultRaw;
        }

        if (!$this->allowAccess($request->getHeader('User-Agent'), $request->getHeader('X-FeedSign'))) {
            $resultRaw->setHttpResponseCode(404);
            return $resultRaw;
        }

        $currentPage = $request->getParam('p', 1);
        $pageSize = $request->getParam('s', self::MAX_PAGE_SIZE);
        $pageSize = min($pageSize, self::MAX_PAGE_SIZE);
        $storeId = (int)$this->storeManager->getStore()->getId();

        // Process products in pages to avoid memory issues
        $collection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect(['name', 'price', 'sku', 'image', 'entity_id', 'url_key', 'upc'])
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->addAttributeToFilter('image', ['notnull' => true])
            ->addAttributeToFilter('image', ['neq' => 'no_selection'])
            ->addAttributeToFilter('visibility', [
                'in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH]
            ])
            ->addUrlRewrite()
            ->setPageSize($pageSize)
            ->setCurPage($currentPage);

        // Create pager metadata
        $total = (int) $collection->getSize();
        $pager = [
            'total' => $total,
            'page' => $currentPage,
            'next_page' => $currentPage < ((int) ceil($total / $pageSize)) ? $currentPage + 1 : 0,
            'page_size' => $pageSize,
        ];

        $rows = [];
        foreach ($collection as $product) {
            $productTypeId = $product->getTypeId();
            $productId = $product->getId();

            // Skip simple products that are children of a configurable
            if ($productTypeId === Type::TYPE_SIMPLE && !empty($this->configurableResource->getParentIdsByChild($productId))) {
                continue;
            }

            $currentRow = [
                'id' => $productId,
                'title' => $product->getName(),
                'link' => $product->getProductUrl(),
                'image_link' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
                'link_key' => $product->getUrlKey(),
                'type' => $productTypeId,
                'price' => $product->getPrice(),
                'upc' => $product->getUpc(),
                'sku' => $product->getSku(),
            ];

            // Product type is configurable and has no price
            if ($productTypeId === ConfigurableType::TYPE_CODE && (float) $product->getPrice() === 0.0) {
                [$minPrice, $maxPrice] = $this->resolveMinAndMaxPrices($product, $storeId);

                if (!$minPrice || !$maxPrice) {
                    $rows[] = $currentRow;
                    continue;
                }

                if ((float) $minPrice === (float) $maxPrice) {
                    $currentRow['price'] = $minPrice;
                }

                $currentRow['min_price'] = $minPrice;
                $currentRow['max_price'] = $maxPrice;
            }

            $rows[] = $currentRow;
        }

        // Build response with pager metadata
        $resultRaw->setContents(json_encode([
            'data' => $rows,
            'meta' => $pager,
        ]));

        $resultRaw->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
        return $resultRaw;
    }

    private function resolveMinAndMaxPrices($product, int $storeId): array
    {
        $product->setStoreId($storeId);

        // First, try to get the price from the index (FinalPrice)
        $finalPrice = $product->getPriceInfo()->getPrice(FinalPrice::PRICE_CODE);
        $minPrice = $finalPrice->getMinimalPrice()->getValue();
        $maxPrice = $finalPrice->getMaximalPrice()->getValue();

        // If the index doesn't return a max price, try to get it from the DB
        if (!$maxPrice || (float) $minPrice === (float) $maxPrice) {
            $conn = $this->resource->getConnection();
            $table = $this->resource->getTableName('catalog_product_index_price');
            $row = $conn->fetchRow(
                "SELECT min_price, max_price
                FROM {$table}
                WHERE entity_id = :id
                    AND customer_group_id = 0
                    AND (COALESCE(min_price,0) > 0 AND COALESCE(max_price,0) > 0)
                LIMIT 1",
                [
                    'id' => (int)$product->getId(),
                ]
            );

            if ($row) {
                $minPrice = $row['min_price'] ?? null;
                $maxPrice = $row['max_price'] ?? null;
            }
        }

        // If no valid max price, try to get it from the child collection (filtered)
        if (!$maxPrice || (float) $maxPrice === (float) $minPrice) {
            $childCollection = $this->configurableType
                ->getUsedProductCollection($product)
                ->addAttributeToSelect('price')
                ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
                ->addAttributeToFilter('price', ['gt' => 0]) // Only child products with price > 0
                ->setStoreId($storeId);

            if ($childCollection->getSize()) {
                $minPrice = (clone $childCollection)->setOrder('price', 'ASC')->setPageSize(1)->getFirstItem()->getPrice();
                $maxPrice = (clone $childCollection)->setOrder('price', 'DESC')->setPageSize(1)->getFirstItem()->getPrice();
            }
        }

        return [$minPrice, $maxPrice];
    }

    private function allowAccess($userAgent, $feedSign)
    {
        if ($userAgent !== self::UA) {
            return false;
        }

        $timestamp = date('YmdHi', time());
        return hash_equals(hash_hmac('sha256', $timestamp, $this->configHelper->getApiKey()), $feedSign);
    }
}
