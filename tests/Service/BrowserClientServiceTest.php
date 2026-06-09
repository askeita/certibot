<?php

namespace App\Tests\Service;

use App\Service\BrowserClientService;
use PHPUnit\Framework\TestCase;

/**
 * BrowserClientServiceTest
 *
 * Unit tests for the BrowserClientService class.
 */
class BrowserClientServiceTest extends TestCase
{
    public function testCreateClientThrowsWhenChromeDriverPathMissing(): void
    {
        $service = new BrowserClientService('chrome', null, null, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CHROME_DRIVER_PATH is not configured.');

        $service->createClient();
    }

    public function testCreateClientThrowsWhenGeckoDriverPathMissing(): void
    {
        $service = new BrowserClientService('firefox', null, null, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('GECKO_DRIVER_PATH is not configured.');

        $service->createClient();
    }

    public function testCreateClientThrowsWhenEdgeDriverPathMissing(): void
    {
        $service = new BrowserClientService('edge', null, null, null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EDGE_DRIVER_PATH is not configured.');

        $service->createClient();
    }
}


