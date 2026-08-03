<?php

declare(strict_types=1);

namespace Neo\Core\Package\Commands;

use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Output\Output;
use Neo\Core\Package\Exception\PackageException;
use Neo\Core\Package\PackageManager;

#[Command(
    name: 'package:sync',
    description: 'Link every declared package into its project(s)',
    category: 'Package',
)]
final class PackageSyncCommand extends AbstractCommand
{
    public function do(Input $input, Output $output): ExitCode
    {
        try {
            $manager = new PackageManager();
            $result = $manager->syncAllProjects();

            if ($result['synced'] === 0) {
                Output::warning('No project declares any package.');
                return ExitCode::SUCCESS;
            }

            foreach ($result['projects'] as $project) {
                Output::success("Synced: $project");
            }

            return ExitCode::SUCCESS;
        } catch (PackageException $e) {
            Output::error($e->getTitle() . ': ' . $e->getMessage());
            return ExitCode::FAILURE;
        }
    }
}