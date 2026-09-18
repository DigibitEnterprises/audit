<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class SelfReferencing
{
    #[AuditedProperty]
    public ?self $self = null;

    #[AuditedProperty]
    public string $name = 'x';
}
