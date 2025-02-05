<?php

namespace Armanet\Integration\Block\Adminhtml;

use Magento\Backend\Block\Widget\Container;
use Magento\Backend\Block\Widget\Context;

class Index extends Container
{
    public function __construct(Context $context, array $data = [])
    {
        parent::__construct($context, $data);
    }
}
