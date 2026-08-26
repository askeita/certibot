<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Process\Process;

/**
 * Service responsible for checking and updating WebDrivers automatically.
 *
 * This service ensures that the WebDriver version matches the installed browser version
 * to avoid compatibility issues during crawling operations.
 */
readonly class DriverUpdaterService
{
    private string $cacheFile;
    private string $updateScript;
    private int $checkInterval;
    private int $processTimeout;

    public function __construct(
        private LoggerInterface $logger,
        private string          $projectDir,
        ?int                    $checkInterval = null,
        ?int                    $processTimeout = null,
    ) {
        $this->cacheFile = $projectDir . '/var/cache/driver_check.json';
        $this->updateScript = $projectDir . '/update_driver.py';
        $this->checkInterval = $checkInterval ?? (int)($_ENV['DRIVER_CHECK_INTERVAL'] ?? 86400);
        $this->processTimeout = $processTimeout ?? (int)($_ENV['DRIVER_UPDATE_TIMEOUT'] ?? 120);
    }

    /**
     * Check if the driver needs updating and update it if necessary.
     * Uses a cache mechanism to avoid checking too frequently.
     *
     * @param string $browser The browser name (chrome, firefox, edge)
     * @param bool $force Force the update even if recently checked
     * @return bool True if update was performed or not needed, false on error
     */
    public function ensureDriverUpdated(string $browser, bool $force = false): bool
    {
        $browser = strtolower($browser);

        // Check if we need to update based on cache
        if (!$force && !$this->shouldCheckUpdate($browser)) {
            $this->logger->debug("Driver check skipped for {$browser} (recently checked)");
            return true;
        }

        $this->logger->info("Checking/updating {$browser} driver...");

        try {
            // Run the update script with --detect flag
            $process = new Process([
                'python3',
                $this->updateScript,
                $browser,
                '--detect'
            ]);

            $process->setTimeout($this->processTimeout);
            $process->run();

            if ($process->isSuccessful()) {
                $this->logger->info("Driver update check completed for {$browser}");
                $this->updateCache($browser);
                return true;
            } else {
                $this->logger->warning(
                    "Driver update failed for {$browser}: " . $process->getErrorOutput()
                );
                return false;
            }
        } catch (\Exception $e) {
            // Catch all exceptions including ProcessFailedException and ProcessTimedOutException
            $this->logger->error(
                "Exception during driver update for {$browser}: " . $e->getMessage()
            );
            return false;
        }
    }

    /**
     * Check if a driver has a compatible version with the installed browser.
     *
     * @param string $browser The browser name
     * @return bool True if versions are compatible
     */
    public function isDriverCompatible(string $browser): bool
    {
        $browser = strtolower($browser);

        try {
            // Get browser version
            $browserVersion = $this->getBrowserVersion($browser);
            if (!$browserVersion) {
                $this->logger->warning("Could not detect {$browser} version");
                return false;
            }

            // Get driver version
            $driverVersion = $this->getDriverVersion($browser);
            if (!$driverVersion) {
                $this->logger->warning("Could not detect {$browser} driver version");
                return false;
            }

            // Compare major versions
            $browserMajor = $this->getMajorVersion($browserVersion);
            $driverMajor = $this->getMajorVersion($driverVersion);

            $compatible = $browserMajor === $driverMajor;

            if (!$compatible) {
                $this->logger->warning(
                    "Version mismatch for {$browser}: browser={$browserVersion}, driver={$driverVersion}"
                );
            }

            return $compatible;
        } catch (\Exception $e) {
            $this->logger->error("Error checking driver compatibility: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Update all drivers for installed browsers.
     *
     * @return array Array of results per browser
     */
    public function updateAllDrivers(): array
    {
        $results = [];
        $browsers = ['chrome', 'firefox', 'edge'];

        foreach ($browsers as $browser) {
            $results[$browser] = $this->ensureDriverUpdated($browser, true);
        }

        return $results;
    }

    /**
     * Get the browser version.
     *
     * @param string $browser
     * @return string|null
     */
    private function getBrowserVersion(string $browser): ?string
    {
        $commands = match ($browser) {
            'chrome' => ['google-chrome --version', 'chromium --version'],
            'firefox' => ['firefox --version'],
            'edge' => ['microsoft-edge --version', 'microsoft-edge-stable --version'],
            default => []
        };

        foreach ($commands as $command) {
            try {
                $process = Process::fromShellCommandline($command);
                $process->run();

                if ($process->isSuccessful()) {
                    $output = $process->getOutput();
                    if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
                        return $matches[1];
                    }
                    if (preg_match('/(\d+\.\d+)/', $output, $matches)) {
                        return $matches[1];
                    }
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return null;
    }

    /**
     * Get the driver version.
     *
     * @param string $browser
     * @return string|null
     */
    private function getDriverVersion(string $browser): ?string
    {
        $driverPaths = [
            'chrome' => $this->projectDir . '/drivers/chromedriver',
            'firefox' => $this->projectDir . '/drivers/geckodriver',
            'edge' => $this->projectDir . '/drivers/msedgedriver',
        ];

        $driverPath = $driverPaths[$browser] ?? null;
        if (!$driverPath || !file_exists($driverPath)) {
            return null;
        }

        try {
            $process = new Process([$driverPath, '--version']);
            $process->run();

            if ($process->isSuccessful()) {
                $output = $process->getOutput();
                if (preg_match('/(\d+\.\d+\.\d+\.\d+)/', $output, $matches)) {
                    return $matches[1];
                }
                if (preg_match('/(\d+\.\d+\.\d+)/', $output, $matches)) {
                    return $matches[1];
                }
            }
        } catch (\Exception $e) {
            return null;
        }

        return null;
    }

    /**
     * Extract major version from version string.
     *
     * @param string $version
     * @return int|null
     */
    private function getMajorVersion(string $version): ?int
    {
        if (preg_match('/^(\d+)/', $version, $matches)) {
            return (int) $matches[1];
        }

        return null;
    }

    /**
     * Check if we should check for updates based on cache.
     *
     * @param string $browser
     * @return bool
     */
    private function shouldCheckUpdate(string $browser): bool
    {
        $cacheFile = $this->cacheFile;

        if (!file_exists($cacheFile)) {
            return true;
        }

        try {
            $cache = json_decode(file_get_contents($cacheFile), true);

            if (!isset($cache[$browser])) {
                return true;
            }

            $lastCheck = $cache[$browser]['last_check'] ?? 0;
            $now = time();

            // Check if interval has passed
            return ($now - $lastCheck) > $this->checkInterval;
        } catch (\Exception $e) {
            $this->logger->error("Error reading cache: " . $e->getMessage());
            return true;
        }
    }

    /**
     * Update the cache with the last check timestamp.
     *
     * @param string $browser
     * @return void
     */
    private function updateCache(string $browser): void
    {
        $cacheFile = $this->cacheFile;
        $cacheDir = dirname($cacheFile);

        // Create cache directory if it doesn't exist
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir, 0755, true);
        }

        $cache = [];
        if (file_exists($cacheFile)) {
            try {
                $cache = json_decode(file_get_contents($cacheFile), true) ?? [];
            } catch (\Exception $e) {
                // Ignore errors, start fresh
            }
        }

        $cache[$browser] = [
            'last_check' => time(),
            'checked_at' => date('Y-m-d H:i:s'),
        ];

        file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
    }

    /**
     * Clear the cache for all or specific browser.
     *
     * @param string|null $browser If null, clears all cache
     * @return void
     */
    public function clearCache(?string $browser = null): void
    {
        $cacheFile = $this->cacheFile;

        if (!file_exists($cacheFile)) {
            return;
        }

        if ($browser === null) {
            unlink($cacheFile);
            $this->logger->info("Driver update cache cleared");
        } else {
            // Normalize browser name to lowercase for consistency
            $browser = strtolower($browser);
            try {
                $cache = json_decode(file_get_contents($cacheFile), true) ?? [];
                unset($cache[$browser]);
                file_put_contents($cacheFile, json_encode($cache, JSON_PRETTY_PRINT));
                $this->logger->info("Cache cleared for {$browser} driver");
            } catch (\Exception $e) {
                $this->logger->error("Error clearing cache: " . $e->getMessage());
            }
        }
    }
}

