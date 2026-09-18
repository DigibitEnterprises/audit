<?php declare(strict_types=1);

namespace Digibit\Audit\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class AuditedProperty {
    public function __construct(
        public ?string $valueExpr = null,
    ) {}
}