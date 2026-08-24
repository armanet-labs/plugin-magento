<?php

namespace Armanet\Integration\Test\Unit\Helper;

use Armanet\Integration\Helper\Data;
use Magento\Customer\Api\AddressRepositoryInterface;
use Magento\Customer\Api\Data\AddressInterface;
use Magento\Customer\Api\Data\GroupInterface;
use Magento\Customer\Api\GroupRepositoryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\TestFramework\Unit\Helper\ObjectManager;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

class DataTest extends TestCase
{
    /**
     * @var Data
     */
    private $helper;

    /**
     * @var ScopeConfigInterface|\PHPUnit\Framework\MockObject\MockObject
     */
    private $scopeConfigMock;

    /**
     * @var GroupRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $groupRepositoryMock;

    /**
     * @var AddressRepositoryInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private $addressRepositoryMock;

    /**
     * Set up test environment
     */
    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->groupRepositoryMock = $this->createMock(GroupRepositoryInterface::class);
        $this->addressRepositoryMock = $this->createMock(AddressRepositoryInterface::class);

        $this->helper = $objectManager->getObject(Data::class, [
            'scopeConfig' => $this->scopeConfigMock,
            'groupRepository' => $this->groupRepositoryMock,
            'addressRepository' => $this->addressRepositoryMock,
        ]);
    }

    public function testIsGettingApiKey()
    {
        $apiKey = 'test_api_key_123';

        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                Data::CONFIG_PATH_API_KEY,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn($apiKey);

        $this->assertEquals($apiKey, $this->helper->getApiKey());
    }

    public function testIsReturningTrueWhenIsTrackingEnabledIsEnabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                Data::CONFIG_PATH_ENABLE_TRACKING,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(true);

        $this->assertTrue($this->helper->isTrackingEnabled());
    }

    public function testIsReturningFalseWhenIsTrackingEnabledIsDisabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                Data::CONFIG_PATH_ENABLE_TRACKING,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(false);

        $this->assertFalse($this->helper->isTrackingEnabled());
    }

    public function testIsReturningTrueWhenIsFeedEnabledIsEnabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                Data::CONFIG_PATH_ENABLE_FEED,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(true);

        $this->assertTrue($this->helper->isFeedEnabled());
    }

    public function testIsReturningFalseWhenIsFeedEnabledIsDisabled()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('isSetFlag')
            ->with(
                Data::CONFIG_PATH_ENABLE_FEED,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(false);

        $this->assertFalse($this->helper->isFeedEnabled());
    }

    public function testGetUpcAttributeReturnsConfiguredValue()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                Data::CONFIG_PATH_UPC_ATTRIBUTE,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn('gtin');

        $this->assertSame('gtin', $this->helper->getUpcAttribute());
    }

    public function testGetUpcAttributeReturnsDefaultWhenConfigIsEmpty()
    {
        $this->scopeConfigMock->expects($this->once())
            ->method('getValue')
            ->with(
                Data::CONFIG_PATH_UPC_ATTRIBUTE,
                ScopeInterface::SCOPE_STORE
            )
            ->willReturn(null);

        $this->assertSame('upc', $this->helper->getUpcAttribute());
    }

    public function testGetGroupNamesReturnsEmptyArrayWhenGroupIdIsNull()
    {
        $this->groupRepositoryMock->expects($this->never())->method('getById');

        $this->assertSame([], $this->helper->getGroupNames(null));
    }

    public function testGetGroupNamesReturnsResolvedGroupCode()
    {
        $groupMock = $this->createMock(GroupInterface::class);
        $groupMock->method('getCode')->willReturn('Wholesale');

        $this->groupRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(3)
            ->willReturn($groupMock);

        $this->assertSame(['Wholesale'], $this->helper->getGroupNames(3));
    }

    public function testGetGroupNamesReturnsEmptyArrayWhenGroupCannotBeResolved()
    {
        $this->groupRepositoryMock->method('getById')->willThrowException(new \Exception('not found'));

        $this->assertSame([], $this->helper->getGroupNames(99));
    }

    public function testGetBillingAddressReturnsNullWhenAddressIdIsEmpty()
    {
        $this->addressRepositoryMock->expects($this->never())->method('getById');

        $this->assertNull($this->helper->getBillingAddress(null));
    }

    public function testGetBillingAddressReturnsResolvedAddress()
    {
        $addressMock = $this->createMock(AddressInterface::class);

        $this->addressRepositoryMock->expects($this->once())
            ->method('getById')
            ->with(99)
            ->willReturn($addressMock);

        $this->assertSame($addressMock, $this->helper->getBillingAddress(99));
    }

    public function testGetBillingAddressReturnsNullWhenAddressCannotBeResolved()
    {
        $this->addressRepositoryMock->method('getById')->willThrowException(new \Exception('not found'));

        $this->assertNull($this->helper->getBillingAddress(99));
    }
}
