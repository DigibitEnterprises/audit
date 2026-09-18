<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited]
class Address
{
    public function __construct(
        public int $id,
        #[AuditedProperty]
        public string $city,
    ) {}

    public function getId(): int
    {
        return $this->id;
    }
}
