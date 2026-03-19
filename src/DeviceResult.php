<?php

namespace UaParserModern;

readonly class DeviceResult implements \JsonSerializable
{
    public function __construct(
        public ?string $model = null,
        public ?string $type = null,
        public ?string $vendor = null,
    ) {}

    /** @return array{model: ?string, type: ?string, vendor: ?string} */
    public function toArray(): array
    {
        return [
            'model' => $this->model,
            'type' => $this->type,
            'vendor' => $this->vendor,
        ];
    }

    /** @return array{model: ?string, type: ?string, vendor: ?string} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
