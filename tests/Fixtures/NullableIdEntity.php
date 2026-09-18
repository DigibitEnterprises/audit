<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class NullableIdEntity
{
    public function __construct(
        public ?int $id = null,
        #[AuditedProperty]
        public string $name = 'unsaved',
    ) {}
}
