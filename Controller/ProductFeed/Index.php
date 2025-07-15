<?php

namespace Armanet\Integration\Controller\ProductFeed;

use Armanet\Integration\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
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

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        CollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        Data $configHelper,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
    }

    public function execute()
    {
        $request = $this->getRequest();

        // Create a Raw response object for CSV output
        $resultRaw = $this->resultRawFactory->create();
        $apiKey = $this->configHelper->getApiKey();

        if (!$apiKey || !$this->configHelper->isFeedEnabled()) {
            $resultRaw->setHttpResponseCode(404);

            return $resultRaw;
        }

        $timestamp = date('YmdHi', time());
        $apiKey = $this->configHelper->getApiKey();
        $expectedSig = hash_hmac('sha256', $timestamp, $apiKey);

        $feedSign = $request->getHeader('X-FeedSign');
        $userAgent = $request->getHeader('User-Agent');
        if ($feedSign !== $expectedSig || $userAgent !== self::UA) {
            $resultRaw->setHttpResponseCode(404);
            return $resultRaw;
        }

        $currentPage = $request->getParam('p', 1);
        $pageSize = $request->getParam('s', self::MAX_PAGE_SIZE);
        $pageSize = min($pageSize, self::MAX_PAGE_SIZE);
        $storeId = $this->storeManager->getStore()->getId();

        // Process products in pages to avoid memory issues
        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'price', 'sku', 'image', 'entity_id', 'url_key'])
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->addUrlRewrite()
            ->setStoreId($storeId)
            ->setPageSize($pageSize)
            ->setCurPage($currentPage);

        // Create pager metadata
        $pager = [
            'total' => $collection->getSize(),
            'page' => $currentPage,
            'next_page' => $currentPage + 1,
            'page_size' => $pageSize,
        ];

        // If we have less results than the page size, means last page
        if ($collection->count() < $pageSize) {
            $pager['next_page'] = 0;
        }

        $rows = [];
        foreach ($collection as $product) {
            $rows[] = [
                'id' => $product->getId(),
                'title' => $product->getName(),
                'link' => $product->getUrlInStore(),
                'product_link' => $product->getProductUrl(),
                'image_link' => $product->getMediaConfig()->getMediaUrl($product->getImage()),
                'link_key' => $product->getUrlKey(),
                'price' => $product->getPrice(),
            ];
        }

        // Build response with pager metadata
        $resultRaw->setContents(json_encode([
            'data' => $rows,
            'meta' => $pager,
        ]));

        return $resultRaw;
    }
}
