<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;

#[Audited]
class UntypedKeyItem
{
    /** @var mixed intentionally untyped to exercise the untyped-property rejection path */
    public $id;
}
