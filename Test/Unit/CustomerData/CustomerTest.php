<?php

namespace Armanet\Integration\Test\Unit\CustomerData;

use Armanet\Integration\CustomerData\Customer;
use Armanet\Integration\Helper\Data as ConfigHelper;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\RegionInterface;
use Magento\Customer\Model\Customer as CustomerModel;
use Magento\Customer\Model\Session\Proxy as CustomerSessionProxy;
use PHPUnit\Framework\TestCase;

class CustomerTest extends TestCase
{
    /** @var CustomerSessionProxy&\PHPUnit\Framework\MockObject\MockObject */
    private $customerSessionMock;

    /** @var ConfigHelper&\PHPUnit\Framework\MockObject\MockObject */
    private $configHelperMock;

    /** @var Customer */
    private $section;

    protected function setUp(): void
    {
        $this->customerSessionMock = $this->createMock(CustomerSessionProxy::class);
        $this->configHelperMock = $this->createMock(ConfigHelper::class);

        $this->section = new Customer(
            $this->customerSessionMock,
            $this->configHelperMock,
        );
    }

    public function testReturnsEmptyAddressFieldsAndResolvedGroupForGuest()
    {
        $this->customerSessionMock->method('getCustomerId')->willReturn(null);
        $this->customerSessionMock->method('getCustomerGroupId')->willReturn(0);
        $this->customerSessionMock->method('isLoggedIn')->willReturn(false);

        $this->configHelperMock->method('getGroupNames')->with(0)->willReturn(['NOT LOGGED IN']);
        $this->configHelperMock->expects($this->never())->method('getBillingAddress');

        $data = $this->section->getSectionData();

        $this->assertSame(0, $data['id']);
        $this->assertSame('', $data['country']);
        $this->assertSame('', $data['city']);
        $this->assertSame('', $data['state']);
        $this->assertSame('', $data['zip']);
        $this->assertSame(['NOT LOGGED IN'], $data['groups']);
    }

    public function testReturnsBillingAddressAndGroupForLoggedInCustomer()
    {
        $this->customerSessionMock->method('getCustomerId')->willReturn(42);
        $this->customerSessionMock->method('getCustomerGroupId')->willReturn(3);
        $this->customerSessionMock->method('isLoggedIn')->willReturn(true);

        $customerModelMock = $this->createMock(CustomerModel::class);
        $customerModelMock->method('getDefaultBilling')->willReturn(99);
        $this->customerSessionMock->method('getCustomer')->willReturn($customerModelMock);

        $regionMock = $this->createMock(RegionInterface::class);
        $regionMock->method('getRegionCode')->willReturn('CA');

        $addressMock = $this->createMock(AddressInterface::class);
        $addressMock->method('getCountryId')->willReturn('US');
        $addressMock->method('getCity')->willReturn('Los Angeles');
        $addressMock->method('getRegion')->willReturn($regionMock);
        $addressMock->method('getPostcode')->willReturn('90001');

        $this->configHelperMock->method('getBillingAddress')->with(99)->willReturn($addressMock);
        $this->configHelperMock->method('getGroupNames')->with(3)->willReturn(['Wholesale']);

        $data = $this->section->getSectionData();

        $this->assertSame(42, $data['id']);
        $this->assertSame('US', $data['country']);
        $this->assertSame('Los Angeles', $data['city']);
        $this->assertSame('CA', $data['state']);
        $this->assertSame('90001', $data['zip']);
        $this->assertSame(['Wholesale'], $data['groups']);
    }

    public function testFallsBackToEmptyAddressWhenAddressLookupFails()
    {
        $this->customerSessionMock->method('getCustomerId')->willReturn(42);
        $this->customerSessionMock->method('getCustomerGroupId')->willReturn(1);
        $this->customerSessionMock->method('isLoggedIn')->willReturn(true);

        $customerModelMock = $this->createMock(CustomerModel::class);
        $customerModelMock->method('getDefaultBilling')->willReturn(99);
        $this->customerSessionMock->method('getCustomer')->willReturn($customerModelMock);

        $this->configHelperMock->method('getBillingAddress')->willReturn(null);
        $this->configHelperMock->method('getGroupNames')->willReturn(['General']);

        $data = $this->section->getSectionData();

        $this->assertSame('', $data['country']);
        $this->assertSame('', $data['city']);
        $this->assertSame('', $data['state']);
        $this->assertSame('', $data['zip']);
    }
}
