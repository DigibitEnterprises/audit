<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class UninitializedKeyItem
{
    public string $id;

    #[AuditedProperty]
    public int $quantity = 1;
}
