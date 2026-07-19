<?php

declare(strict_types=1);

namespace Neo\Core\Extension\Enum;

enum ExtensionTypeEnum: string
{
    case CONTROLLER = 'controller';
    case VIEW = 'twig';
}