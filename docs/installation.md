# Installation

Alto JSON Patch requires PHP 8.3 or later and the JSON extension.

```bash
composer require alto/json-patch
```

## Verify the installation

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\JsonPatch\JsonPatch;

$result = JsonPatch::apply(
    ['enabled' => false],
    [['op' => 'replace', 'path' => '/enabled', 'value' => true]],
);

var_export($result);
```

The script prints `array ('enabled' => true,)` with PHP's normal multiline
formatting.
