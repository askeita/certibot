<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Panther\Client;


/**
 * Service responsible for creating browser clients based on the configured browser and driver paths.
 */
class BrowserClientService
{
    /**
     * Constructor
     *
     * @param string $browser The browser to use (e.g., 'chrome', 'firefox', 'edge').
     * @param string|null $chromeDriverPath The path to the ChromeDriver executable (required if using Chrome).
     * @param string|null $geckoDriverPath The path to the GeckoDriver executable (required if using Firefox).
     * @param string|null $edgeDriverPath The path to the EdgeDriver executable (required if using Edge).
     * @param DriverUpdaterService|null $driverUpdater Service to automatically update drivers.
     * @param LoggerInterface|null $logger Logger instance.
     */
    public function __construct(
        private readonly string $browser,
        private readonly ?string $chromeDriverPath,
        private readonly ?string $geckoDriverPath,
        private readonly ?string $edgeDriverPath,
        private readonly ?DriverUpdaterService $driverUpdater = null,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Create a browser client based on the configured browser and driver paths.
     * Automatically checks and updates the driver if needed before creating the client.
     *
     * @param array $additionalOptions Additional options to pass to the browser client.
     * @return Client
     * @throws \RuntimeException
     */
    public function createClient(array $additionalOptions = []): Client
    {
        // Automatically check and update driver if service is available
        if ($this->driverUpdater !== null) {
            try {
                $this->driverUpdater->ensureDriverUpdated($this->browser);
            } catch (\Exception $e) {
                // Log but don't fail - let the user see the actual error
                if ($this->logger) {
                    $this->logger->warning(
                        "Could not auto-update driver for {$this->browser}: " . $e->getMessage()
                    );
                }
            }
        }

        $defaultOptions = [
            '--headless',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            '--disable-gpu',
            '--disable-blink-features=AutomationControlled',
            '--user-agent=Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/147.0.0.0 Safari/537.36',
        ];

        $options = array_merge($defaultOptions, $additionalOptions);
        $browser = strtolower($this->browser);

        return match ($browser) {
            'firefox' => (function () use ($options) {
                if (empty($this->geckoDriverPath)) {
                    throw new \RuntimeException('GECKO_DRIVER_PATH is not configured.');
                }

                return Client::createFirefoxClient($this->geckoDriverPath, $options);
            })(),
            'edge' => (function () use ($options) {
                if (empty($this->edgeDriverPath)) {
                    throw new \RuntimeException('EDGE_DRIVER_PATH is not configured.');
                }

                // EdgeDriver uses the same client creation method as ChromeDriver
                return Client::createChromeClient($this->edgeDriverPath, $options);
            })(),
            default => (function () use ($options) {
                if (empty($this->chromeDriverPath)) {
                    throw new \RuntimeException("CHROME_DRIVER_PATH is not configured.");
                }

                return Client::createChromeClient($this->chromeDriverPath, $options);
            })(),
        };
    }

}
