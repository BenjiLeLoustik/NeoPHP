<?php
declare(strict_types=1);

namespace Neo\Core\Cron\Commands;

use Neo\Core\Application\ApplicationPaths;
use Neo\Core\Console\Abstract\AbstractCommand;
use Neo\Core\Console\Attribute\Command;
use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Input\InputOption;
use Neo\Core\Console\Output\Output;
use Neo\Core\Cron\Exception\CronException;
use Neo\Core\Cron\Runner\CronRunner;
use Neo\Core\Cron\Scanner\CronScanner;
use Neo\Core\DI\Container;
use Neo\Core\DI\Exception\ContainerException;
use Neo\Core\Package\Interface\PackageInterface;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;

#[Command(
    name: 'cron:run',
    description: 'Run all due cron jobs for a project',
    category: 'Cron',
)]
final class CronRunCommand extends AbstractCommand
{
    public function __construct(
        private Container $container
    ) {
    }
    public function configure(): void
    {
        $this->addOption(
            name: 'project',
            shortcut: null,
            mode: InputOption::REQUIRED,
            description: 'Target project',
        );
    }

    /**
     * @throws NotFoundExceptionInterface
     * @throws CronException
     * @throws \ReflectionException
     * @throws ContainerExceptionInterface
     * @throws ContainerException
     */
    public function do(Input $input, Output $output): ExitCode
    {
        $project = $input->getOption('project');

        if (!is_dir(ROOT_DIR . "/src/$project")) {
            Output::error("Project '$project' not found.");
            return ExitCode::FAILURE;
        }

        new ApplicationPaths($this->container)->register($project);

        $paths = [$this->container->get('cronsPath')];

        if ($this->container->has('packages')) {
            /** @var array<int, PackageInterface> $packages */
            $packages = $this->container->get('packages');

            foreach ($packages as $package) {
                $path = $package->getCronsPath();
                if ($path !== null) {
                    $paths[] = $path;
                }
            }
        }

        $scanner = new CronScanner();
        $jobs = $scanner->scan($paths);

        if (empty($jobs)) {
            Output::muted('No cron jobs found.');
            return ExitCode::SUCCESS;
        }

        $runner = new CronRunner($this->container);

        $runner->run($jobs);

        return ExitCode::SUCCESS;
    }

    protected function getAvailableProjects(): array
    {
        return glob(ROOT_DIR . '/src/*', GLOB_ONLYDIR)
                |> (fn (array $d): array => array_map(basename(...), $d));
    }
}