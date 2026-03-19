<?php

namespace UaParserModern;

readonly class BrowserResult implements \JsonSerializable
{
    public function __construct(
        public ?string $name = null,
        public ?string $version = null,
        public ?string $major = null,
    ) {}

    /** @return array{name: ?string, version: ?string, major: ?string} */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'major' => $this->major,
        ];
    }

    /** @return array{name: ?string, version: ?string, major: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
