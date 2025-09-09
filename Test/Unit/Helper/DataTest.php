<?php

namespace Armanet\Integration\Test\Unit\Helper;

use Armanet\Integration\Helper\Data;
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
     * Set up test environment
     */
    protected function setUp(): void
    {
        $objectManager = new ObjectManager($this);

        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);

        $this->helper = $objectManager->getObject(Data::class, [
            'scopeConfig' => $this->scopeConfigMock
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
}
