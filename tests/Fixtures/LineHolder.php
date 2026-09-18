<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\AuditedCollection;
use Digibit\Audit\Attributes\Audited;

#[Audited]
class LineHolder
{
    public function __construct(
        public int $id,
        #[AuditedCollection(ofType: NullableKeyItem::class, keyBy: 'id')]
        public array $lines = [],
    ) {}
}
