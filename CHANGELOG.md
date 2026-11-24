# Changelog

All notable changes to `initphp/sessions` are documented here. The format is based
on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- All bundled adapters (`FileAdapter`, `PDOAdapter`, `RedisAdapter`,
  `MemCacheAdapter`, `MongoDBAdapter`, `CookieAdapter`) now support
  `session.use_strict_mode` and `session.lazy_write`. `AbstractAdapter`
  implements `SessionUpdateTimestampHandlerInterface`: `validateId()` tells PHP
  whether a client-supplied id belongs to a stored, unexpired session (an unknown
  id is replaced with a fresh one), and `updateTimestamp()` renews the lifetime
  of an unchanged session (file mtime, row/document timestamp, key TTL, cookie
  expiry) so active sessions do not expire early.
- `AbstractAdapter` provides working defaults for both methods (`validateId()`
  treats a non-empty `read()` as "exists", `updateTimestamp()` calls `write()`),
  so existing third-party adapters that extend it gain strict mode without
  changes; `AdapterInterface` is unchanged. A third-party adapter that already
  declares a method named `validateId` or `updateTimestamp` with a different
  signature must align it.

### Fixed

- `Manager::setID()` (and `Session::setID()`) returned `false` even when PHP
  accepted the id. It now returns `true` when the id was applied and `false`
  when PHP refused it (for example, a session is already active).
- `FileAdapter` no longer builds file names from unvalidated session ids: an id
  containing anything other than letters, digits, `,`, `-` and `_` (such as a
  path separator or `..`) is treated as a session that does not exist and is
  never read, written or deleted.

### Changed

- With `session.use_strict_mode` on, a session id the store does not know (or
  whose session expired) is now replaced by a fresh id instead of being
  accepted. Applications that relied on the previous behaviour of adopting any
  client-supplied id must turn strict mode off.

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
