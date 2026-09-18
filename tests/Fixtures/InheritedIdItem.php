<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

/**
 * Mirrors a common ORM shape: the id lives on a private property declared by
 * an ancestor class, not on this class directly.
 */
#[Audited]
class InheritedIdItem extends AbstractIdentifiable
{
    #[AuditedProperty]
    public int $quantity = 1;
}
