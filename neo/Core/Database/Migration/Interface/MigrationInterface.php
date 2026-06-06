<?php
declare(strict_types=1);

namespace Neo\Core\Database\Migration\Interface;

use Neo\Core\Database\DatabaseManager;

interface MigrationInterface
{
    public function up(DatabaseManager $db): void;

    public function down(DatabaseManager $db): void;
}