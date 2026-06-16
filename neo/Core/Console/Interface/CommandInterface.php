<?php
declare(strict_types=1);

namespace Neo\Core\Console\Interface;

use Neo\Core\Console\Enum\ExitCode;
use Neo\Core\Console\Input\Input;
use Neo\Core\Console\Output\Output;

interface CommandInterface
{
    public function configure(): void;

    public function do(Input $input, Output $output): ExitCode;

    public function getName(): string;

    public function getDescription(): string;

    public function getCategory(): string;
}