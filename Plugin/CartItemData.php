<?php

namespace Armanet\Integration\Plugin;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Catalog\Model\ResourceModel\Product as ProductResource;
use Magento\Checkout\CustomerData\AbstractItem;
use Magento\Quote\Model\Quote\Item\AbstractItem as QuoteItem;

class CartItemData
{
    protected $configHelper;
    protected $productResource;

    public function __construct(ConfigHelper $configHelper, ProductResource $productResource)
    {
        $this->configHelper = $configHelper;
        $this->productResource = $productResource;
    }

    public function afterGetItemData(AbstractItem $subject, array $result, QuoteItem $item): array
    {
        $upcAttr = $this->configHelper->getUpcAttribute();
        $product = $item->getProduct();
        if (!$product) {
            $result['upc'] = '';
            return $result;
        }

        $upc = $this->productResource->getAttributeRawValue(
            $product->getId(),
            $upcAttr,
            $product->getStoreId()
        );
        $result['upc'] = is_string($upc) ? $upc : '';
        return $result;
    }
}
