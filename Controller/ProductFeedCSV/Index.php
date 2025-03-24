<?php

namespace Armanet\Integration\Controller\ProductFeedCSV;

use Armanet\Integration\Helper\Data;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;

class Index extends Action
{
    protected $resultRawFactory;
    protected $productCollectionFactory;
    protected $productRepository;
    protected const UA = 'Mozilla/5.0 (X11; Armanet x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';
    protected $configHelper;
    protected $fileFactory;

    public function __construct(
        Context $context,
        RawFactory $resultRawFactory,
        CollectionFactory $productCollectionFactory,
        ProductRepositoryInterface $productRepository,
        Data $configHelper,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->resultRawFactory = $resultRawFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->productRepository = $productRepository;
        $this->configHelper = $configHelper;
        $this->fileFactory = $fileFactory;
    }

    public function execute()
    {
        $request = $this->getRequest();

        if (!$this->configHelper->isFeedEnabled()) {
            $resultRaw = $this->resultRawFactory->create();
            $resultRaw->setHttpResponseCode(404);

            return $resultRaw;
        }

        // Validate request
        $feedSign = $request->getHeader('X-FeedSign');
        $userAgent = $request->getHeader('User-Agent');
        if ($feedSign !== '1' || $userAgent !== self::UA) {
            $resultRaw = $this->resultRawFactory->create();
            $resultRaw->setHttpResponseCode(404);

            return $resultRaw;
        }

        // Set CSV headers
        $fileName = 'product_feed.csv';

        // Create a temporary file for CSV output
        $varDir = $this->_objectManager->get(DirectoryList::class)->getPath(DirectoryList::VAR_DIR);
        $tmpDir = $varDir . '/tmp';
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }
        $cacheFile = $tmpDir . '/' . $fileName;

        // Check if cached file exists and is fresh (24 hours)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            // Return cached file using FileFactory
            return $this->fileFactory->create(
                $fileName,
                [
                    'type'  => 'filename',
                    'value' => 'tmp/' . $fileName,
                    'rm'    => false
                ],
                DirectoryList::VAR_DIR,
                'text/csv'
            );
        }

        $output = fopen($cacheFile, 'w');

        if (!$output) {
            $resultRaw = $this->resultRawFactory->create();
            $resultRaw->setContents('Error opening temporary file stream');
            return $resultRaw;
        }

        // Write CSV header row
        fputcsv($output, ['id', 'title', 'link', 'image_link', 'price']);
        fflush($output);

        $pageSize = 10000;
        $currentPage = 1;

        // Process products in pages to avoid memory issues
        while (true) {
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect(['name', 'price', 'sku', 'image', 'entity_id'])
                ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
                //->addAttributeToFilter('price', ['gt' => 500])
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

        // Return the file as a streamed response using FileFactory
        return $this->fileFactory->create(
            $fileName,
            [
                'type'  => 'filename',
                'value' => 'tmp/' . $fileName, // relative to VAR_DIR
                'rm'    => false
            ],
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
