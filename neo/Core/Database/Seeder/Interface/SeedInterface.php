<?php

declare(strict_types=1);

namespace Neo\Core\Database\Seeder\Interface;

use Neo\Core\Database\ORM\Persistence\EntityManager;

interface SeedInterface
{
    public function run(EntityManager $entityManager): void;
}