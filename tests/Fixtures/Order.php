<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\AuditedCollection;
use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class Order
{
    /** @param OrderLine[] $lines */
    public function __construct(
        public string $id = 'order-1',
        #[AuditedProperty]
        public string $status = 'pending',
        #[AuditedProperty(valueExpr: 'email')]
        public ?Customer $customer = null,
        #[AuditedCollection(ofType: OrderLine::class, keyBy: 'id')]
        public array $lines = [],
        public string $internalNotes = '',
    ) {}
}
