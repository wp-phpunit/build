# WP PHPUNIT

The WordPress core PHPUnit test library made installable via Composer.

#### This repository is built from the official WordPress git mirror `git://develop.git.wordpress.org`. Please direct all issues to [wp-phpunit-build](https://github.com/wp-phpunit/wp-phpunit-build).


## Installation

```
composer require wp-phpunit/wp-phpunit --dev
```

## Configuration

Create a bootstrap file for your tests

```php
// e.g. tests/bootstrap.php

<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once getenv('WP_PHPUNIT__DIR') . '/includes/functions.php';

tests_add_filter('muplugins_loaded', function() {
    // manually load a plugin
});

require_once getenv('WP_PHPUNIT__DIR') . '/includes/bootstrap.php';
```

Create a wp-tests-config file

```
wget https://github.com/WordPress/wordpress-develop/raw/master/wp-tests-config-sample.php
```

Set the path to your `wp-tests-config.php` (file name does not need to be `wp-tests-config.php`)

```php
// e.g. in tests/bootstrap.php

<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

putenv('WP_PHPUNIT__TESTS_CONFIG=path/to/wp-tests-config.php');

// ... anywhere before this line

require_once getenv('WP_PHPUNIT__DIR') . '/includes/bootstrap.php';
```

or alternatively configure the path in your `phpunit.xml`!

```xml
<phpunit>
    <!-- ... -->

    <php>
        <env name="WP_PHPUNIT__TESTS_CONFIG" value="tests/wp-config.php" />
    </php>

    <!-- ... -->
</phpunit>
```