<?php

namespace Neo\Core\DI;

use Neo\Core\DI\Exception\ContainerException;

final class ContainerRegistry
{
    private static ?Container $instance = null;

    public static function set(Container $container): void
    {
        self::$instance = $container;
    }

    /**
     * @throws ContainerException
     */
    public static function get(): Container
    {
        if (self::$instance === null) {
            throw new ContainerException(
                title: 'Container Not Registered',
                message: 'Container has not been registered. Call ContainerRegistry::set() during bootstrap.',
                code: 500
            );
        }

        return self::$instance;
    }
}
