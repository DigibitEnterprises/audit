<?php declare(strict_types=1);

namespace Digibit\Audit\Tests\Fixtures;

class JsonSerializableValue implements \JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['x' => 1];
    }
}
