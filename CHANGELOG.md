# Changelog

All notable changes to `ua-parser-modern-php` will be documented in this file.

## 0.1.1 - Unreleased

- Initial release
- Full PHP port of ua-parser-modern v0.1.1 regex engine
- Browser, Engine, OS, CPU, and Device detection
- Readonly DTO result types (`BrowserResult`, `DeviceResult`, `EngineResult`, `OSResult`, `CPUResult`, `ParseResult`)
- All result objects implement `JsonSerializable` and provide `toArray()`
- 2,257 fixture-based tests from official ua-parser-modern
