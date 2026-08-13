# Generating patches

`JsonPatch::diff()` returns operations that transform one value into another.

```php
use Alto\JsonPatch\JsonPatch;

$before = ['version' => 1, 'status' => 'draft'];
$after = ['version' => 2, 'status' => 'published', 'author' => 'Alice'];

$patch = JsonPatch::diff($before, $after);
$result = JsonPatch::apply($before, $patch);

assert($after === $result);
```

Object keys are removed, added, or recursively changed. Scalar values and
changes between object and list shapes produce `replace` operations.

## Lists

Lists use a longest common subsequence by default. This produces stable
`remove` and `add` operations while preserving unchanged elements.

```php
use Alto\JsonPatch\DiffOptions;

$patch = JsonPatch::diff(
    ['tags' => ['php', 'json']],
    ['tags' => ['php', 'api']],
    new DiffOptions(useLcs: true),
);
```

Set `useLcs` to `false` to replace a changed list as one value.

## Lists with identities

Identity-based diffing can express moves and nested item changes:

```php
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
```

Every item in both lists must be an object-like associative array with a
unique string or integer identity. Otherwise, diffing falls back to the normal
list strategy.

Diffing stops at a nesting depth of 512 and raises `JsonPatchException` rather
than recursing without a bound.
