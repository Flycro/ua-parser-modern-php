# ua-parser-modern-php

PHP port of [ua-parser-modern](https://github.com/antfu-collective/ua-parser-modern) - detect Browser, Engine, OS, CPU, and Device type/model from User-Agent strings.

All regex patterns are ported 1:1 from the original JavaScript source to ensure identical detection behavior.

## Requirements

- PHP 8.2+
- `ext-mbstring`

## Installation

```bash
composer require flycro/ua-parser-modern-php
```

## Usage

### Parse everything at once

```php
use UaParserModern\UserAgentParser;

$result = UserAgentParser::parse($userAgent);

$result->browser->name;    // 'Chrome'
$result->browser->version; // '120.0.0.0'
$result->browser->major;   // '120'

$result->os->name;         // 'Windows'
$result->os->version;      // '10'

$result->device->model;    // null
$result->device->type;     // null
$result->device->vendor;   // null

$result->engine->name;     // 'Blink'
$result->engine->version;  // '120.0.0.0'

$result->cpu->architecture; // 'amd64'
```

### Parse individual components

Only parse what you need:

```php
$browser = UserAgentParser::parseBrowser($userAgent);
$browser->name;    // 'Chrome'
$browser->version; // '120.0.0.0'
$browser->major;   // '120'

$os = UserAgentParser::parseOS($userAgent);
$os->name;    // 'Windows'
$os->version; // '10'

$device = UserAgentParser::parseDevice($userAgent);
$engine = UserAgentParser::parseEngine($userAgent);
$cpu    = UserAgentParser::parseCPU($userAgent);
```

### JSON serialization

All result objects implement `JsonSerializable`:

```php
$result = UserAgentParser::parse($userAgent);

json_encode($result);
// {"ua":"...","browser":{"name":"Chrome","version":"120.0.0.0","major":"120"},...}
```

### Convert to array

```php
$result->toArray();          // Full nested array
$result->browser->toArray(); // ['name' => 'Chrome', 'version' => '120.0.0.0', 'major' => '120']
```

## Result Types

All results are readonly value objects:

| Method | Return Type | Properties |
|--------|------------|------------|
| `parse()` | `ParseResult` | `ua`, `browser`, `device`, `engine`, `os`, `cpu` |
| `parseBrowser()` | `BrowserResult` | `name`, `version`, `major` |
| `parseDevice()` | `DeviceResult` | `model`, `type`, `vendor` |
| `parseEngine()` | `EngineResult` | `name`, `version` |
| `parseOS()` | `OSResult` | `name`, `version` |
| `parseCPU()` | `CPUResult` | `architecture` |

## Testing

```bash
composer test
```

## Credits

This is a PHP port of [ua-parser-modern](https://github.com/antfu-collective/ua-parser-modern) by [Anthony Fu](https://github.com/antfu), which was forked from [my-ua-parser](https://github.com/mcollina/my-ua-parser) by [Matteo Collina](https://github.com/mcollina), which was a fork of [ua-parser-js](https://github.com/nicedoc/ua-parser-modern) by [Faisal Salman](https://github.com/nicedoc).

- [Anthony Fu](https://github.com/antfu)
- [Matteo Collina](https://github.com/mcollina)
- [Faisal Salman](https://github.com/nicedoc)
- [Flycro](https://github.com/flycro)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
