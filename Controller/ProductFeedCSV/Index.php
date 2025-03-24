<?php

namespace Armanet\Integration\Controller\ProductFeedCSV;

use Armanet\Integration\Helper\Data;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;

class Index extends Action
{
    protected $resultRawFactory;
    protected $productCollectionFactory;
    protected $productRepository;
    protected const UA = 'Mozilla/5.0 (X11; Armanet x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';
    protected $configHelper;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        CollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        Data $configHelper,
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
        $resultRaw = $this->resultRawFactory->create();
        $isFeedEnabled = $this->configHelper->isFeedEnabled();

        if (!$isFeedEnabled) {
            $resultRaw->setHttpResponseCode(404);

            return $resultRaw;
        }

        // Validate request
        $feedSign = $request->getHeader('X-FeedSign');
        $userAgent = $request->getHeader('User-Agent');
        if ($feedSign !== '1' || $userAgent !== self::UA) {
            $resultRaw->setHttpResponseCode(404);

            return $resultRaw;
        }

        $resultRaw->setHeader('Content-Type', 'text/csv', true);
        $resultRaw->setHeader('Content-Disposition', 'attachment; filename="product_feed.csv"', true);

        // Start output buffering to capture CSV data
        ob_start();

        // Open a stream to write CSV content to the output buffer
        $output = fopen('php://output', 'w');
        if (!$output) {
            return $resultRaw;
        }

        fputcsv($output, ['id', 'title', 'link', 'image_link', 'price']);
        fflush($output);

        $pageSize = 10000;
        $currentPage = 1;

        while (true) {
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect(['name', 'price', 'sku', 'image', 'entity_id'])
                ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
                ->setPageSize($pageSize)
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
            if ($collection->count() < $pageSize) {
                break;
            }

            $currentPage++;
            // Clear the current collection to free memory
            $collection->clear();
        }

        fclose($output);

        // Capture the entire CSV content from the output buffer
        $csvContent = ob_get_clean();
        $resultRaw->setContents($csvContent);
        return $resultRaw;
    }
}
