# Recipe: Flash Messages

A *flash* message is shown exactly once — typically after a redirect ("Saved!",
"Invalid login"). The package's [`pull()`](Reading-And-Writing-Values.md#pullstring-key-mixed-default--null-mixed)
method is purpose-built for this: it reads a value **and removes it** in one call.

## The simplest version

Set a flash before redirecting:

```php
use InitPHP\Sessions\Session;

Session::set('flash', 'Your profile was saved.');
header('Location: /profile');
exit;
```

Read it once on the next request:

```php
$message = Session::pull('flash'); // string|null, then gone

if ($message !== null) {
    echo '<div class="alert">' . htmlspecialchars($message) . '</div>';
}
```

After `pull()`, the key no longer exists — a refresh won't show it again.

## Typed flashes (success / error / …)

Group messages under a single key so multiple can queue up:

```php
function flash(string $type, string $message): void
{
    $all = Session::get('_flash', []);
    $all[$type][] = $message;
    Session::set('_flash', $all);
}

/** @return array<string, list<string>> */
function takeFlashes(): array
{
    return Session::pull('_flash', []); // read all, then clear
}
```

Usage:

```php
flash('success', 'Profile saved.');
flash('error', 'Avatar upload failed.');
header('Location: /profile');
exit;
```

Rendering:

```php
foreach (takeFlashes() as $type => $messages) {
    foreach ($messages as $message) {
        printf(
            '<div class="alert alert-%s">%s</div>',
            htmlspecialchars($type),
            htmlspecialchars($message)
        );
    }
}
```

## A tiny reusable helper

```php
final class Flash
{
    public static function add(string $type, string $message): void
    {
        $all = Session::get('_flash', []);
        $all[$type][] = $message;
        Session::set('_flash', $all);
    }

    /** @return array<string, list<string>> */
    public static function consume(): array
    {
        return Session::pull('_flash', []);
    }

    public static function has(): bool
    {
        return Session::has('_flash');
    }
}
```

```php
Flash::add('success', 'Welcome back!');
// ... after redirect ...
foreach (Flash::consume() as $type => $messages) { /* render */ }
```

## Why `pull()` and not `get()` + `remove()`

`pull()` is a single atomic step — read then delete — so you can't forget the
`remove()` and accidentally show a message twice. It also returns the default when
the key is absent, so the call site stays branch-light.

## See also

- [Reading & Writing Values](Reading-And-Writing-Values.md)
- [Recipe: Authentication](Recipe-Authentication.md)
