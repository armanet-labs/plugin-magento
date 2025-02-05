<?php

namespace Armanet\Integration\Block\CheckoutSuccess;

use Magento\Framework\View\Element\Template;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Sales\Model\Order;

class Index extends Template
{
    protected $checkoutSession;
    protected $order;

    public function __construct(
        Template\Context $context,
        CheckoutSession $checkoutSession,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        parent::__construct($context, $data);
    }

    /**
     * Get last order
     *
     * @return Order
     */
    public function getOrder()
    {
        if (is_null($this->order)) {
            $this->order = $this->checkoutSession->getLastRealOrder();
        }
        return $this->order;
    }

    /**
     * Get order increment id
     *
     * @return string
     */
    public function getOrderId()
    {
        return $this->getOrder()->getIncrementId();
    }

    /**
     * Get order items
     *
     * @return Order\Item[]
     */
    public function getItems()
    {
        return $this->getOrder()->getAllVisibleItems();
    }

    /**
     * Get order total
     *
     * @return float
     */
    public function getOrderTotal()
    {
        return $this->getOrder()->getGrandTotal();
    }

    /**
     * Get shipping address
     *
     * @return Order\Address
     */
    public function getShippingAddress()
    {
        return $this->getOrder()->getShippingAddress();
    }
}
