<?php

use UaParserModern\BrowserResult;
use UaParserModern\CPUResult;
use UaParserModern\DeviceResult;
use UaParserModern\EngineResult;
use UaParserModern\OSResult;
use UaParserModern\ParseResult;
use UaParserModern\UserAgentParser;

function loadFixture(string $filename): array
{
    $path = __DIR__."/fixtures/{$filename}";

    return json_decode(file_get_contents($path), true);
}

function normalizeExpect(mixed $value): ?string
{
    if ($value === 'undefined' || $value === null) {
        return null;
    }

    return (string) $value;
}

$methods = [
    [
        'title' => 'parseBrowser',
        'method' => 'parseBrowser',
        'fixture' => 'browser-test.json',
        'properties' => ['name', 'version', 'major'],
    ],
    [
        'title' => 'parseCPU',
        'method' => 'parseCPU',
        'fixture' => 'cpu-test.json',
        'properties' => ['architecture'],
    ],
    [
        'title' => 'parseDevice',
        'method' => 'parseDevice',
        'fixture' => 'device-test.json',
        'properties' => ['model', 'type', 'vendor'],
        'skip' => fn ($entry) => str_contains($entry['desc'], 'DuckDuckGo mobile browser'),
    ],
    [
        'title' => 'parseEngine',
        'method' => 'parseEngine',
        'fixture' => 'engine-test.json',
        'properties' => ['name', 'version'],
    ],
    [
        'title' => 'parseOS',
        'method' => 'parseOS',
        'fixture' => 'os-test.json',
        'properties' => ['name', 'version'],
    ],
];

foreach ($methods as $m) {
    $fixtures = loadFixture($m['fixture']);
    $method = $m['method'];
    $skip = $m['skip'] ?? null;

    foreach ($fixtures as $idx => $entry) {
        if (! isset($entry['ua'])) {
            continue;
        }

        if ($skip && $skip($entry)) {
            continue;
        }

        foreach ($m['properties'] as $property) {
            $desc = $entry['desc'];
            $ua = $entry['ua'];
            $expected = normalizeExpect($entry['expect'][$property] ?? null);

            it("{$m['title']} #{$idx}: [{$desc}] -> {$property}", function () use ($method, $ua, $property, $expected) {
                $result = UserAgentParser::$method($ua);
                expect($result->$property)->toBe($expected);
            });
        }
    }
}

describe('parse', function () {
    it('returns composed parser output', function () {
        $ua = 'Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.6 (KHTML, like Gecko) Chrome/20.0.1090.0 Safari/536.6';
        $result = UserAgentParser::parse($ua);

        expect($result)->toBeInstanceOf(ParseResult::class);
        expect($result->browser)->toEqual(UserAgentParser::parseBrowser($ua));
        expect($result->engine)->toEqual(UserAgentParser::parseEngine($ua));
        expect($result->os)->toEqual(UserAgentParser::parseOS($ua));
        expect($result->device)->toEqual(UserAgentParser::parseDevice($ua));
        expect($result->cpu)->toEqual(UserAgentParser::parseCPU($ua));
    });

    it('returns typed result objects', function () {
        $result = UserAgentParser::parse('Mozilla/5.0');

        expect($result->browser)->toBeInstanceOf(BrowserResult::class);
        expect($result->device)->toBeInstanceOf(DeviceResult::class);
        expect($result->engine)->toBeInstanceOf(EngineResult::class);
        expect($result->os)->toBeInstanceOf(OSResult::class);
        expect($result->cpu)->toBeInstanceOf(CPUResult::class);
    });
});

describe('default result shape', function () {
    it('returns the expected default object for empty UA', function () {
        $result = UserAgentParser::parse('');

        expect($result)->toEqual(new ParseResult(
            ua: '',
            browser: new BrowserResult,
            device: new DeviceResult,
            engine: new EngineResult,
            os: new OSResult,
            cpu: new CPUResult,
        ));
    });
});

describe('user-agent length', function () {
    it('greater than 500 chars should be trimmed down', function () {
        $ua = 'Mozilla/5.0 '.str_repeat('x', 600);
        $result = UserAgentParser::parse($ua);

        expect(mb_strlen($result->ua))->toBe(500);
    });
});

describe('JSON serialization', function () {
    it('serializes ParseResult to the expected JSON structure', function () {
        $result = UserAgentParser::parse('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
        $json = json_decode(json_encode($result), true);

        expect($json)->toBeArray();
        expect($json['ua'])->toBeString();
        expect($json['browser'])->toHaveKeys(['name', 'version', 'major']);
        expect($json['device'])->toHaveKeys(['model', 'type', 'vendor']);
        expect($json['engine'])->toHaveKeys(['name', 'version']);
        expect($json['os'])->toHaveKeys(['name', 'version']);
        expect($json['cpu'])->toHaveKeys(['architecture']);
    });

    it('serializes individual results to arrays', function () {
        $browser = UserAgentParser::parseBrowser('Mozilla/5.0 Chrome/120.0.0.0');
        $array = $browser->toArray();

        expect($array)->toBeArray();
        expect($array)->toHaveKeys(['name', 'version', 'major']);
    });
});
