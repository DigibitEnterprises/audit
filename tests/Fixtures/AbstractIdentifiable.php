<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

abstract class AbstractIdentifiable
{
    private ?int $id = null;

    public function getId(): ?int { return $this->id; }
}
