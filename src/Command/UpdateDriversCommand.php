<?php

namespace App\Command;

use App\Service\DriverUpdaterService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Command to manually update WebDrivers for Chrome, Firefox, or Edge.
 */
#[AsCommand(
    name: 'app:update-drivers',
    description: 'Update WebDriver for Chrome, Firefox, or Edge',
)]
class UpdateDriversCommand extends Command
{
    public function __construct(
        private readonly DriverUpdaterService $driverUpdater
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('browser', InputArgument::OPTIONAL, 'Browser to update (chrome, firefox, edge, or all)', 'all')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force update even if recently checked')
            ->addOption('clear-cache', null, InputOption::VALUE_NONE, 'Clear the driver update cache')
            ->setHelp(<<<'HELP'
                The <info>%command.name%</info> command updates WebDrivers for your browsers:

                  <info>php %command.full_name%</info>

                Update a specific browser:
                  <info>php %command.full_name% chrome</info>

                Force update:
                  <info>php %command.full_name% chrome --force</info>

                Clear cache and update:
                  <info>php %command.full_name% --clear-cache</info>
                HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $browser = $input->getArgument('browser');
        $force = $input->getOption('force');
        $clearCache = $input->getOption('clear-cache');

        $io->title('WebDriver Updater');

        // Clear cache if requested
        if ($clearCache) {
            $this->driverUpdater->clearCache();
            $io->success('Cache cleared successfully');
        }

        // Update drivers
        if ($browser === 'all') {
            $io->section('Updating all drivers...');
            $results = $this->driverUpdater->updateAllDrivers();

            $io->table(
                ['Browser', 'Status'],
                array_map(
                    fn($browser, $success) => [$browser, $success ? '✅ Success' : '❌ Failed'],
                    array_keys($results),
                    $results
                )
            );

            $successCount = count(array_filter($results));
            $totalCount = count($results);

            if ($successCount === $totalCount) {
                $io->success("All drivers updated successfully ({$successCount}/{$totalCount})");
                return Command::SUCCESS;
            } else {
                $io->warning("Some drivers failed to update ({$successCount}/{$totalCount})");
                return Command::FAILURE;
            }
        } else {
            $validBrowsers = ['chrome', 'firefox', 'edge'];
            if (!in_array($browser, $validBrowsers)) {
                $io->error("Invalid browser. Choose from: " . implode(', ', $validBrowsers));
                return Command::FAILURE;
            }

            $io->section("Updating {$browser} driver...");
            $success = $this->driverUpdater->ensureDriverUpdated($browser, $force);

            if ($success) {
                $io->success("{$browser} driver updated successfully");
                return Command::SUCCESS;
            } else {
                $io->error("Failed to update {$browser} driver");
                return Command::FAILURE;
            }
        }
    }
}

