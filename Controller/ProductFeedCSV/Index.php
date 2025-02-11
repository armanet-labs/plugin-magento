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

        $offset = (int) $request->getParam('offset', 0);
        $pageSize = 300;

        $page = ($offset / $pageSize) + 1;

        $collection = $this->productCollectionFactory->create()
            ->addAttributeToSelect(['name', 'price', 'sku', 'image', "entity_id",])
            ->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED])
            ->setPageSize($pageSize)
            ->setCurPage($page);

        $csvContent = [];
        $csvContent[] = ['id', 'title', 'link', 'image_link', 'price'];

        foreach ($collection as $product) {
            $csvContent[] = [
                $product->getId(),
                $product->getName(),
                $product->getProductUrl(),
                $product->getMediaConfig()->getMediaUrl($product->getImage()),
                $product->getPrice()
            ];
        }

        $outputBuffer = fopen('php://temp', 'w');

        foreach ($csvContent as $row) {
            fputcsv($outputBuffer, $row);
        }

        rewind($outputBuffer);

        $csvData = stream_get_contents($outputBuffer);

        fclose($outputBuffer);

        $resultRaw->setContents($csvData);
        $resultRaw->setHeader('Content-Type', 'text/csv', true);
        $resultRaw->setHeader('Content-Disposition', 'attachment; filename="product_feed.csv"', true);

        return $resultRaw;
    }
}
