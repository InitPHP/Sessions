# `initphp/sessions` — Developer Documentation

This directory is the in-repo reference manual for the `initphp/sessions`
package. The top-level [README](../README.md) is the front door; everything here
is detail.

## Contents

| File | What it covers |
| --- | --- |
| [01 — Getting Started](./01-getting-started.md) | Installation, the facade, your first session, the two call styles. |
| [02 — Session API](./02-session-api.md) | Every method on the `Session` facade, with examples. |
| [03 — Adapters](./03-adapters.md) | Each bundled save handler: requirements, every option, examples. |
| [04 — Custom Adapters](./04-custom-adapters.md) | Build your own save handler on top of `AbstractAdapter`. |
| [05 — Error Handling](./05-error-handling.md) | The exception hierarchy and what triggers each type. |

## Conventions in These Docs

- Every PHP code block is written against the current `src/`. Copy a block,
  `require 'vendor/autoload.php'`, and it runs.
- "Payload" means the raw, PHP-session-encoded string PHP hands to a save
  handler's `write()` — not your individual `$_SESSION` values.
- Examples assume `use InitPHP\Sessions\Session;` and the relevant adapter
  `use` statement.
