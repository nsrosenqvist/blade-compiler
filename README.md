Blade Compiler
==============

This is a simple PHP class that wraps Laravel's Blade compiler into a reusable component. This abstraction makes it very convenient to use the Blade templating engine in other projects that aren't built with Laravel.

**Sample code**
```php
use NSRosenqvist\Blade\Compiler;

$cacheDir = "storage/cache/views";
$baseDirs = [
    'app/views',
    'module/views',
];

$blade = new Compiler($cacheDir, $baseDirs);

$index = $blade->render('index')
// Only works when baseDirs are set (note that just
// 'index.blade.php' wouldn't work since Blade would
// interpret that as 'index/blade/php')

$index = $blade->render('/path/to/index.blade.php')
// Absolute paths always works
```
