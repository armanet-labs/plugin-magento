<?php

namespace Armanet\Integration\CustomerData;

use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Customer\CustomerData\SectionSourceInterface;
use Magento\Customer\Model\Session\Proxy as CustomerSessionProxy;

class Customer implements SectionSourceInterface
{
    protected $customerSession;
    protected $configHelper;

    public function __construct(
        CustomerSessionProxy $customerSession,
        ConfigHelper $configHelper
    ) {
        $this->customerSession = $customerSession;
        $this->configHelper = $configHelper;
    }

    public function getSectionData(): array
    {
        $billingAddressId = $this->customerSession->isLoggedIn()
            ? $this->customerSession->getCustomer()->getDefaultBilling()
            : null;
        $address = $this->configHelper->getBillingAddress($billingAddressId);
        $region = $address ? $address->getRegion() : null;

        return [
            'id' => (int) $this->customerSession->getCustomerId(),
            'country' => $address ? (string) $address->getCountryId() : '',
            'city' => $address ? (string) $address->getCity() : '',
            'state' => $region ? (string) $region->getRegionCode() : '',
            'postcode' => $address ? (string) $address->getPostcode() : '',
            'groups' => $this->configHelper->getGroupNames($this->customerSession->getCustomerGroupId()),
        ];
    }
}
