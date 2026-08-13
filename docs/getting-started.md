# Getting started

Apply a list of operations to an in-memory document:

```php
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
```

The original value is unchanged. Operations run in order, and each subsequent
operation sees the result of the preceding one.

## Generate the reverse transformation

```php
$generated = JsonPatch::diff($before, $after);
$replayed = JsonPatch::apply($before, $generated);

assert($after === $replayed);
```

Generated operations use JSON Pointer paths. Continue with [Applying](applying.md)
for all operations, [Diffing](diffing.md) for list strategies, and
[Pointers](pointers.md) for path escaping.
