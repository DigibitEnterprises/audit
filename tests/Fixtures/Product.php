<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\Audited;
use Digibit\Audit\Attributes\AuditedProperty;

#[Audited(id: 'sku')]
class Product
{
    public function __construct(
        public string $sku,
        #[AuditedProperty]
        public string $name,
    ) {}
}
