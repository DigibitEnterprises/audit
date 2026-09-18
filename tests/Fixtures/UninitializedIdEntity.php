<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class UninitializedIdEntity
{
    public int $id;

    #[AuditedProperty]
    public string $name = 'unsaved';
}
