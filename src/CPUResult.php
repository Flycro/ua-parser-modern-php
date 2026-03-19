<?php

namespace UaParserModern;

readonly class CPUResult implements \JsonSerializable
{
    public function __construct(
        public ?string $architecture = null,
    ) {}

    /** @return array{architecture: ?string} */
    public function toArray(): array
    {
        return [
            'architecture' => $this->architecture,
        ];
    }

    /** @return array{architecture: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
