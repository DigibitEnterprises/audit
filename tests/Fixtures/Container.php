<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\AuditedCollection;
use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class Container
{
    /** @param OrderLine[] $lines */
    public function __construct(
        public int $id,
        #[AuditedProperty]
        public Address $address,
        #[AuditedCollection(ofType: OrderLine::class, keyBy: 'id')]
        public array $lines = [],
        #[AuditedProperty]
        public ContainerStatus $status = ContainerStatus::Active,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }
}
