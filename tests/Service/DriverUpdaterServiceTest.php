<?php

namespace App\Tests\Service;

use App\Service\DriverUpdaterService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class DriverUpdaterServiceTest extends TestCase
{
    private DriverUpdaterService $service;
    private string $tempDir;
    private string $tempCacheFile;

    protected function setUp(): void
    {
        // Create temporary directory for tests
        $this->tempDir = sys_get_temp_dir() . '/certibot_test_' . uniqid();
        mkdir($this->tempDir);
        mkdir($this->tempDir . '/var/cache', 0777, true);
        
        $this->service = new DriverUpdaterService(
            new NullLogger(),
            $this->tempDir
        );
        
        $this->tempCacheFile = $this->tempDir . '/var/cache/driver_check.json';
    }

    protected function tearDown(): void
    {
        // Clean up
        if (file_exists($this->tempCacheFile)) {
            unlink($this->tempCacheFile);
        }
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    public function testClearCacheRemovesFile(): void
    {
        // Create a cache file
        file_put_contents($this->tempCacheFile, json_encode(['chrome' => ['last_check' => time()]]));
        
        $this->assertFileExists($this->tempCacheFile);
        
        $this->service->clearCache();
        
        $this->assertFileDoesNotExist($this->tempCacheFile);
    }

    public function testClearCacheForSpecificBrowser(): void
    {
        // Create cache with multiple browsers
        $cache = [
            'chrome' => ['last_check' => time()],
            'firefox' => ['last_check' => time()],
        ];
        file_put_contents($this->tempCacheFile, json_encode($cache));
        
        $this->service->clearCache('chrome');
        
        $this->assertFileExists($this->tempCacheFile);
        $content = json_decode(file_get_contents($this->tempCacheFile), true);
        $this->assertArrayNotHasKey('chrome', $content);
        $this->assertArrayHasKey('firefox', $content);
    }

    public function testClearCacheHandlesNonExistentFile(): void
    {
        // Should not throw an error even if cache doesn't exist
        $this->assertFileDoesNotExist($this->tempCacheFile);
        
        $this->service->clearCache();
        
        // Test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function testUpdateAllDriversReturnsArrayOfResults(): void
    {
        // This will fail because browsers may not be installed in test environment
        // But it should return an array structure
        $results = $this->service->updateAllDrivers();
        
        $this->assertIsArray($results);
        $this->assertArrayHasKey('chrome', $results);
        $this->assertArrayHasKey('firefox', $results);
        $this->assertArrayHasKey('edge', $results);
        
        // Each result should be a boolean
        $this->assertIsBool($results['chrome']);
        $this->assertIsBool($results['firefox']);
        $this->assertIsBool($results['edge']);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

