<?php

namespace Armanet\Integration\Test\Unit\Observer;

use Armanet\Integration\Observer\ConfigChanged;
use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\Event\Observer;
use PHPUnit\Framework\TestCase;

class ConfigChangedTest extends TestCase
{
    /** @var TypeListInterface&\PHPUnit\Framework\MockObject\MockObject */
    private $cacheTypeListMock;

    /** @var ConfigChanged */
    private $observer;

    protected function setUp(): void
    {
        $this->cacheTypeListMock = $this->createMock(TypeListInterface::class);
        $this->observer = new ConfigChanged($this->cacheTypeListMock);
    }

    public function testExecuteCleansFullPageAndBlockHtmlCacheTypes()
    {
        $cleaned = [];
        $this->cacheTypeListMock->expects($this->exactly(2))
            ->method('cleanType')
            ->willReturnCallback(function (string $type) use (&$cleaned): void {
                $cleaned[] = $type;
            });

        $this->observer->execute($this->createMock(Observer::class));

        $this->assertSame(['full_page', 'block_html'], $cleaned);
    }
}
