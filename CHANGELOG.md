# Changelog

All notable changes to `initphp/sessions` are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [5.0.0] - 2026-11-22

### Changed (breaking)

- The development dependency `initphp/encryption` moved to `^3.0` (was `^2.0`);
  it is only used by the test suite (applications that use the `CookieAdapter`
  install `initphp/encryption` themselves).
- Requires PHP `^8.2` (was `^8.1`); PHP 8.1 is no longer supported. CI now
  covers PHP 8.2, 8.3, 8.4 and 8.5.
- Code style moved to PER-CS (php-cs-fixer, shared `.php-cs-fixer.dist.php`,
  replacing the previous PSR-12 configuration); the codebase has been
  reformatted accordingly.
