<?php

namespace App\Service;

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
     */
    public function __construct(
        private readonly string $browser,
        private readonly ?string $chromeDriverPath,
        private readonly ?string $geckoDriverPath,
        private readonly ?string $edgeDriverPath,
    ) {
    }

    /**
     * Create a browser client based on the configured browser and driver paths.
     *
     * @param array $additionalOptions Additional options to pass to the browser client.
     * @return Client
     * @throws \RuntimeException
     */
    public function createClient(array $additionalOptions = []): Client
    {
        $defaultOptions = [
            '--headless',
            '--disable-dev-shm-usage',
            '--no-sandbox',
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
