# MongoDB Adapter

`InitPHP\Sessions\Adapters\MongoDBAdapter` stores each session as a single
document in a MongoDB collection.

**Requirements:** `ext-mongodb`.

Each document looks like:

```json
{ "_id": "<session id>", "data": "<payload>", "timestamp": 1716998400 }
```

`timestamp` (Unix time) is refreshed on every write and used by garbage
collection.

## Options

| Option | Type | Default | Notes |
| --- | --- | --- | --- |
| `dsn` | string | — | **Required.** MongoDB connection string. |
| `collection` | string | — | **Required.** Namespace as `database.collection`. |

> The legacy option key `collation` is still accepted as an alias for
> `collection`, for backward compatibility with older configs.

Missing `dsn`/`collection` throws a
[`SessionInvalidArgumentException`](Exceptions.md); a failed connection throws a
[`SessionAdapterException`](Exceptions.md); a missing `ext-mongodb` throws a
[`SessionNotSupportedAdapter`](Exceptions.md).

## Usage

```php
use InitPHP\Sessions\Session;
use InitPHP\Sessions\Adapters\MongoDBAdapter;

$adapter = new MongoDBAdapter([
    'dsn'        => 'mongodb://127.0.0.1:27017',
    'collection' => 'app.sessions', // "database.collection"
]);

Session::createImmutable($adapter)->start();
```

## Garbage collection

`gc()` deletes documents whose `timestamp` is older than `gc_maxlifetime` and
returns the number removed. It runs on PHP's normal GC schedule, or manually:

```php
$deleted = $adapter->gc(3600);
```

For automatic expiry without relying on PHP's GC, you can additionally create a
[MongoDB TTL index](https://www.mongodb.com/docs/manual/core/index-ttl/) on a date
field — but note this adapter stores `timestamp` as an integer, so a TTL index
would require storing a `UTCDateTime` instead. The built-in `gc()` is the
supported path.

## Strict mode and lazy write

With `session.use_strict_mode` on, a session id counts as known only when a
document with that `_id` exists and its `timestamp` is less than
`session.gc_maxlifetime` seconds old; otherwise the id is replaced by a fresh one.
When the data did not change, `session.lazy_write` makes PHP call
`updateTimestamp()`, which sets `timestamp` to now on the existing document
instead of rewriting the payload; it never creates a document, so a session
destroyed meanwhile is not brought back. A driver error does not reject the id.
An id that is not valid UTF-8 cannot be a MongoDB string `_id`; it is treated as a
session that does not exist and never reaches the driver.

## Behaviour notes

- `write()` **upserts** by `_id`, so repeated writes to the same session update
  the existing document instead of inserting duplicates.
- `read()` returns the document's `data`, or `''` for an unknown session.
- `destroy()` deletes the single matching document.

## See also

- [Adapters overview](Adapters.md)
- [PDO Adapter](PDO-Adapter.md) — the SQL equivalent.
