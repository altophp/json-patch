# Generating patches

`JsonPatch::diff()` returns operations that transform one value into another.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\JsonPatch\JsonPatch;

$before = ['version' => 1, 'status' => 'draft'];
$after = ['version' => 2, 'status' => 'published', 'author' => 'Alice'];

$patch = JsonPatch::diff($before, $after);
$result = JsonPatch::apply($before, $patch);

echo json_encode($patch, JSON_THROW_ON_ERROR), "\n";
echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
printf("target reached=%s\n", $after === $result ? 'yes' : 'no');
```

Output:

```text
[{"op":"replace","path":"\/version","value":2},{"op":"replace","path":"\/status","value":"published"},{"op":"add","path":"\/author","value":"Alice"}]
{"version":2,"status":"published","author":"Alice"}
target reached=yes
```

`diff($before, $after)` produces the forward transformation. To generate a
reverse patch, swap those arguments and apply the result to `$after`.

Object keys are removed, added, or recursively changed. Scalar values and
changes between object and list shapes produce `replace` operations.

## Lists

Lists use a longest common subsequence by default. This produces stable
`remove` and `add` operations while preserving unchanged elements.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\JsonPatch\JsonPatch;
use Alto\JsonPatch\DiffOptions;

$before = ['tags' => ['php', 'json']];
$after = ['tags' => ['php', 'api']];
$options = new DiffOptions(useLcs: true);
$patch = JsonPatch::diff($before, $after, $options);
echo json_encode($patch, JSON_THROW_ON_ERROR), "\n";
echo json_encode(JsonPatch::apply($before, $patch), JSON_THROW_ON_ERROR), "\n";
```

Output:

```text
[{"op":"remove","path":"\/tags\/1"},{"op":"add","path":"\/tags\/1","value":"api"}]
{"tags":["php","api"]}
```

Set `useLcs` to `false` to replace a changed list as one value.

## Lists with identities

Identity-based diffing can express moves and nested item changes:

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\JsonPatch\JsonPatch;
use Alto\JsonPatch\DiffOptions;

$before = ['items' => [
    ['id' => 'a', 'quantity' => 1],
    ['id' => 'b', 'quantity' => 2],
]];

$after = ['items' => [
    ['id' => 'b', 'quantity' => 3],
    ['id' => 'c', 'quantity' => 1],
]];

$options = new DiffOptions(
    listIdentityByPointer: ['/items' => 'id'],
);

$patch = JsonPatch::diff($before, $after, $options);
echo json_encode($patch, JSON_THROW_ON_ERROR), "\n";
echo json_encode(JsonPatch::apply($before, $patch), JSON_THROW_ON_ERROR), "\n";
```

Output:

```text
[{"op":"remove","path":"\/items\/0"},{"op":"replace","path":"\/items\/0\/quantity","value":3},{"op":"add","path":"\/items\/-","value":{"id":"c","quantity":1}}]
{"items":[{"id":"b","quantity":3},{"id":"c","quantity":1}]}
```

Every item in both lists must be an object-like associative array with a
unique string or integer identity. Otherwise, diffing falls back to the normal
list strategy.

Diffing stops at a nesting depth of 512 and raises `JsonPatchException` rather
than recursing without a bound.
