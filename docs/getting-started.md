# Getting started

After [installation](installation.md), save this as `patch.php` beside `vendor`
and run `php patch.php`. It applies two operations to an in-memory document.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\JsonPatch\JsonPatch;

$before = [
    'user' => ['name' => 'Alice', 'role' => 'editor'],
    'status' => 'draft',
];

$patch = [
    ['op' => 'replace', 'path' => '/user/role', 'value' => 'admin'],
    ['op' => 'replace', 'path' => '/status', 'value' => 'published'],
];

$after = JsonPatch::apply($before, $patch);
echo json_encode($after, JSON_THROW_ON_ERROR), "\n";
printf("original status=%s\n", $before['status']);
```

Output:

```text
{"user":{"name":"Alice","role":"admin"},"status":"published"}
original status=draft
```

The original value is unchanged. Operations run in order, and each subsequent
operation sees the result of the preceding one. The package does not save this
result to a database or file.

Continue with [Operations](operations.md) for all six operations,
[Pointers](pointers.md) for property names containing slash or tilde, or
[Diffing](diffing.md) to generate and replay a patch.

## Generate the reverse transformation

To transform the result back into the original value, use
`JsonPatch::diff($after, $before)`. The opposite argument order generates the
forward transformation. See the complete [diff-and-replay example](diffing.md).
