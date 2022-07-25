# InitPHP Session Manager


## Requirements

- PHP 7.2 or later
- [InitPHP ParameterBag Library](https://github.com/InitPHP/ParameterBag)

## Installation

```
composer require initphp/sessions
```

## Usage

```php
require_once "vendor/autoload.php";
use InitPHP\Sessions\Facede\Session;

Session::start();

Session::set('username', 'admin')
        ->set('mail', 'admin@example.com');
// ...
```

## Credits

- [Muhammet ŞAFAK](https://github.com/muhammetsafak) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
