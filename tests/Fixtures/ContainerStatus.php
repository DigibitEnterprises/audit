<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

enum ContainerStatus: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
}
