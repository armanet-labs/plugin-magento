<?php

namespace Armanet\Integration\Controller\ProductFeedCSV;

use Armanet\Integration\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;

class Index extends Action
{
    protected $resultRawFactory;
    protected $productCollectionFactory;
    protected $productRepository;
    protected const UA = 'Mozilla/5.0 (X11; Armanet x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';
    protected const PAGE_SIZE = 10000;
    protected $configHelper;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        CollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        Data $configHelper
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->configHelper = $configHelper;
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

        // Set headers for the response
        $resultRaw->setHeader('Content-Type', 'text/csv', true);

        // Open a stream to write CSV content to the output buffer
        $output = fopen('php://output', 'w');
        if (!$output) {
            return $resultRaw;
        }

        fputcsv($output, ['id', 'title', 'link', 'image_link', 'price']);
        fflush($output);

        $currentPage = 1;

        // Process products in pages to avoid memory issues
        while (true) {
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect(['name', 'price', 'sku', 'image', 'entity_id'])
                ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
                ->setPageSize(self::PAGE_SIZE)
                ->setCurPage($currentPage);

            // Exit the loop if there are no products in the current page
            if ($collection->getSize() == 0) {
                break;
            }

            foreach ($collection as $product) {
                $row = [
                    $product->getId(),
                    $product->getName(),
                    $product->getProductUrl(),
                    $product->getMediaConfig()->getMediaUrl($product->getImage()),
                    $product->getPrice()
                ];
                fputcsv($output, $row);
            }

            // Flush the output to send data progressively
            fflush($output);

            // If this is the last page (fewer products than pageSize), break the loop
            if ($collection->count() < self::PAGE_SIZE) {
                break;
            }

            // Move to the next page and clear the current collection to free memory
            $currentPage++;
            $collection->clear();
        }

        // Close the output stream
        fclose($output);

        return $resultRaw;
    }
}
