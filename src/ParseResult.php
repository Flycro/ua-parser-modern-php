<?php

namespace UaParserModern;

readonly class ParseResult implements \JsonSerializable
{
    public function __construct(
        public string $ua,
        public BrowserResult $browser,
        public DeviceResult $device,
        public EngineResult $engine,
        public OSResult $os,
        public CPUResult $cpu,
    ) {}

    /** @return array{ua: string, browser: array{name: ?string, version: ?string, major: ?string}, device: array{model: ?string, type: ?string, vendor: ?string}, engine: array{name: ?string, version: ?string}, os: array{name: ?string, version: ?string}, cpu: array{architecture: ?string}} */
    public function toArray(): array
    {
        return [
            'ua' => $this->ua,
            'browser' => $this->browser->toArray(),
            'device' => $this->device->toArray(),
            'engine' => $this->engine->toArray(),
            'os' => $this->os->toArray(),
            'cpu' => $this->cpu->toArray(),
        ];
    }

    /** @return array{ua: string, browser: array{name: ?string, version: ?string, major: ?string}, device: array{model: ?string, type: ?string, vendor: ?string}, engine: array{name: ?string, version: ?string}, os: array{name: ?string, version: ?string}, cpu: array{architecture: ?string}} */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
