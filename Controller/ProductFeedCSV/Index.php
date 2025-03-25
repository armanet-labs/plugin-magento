<?php

namespace Armanet\Integration\Controller\ProductFeedCSV;

use Armanet\Integration\Helper\Data;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\RawFactory;

class Index extends Action
{
    protected $resultRawFactory;
    protected $productCollectionFactory;
    protected $productRepository;
    protected const UA = 'Mozilla/5.0 (X11; Armanet x86_64; rv:109.0) Gecko/20100101 Firefox/115.0';
    protected const CACHE_FEED_EXPIRATION_HOURS = 1;
    protected const CACHE_FEED_FOLDER = 'tmp';
    protected const PAGE_SIZE = 10000;
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

        $fileName = 'product_feed.csv';

        $varDir = $this->_objectManager->get(DirectoryList::class)->getPath(DirectoryList::VAR_DIR);
        $tmpDir = sprintf('%s/%s', $varDir, self::CACHE_FEED_FOLDER);
        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $cacheFile = sprintf('%s/%s', $tmpDir, $fileName);

        // Check if cached file exists and is fresh (1 hours)
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < (self::CACHE_FEED_EXPIRATION_HOURS * 3600)) {
            // Return cached file using FileFactory
            return $this->fileFactory->create(
                $fileName,
                [
                    'type' => 'filename',
                    'value' => sprintf('%s/%s', self::CACHE_FEED_FOLDER, $fileName),
                    'rm' => false
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

        $currentPage = 1;

        while (true) {
            $collection = $this->productCollectionFactory->create()
                ->addAttributeToSelect(['name', 'price', 'sku', 'image', 'entity_id'])
                ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
                ->addAttributeToFilter('price', ['gt' => 500])
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

            $currentPage++;
            $collection->clear();
        }

        fclose($output);

        // Return the file as a streamed response using FileFactory
        return $this->fileFactory->create(
            $fileName,
            [
                'type' => 'filename',
                'value' => sprintf('%s/%s', self::CACHE_FEED_FOLDER, $fileName),
                'rm' => false,
            ],
            DirectoryList::VAR_DIR,
            'text/csv'
        );
    }
}
