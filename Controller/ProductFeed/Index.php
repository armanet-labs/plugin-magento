<?php

namespace Armanet\Integration\Controller\ProductFeed;

use Armanet\Integration\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
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

    /**
     * @var CategoryCollectionFactory
     */
    protected $categoryCollectionFactory;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        CollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        Data $configHelper,
        StoreManagerInterface $storeManager,
        ConfigurableType $configurableType,
        ConfigurableResource $configurableResource,
        ResourceConnection $resource,
        CategoryCollectionFactory $categoryCollectionFactory
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
        $this->categoryCollectionFactory = $categoryCollectionFactory;
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
        $upcAttr = $this->configHelper->getUpcAttribute();

        // Process products in pages to avoid memory issues
        $collection = $this->productCollectionFactory->create()
            ->setStoreId($storeId)
            ->addAttributeToSelect([
                'name', 'price', 'special_price', 'special_from_date', 'special_to_date',
                'short_description', 'weight', 'sku', 'image', 'entity_id', 'url_key', $upcAttr,
            ])
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

        // Join stock table to add qty to each product row
        $stockTable = $this->resource->getTableName('cataloginventory_stock_item');
        $collection->getSelect()->joinLeft(
            ['stock_item' => $stockTable],
            'e.entity_id = stock_item.product_id AND stock_item.stock_id = 1',
            ['stock_qty' => 'COALESCE(stock_item.qty, 0)']
        );

        // Load collection, then pre-fetch category names for all products on this page in 2 queries
        $collection->load();
        $productIds = array_map('intval', array_keys($collection->getItems()));
        $categoryMap = $this->loadCategoryNames($productIds);

        $rows = [];
        foreach ($collection->getItems() as $product) {
            $productTypeId = $product->getTypeId();
            $productId = $product->getId();

            // Skip simple products that are children of a configurable
            if ($productTypeId === Type::TYPE_SIMPLE && !empty($this->configurableResource->getParentIdsByChild($productId))) {
                continue;
            }

            $currentRow = $this->buildFeedRow($product, $upcAttr, $categoryMap[$productId] ?? [], false, '');

            // Product type is configurable and has no price
            if ($productTypeId === ConfigurableType::TYPE_CODE && (float) $product->getPrice() === 0.0) {
                [$minPrice, $maxPrice] = $this->resolveMinAndMaxPrices($product, $storeId);

                if ($minPrice && $maxPrice) {
                    $currentRow['price'] = $minPrice;
                    $currentRow['regular_price'] = $minPrice;
                    $currentRow['min_price'] = $minPrice;
                    $currentRow['max_price'] = $maxPrice;
                }
            }

            $rows[] = $currentRow;

            if ($productTypeId === ConfigurableType::TYPE_CODE) {
                foreach ($this->getVariationRows($product, $productId, $storeId, $upcAttr, $categoryMap[$productId] ?? []) as $variationRow) {
                    $rows[] = $variationRow;
                }
            }
        }

        // Build response with pager metadata
        $resultRaw->setContents(json_encode([
            'data' => $rows,
            'meta' => $pager,
        ]));

        $resultRaw->setHeader('Content-Type', 'application/json; charset=UTF-8', true);
        return $resultRaw;
    }

    private function buildFeedRow($product, string $upcAttr, array $categoryNames, bool $isVariation, $parentId): array
    {
        $isOnSale = $this->isProductOnSale($product);

        return [
            'id'             => $product->getId(),
            'title'          => $product->getName(),
            'link'           => $product->getProductUrl(),
            'image_link'     => $product->getMediaConfig()->getMediaUrl($product->getImage()),
            'link_key'       => $product->getUrlKey(),
            'type'           => $product->getTypeId(),
            'price'          => $product->getPrice(),
            'upc'            => $product->getData($upcAttr),
            'sku'            => $product->getSku(),
            'availability'   => 'in_stock',
            'regular_price'  => $product->getPrice(),
            'sale_price'     => $isOnSale ? $product->getSpecialPrice() : '',
            'is_on_sale'     => $isOnSale ? 1 : 0,
            'stock_quantity' => (int) $product->getData('stock_qty'),
            'description'    => $product->getShortDescription(),
            'categories'     => implode('|', $categoryNames),
            'tags'           => '',
            'weight'         => $product->getWeight() ?? '',
            'is_variation'   => $isVariation ? 1 : 0,
            'parent_id'      => $parentId,
        ];
    }

    private function isProductOnSale($product): bool
    {
        $specialPrice = $product->getSpecialPrice();
        if (empty($specialPrice)) {
            return false;
        }
        $today = date('Y-m-d');
        $fromDate = $product->getSpecialFromDate();
        $toDate = $product->getSpecialToDate();
        if ($fromDate && substr($fromDate, 0, 10) > $today) {
            return false;
        }
        if ($toDate && substr($toDate, 0, 10) < $today) {
            return false;
        }
        return true;
    }

    private function loadCategoryNames(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $conn = $this->resource->getConnection();
        $productCatTable = $this->resource->getTableName('catalog_category_product');

        $rows = $conn->fetchAll(
            $conn->select()
                ->from($productCatTable, ['product_id', 'category_id'])
                ->where('product_id IN (?)', $productIds)
        );

        if (empty($rows)) {
            return [];
        }

        $allCategoryIds = array_unique(array_column($rows, 'category_id'));

        $catCollection = $this->categoryCollectionFactory->create()
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('entity_id', ['in' => $allCategoryIds]);

        $categoryNames = [];
        foreach ($catCollection as $cat) {
            $categoryNames[$cat->getId()] = $cat->getName();
        }

        $map = [];
        foreach ($rows as $row) {
            $name = $categoryNames[$row['category_id']] ?? null;
            if ($name !== null) {
                $map[$row['product_id']][] = $name;
            }
        }

        return $map;
    }

    private function getVariationRows($product, $parentId, int $storeId, string $upcAttr, array $parentCategoryNames): array
    {
        $childCollection = $this->configurableType->getUsedProductCollection($product)
            ->addAttributeToSelect([
                'name', 'price', 'special_price', 'special_from_date', 'special_to_date',
                'short_description', 'weight', 'sku', 'image', 'entity_id', 'url_key', $upcAttr,
            ])
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->addAttributeToFilter('image', ['notnull' => true])
            ->addAttributeToFilter('image', ['neq' => 'no_selection'])
            ->setStoreId($storeId);

        $stockTable = $this->resource->getTableName('cataloginventory_stock_item');
        $childCollection->getSelect()->joinLeft(
            ['stock_item' => $stockTable],
            'e.entity_id = stock_item.product_id AND stock_item.stock_id = 1',
            ['stock_qty' => 'COALESCE(stock_item.qty, 0)', 'is_in_stock' => 'stock_item.is_in_stock']
        );

        $rows = [];
        foreach ($childCollection->getItems() as $child) {
            if ((int) $child->getData('is_in_stock') !== 1) {
                continue;
            }

            $rows[] = $this->buildFeedRow($child, $upcAttr, $parentCategoryNames, true, $parentId);
        }

        return $rows;
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
