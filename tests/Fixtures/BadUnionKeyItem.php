<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;

#[Audited]
class BadUnionKeyItem
{
    public string|float $id;
}
