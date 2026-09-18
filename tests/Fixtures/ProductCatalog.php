<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

use Digibit\Audit\Attributes\AuditedCollection;
use Digibit\Audit\Attributes\Audited;

#[Audited]
class ProductCatalog
{
    /** @param Product[] $products */
    public function __construct(
        public int $id,
        #[AuditedCollection(ofType: Product::class, keyBy: 'sku')]
        public array $products = [],
    ) {}
}
