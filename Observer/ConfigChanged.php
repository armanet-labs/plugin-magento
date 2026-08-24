<?php

namespace Armanet\Integration\Observer;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

class ConfigChanged implements ObserverInterface
{
    protected $cacheTypeList;

    public function __construct(TypeListInterface $cacheTypeList)
    {
        $this->cacheTypeList = $cacheTypeList;
    }

    public function execute(Observer $observer)
    {
        $this->cacheTypeList->cleanType('full_page');
        $this->cacheTypeList->cleanType('block_html');
    }
}
