<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class NullableKeyItem
{
    public function __construct(
        public ?string $id = null,
        #[AuditedProperty]
        public int $quantity = 1,
    ) {}
}
