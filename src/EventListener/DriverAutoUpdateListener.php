<?php

namespace App\EventListener;

use App\Service\DriverUpdaterService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Event\ConsoleCommandEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Event listener that automatically checks and updates drivers before running
 * commands that use browsers (crawling commands).
 *
 * This provides an additional layer of automation beyond the BrowserClientService.
 *
 * NOTE: This listener is DISABLED by default. To enable it:
 * 1. Uncomment the AsEventListener attribute below
 * 2. Set DRIVER_AUTO_UPDATE_LISTENER=true in .env
 */
class DriverAutoUpdateListener
{
    private const COMMANDS_REQUIRING_DRIVERS = [
        'app:crawl:symfony-exam-topics',
        'app:crawl:symfony-doc',
        // Add other commands that use browsers here
    ];

    public function __construct(
        private readonly DriverUpdaterService $driverUpdater,
        private readonly LoggerInterface $logger,
    ) {
    }

    // Uncomment this attribute to enable the listener:
    // #[AsEventListener(event: ConsoleCommandEvent::class)]
    public function __invoke(ConsoleCommandEvent $event): void
    {
        $command = $event->getCommand();
        if ($command === null) {
            return;
        }

        $commandName = $command->getName();

        // Only trigger for commands that use browsers
        if (!in_array($commandName, self::COMMANDS_REQUIRING_DRIVERS)) {
            return;
        }

        // Get browser from environment
        $browser = $_ENV['BROWSER'] ?? 'chrome';

        $output = $event->getOutput();
        $output->writeln("<comment>🔧 Vérification du driver {$browser}...</comment>");

        try {
            $this->driverUpdater->ensureDriverUpdated($browser);
            $output->writeln("<info>✅ Driver vérifié et prêt</info>");
        } catch (\Exception $e) {
            $this->logger->warning(
                "Failed to auto-update driver in event listener: " . $e->getMessage()
            );
            $output->writeln("<comment>⚠️  Impossible de vérifier le driver, tentative de poursuite...</comment>");
        }

        $output->writeln('');
    }
}


