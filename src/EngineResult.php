<?php

namespace UaParserModern;

readonly class EngineResult implements \JsonSerializable
{
    public function __construct(
        public ?string $name = null,
        public ?string $version = null,
    ) {}

    /** @return array{name: ?string, version: ?string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
        ];
    }

    /** @return array{name: ?string, version: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
