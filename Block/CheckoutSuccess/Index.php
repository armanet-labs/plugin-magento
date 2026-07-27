<?php

namespace Armanet\Integration\Block\CheckoutSuccess;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Checkout\Model\Session\Proxy as CheckoutSessionProxy;
use Magento\Framework\View\Element\Template;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class Index extends Template
{
    protected $checkoutSession;
    protected $order;
    protected $orderCollectionFactory;
    protected $configHelper;

    public function __construct(
        Template\Context $context,
        CheckoutSessionProxy $checkoutSession,
        OrderCollectionFactory $orderCollectionFactory,
        ConfigHelper $configHelper,
        array $data = []
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->configHelper = $configHelper;
        parent::__construct($context, $data);
    }

    public function getOrder(): Order
    {
        if (is_null($this->order)) {
            $this->order = $this->checkoutSession->getLastRealOrder();
        }
        return $this->order;
    }

    public function getOrderId(): string
    {
        return $this->getOrder()->getIncrementId();
    }

    public function getItems(): array
    {
        return $this->getOrder()->getAllVisibleItems();
    }

    public function getOrderTotal(): float
    {
        return (float) $this->getOrder()->getGrandTotal();
    }

    public function getSubtotal(): float
    {
        return (float) $this->getOrder()->getSubtotal();
    }

    public function getCurrencyCode(): string
    {
        return $this->getOrder()->getOrderCurrencyCode();
    }

    public function getDiscountAmount(): float
    {
        return abs((float) $this->getOrder()->getDiscountAmount());
    }

    public function getTaxAmount(): float
    {
        return (float) $this->getOrder()->getTaxAmount();
    }

    public function getShippingAmount(): float
    {
        return (float) $this->getOrder()->getShippingAmount();
    }

    public function getCouponCode()
    {
        return $this->getOrder()->getCouponCode() ?: null;
    }

    public function getShippingAddress()
    {
        return $this->getOrder()->getShippingAddress();
    }

    public function getCustomerData(): array
    {
        $order = $this->getOrder();
        $billing = $order->getBillingAddress();
        $history = $this->getPriorOrderHistory();
        $priorCount = $history['count'];

        $daysSinceLastPurchase = null;
        if ($priorCount > 0 && $history['last_order_date'] !== null && $order->getCreatedAt()) {
            $orderDate = new \DateTime($order->getCreatedAt());
            $daysSinceLastPurchase = (int) $history['last_order_date']->diff($orderDate)->days;
        }

        $data = [
            'id' => (int) $order->getCustomerId(),
            'totalOrders' => $priorCount + 1,
            'country' => $billing ? $billing->getCountryId() : '',
            'city' => $billing ? $billing->getCity() : '',
            'state' => $billing ? $billing->getRegionCode() : '',
            'zip' => $billing ? $billing->getPostcode() : '',
            'daysSinceLastPurchase' => $daysSinceLastPurchase,
            'groups' => $this->configHelper->getGroupNames($order->getCustomerGroupId()),
        ];

        if ($priorCount === 0) {
            $data['isFirstPurchase'] = true;
        }

        return $data;
    }

    private function getPriorOrderHistory(): array
    {
        $order = $this->getOrder();
        $customerId = $order->getCustomerId();

        if (!$customerId) {
            return ['count' => 0, 'last_order_date' => null];
        }

        $collection = $this->orderCollectionFactory->create()
            ->addFieldToFilter('customer_id', $customerId)
            ->addFieldToFilter('entity_id', ['neq' => $order->getId()])
            ->addFieldToFilter('status', ['nin' => ['canceled']]);

        $count = $collection->getSize();

        if ($count === 0) {
            return ['count' => 0, 'last_order_date' => null];
        }

        $lastOrder = (clone $collection)
            ->setOrder('created_at', 'DESC')
            ->setPageSize(1)
            ->getFirstItem();

        $lastOrderDate = $lastOrder->getCreatedAt()
            ? new \DateTime($lastOrder->getCreatedAt())
            : null;

        return ['count' => $count, 'last_order_date' => $lastOrderDate];
    }
}
