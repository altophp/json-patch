# JSON Pointers

JSON Patch paths follow RFC 6901. The empty string addresses the document root;
every other pointer starts with `/`.

```php
use Alto\JsonPatch\JsonPatch;

$document = [
    'users' => [
        ['name' => 'Alice'],
    ],
];

echo JsonPatch::get($document, '/users/0/name');
```

List indices are decimal integers without leading zeros. The `-` segment is
valid only for appending with an `add` operation.

## Escape property names

Within one segment, `~1` represents `/` and `~0` represents `~`:

```php
$document = [
    'a/b' => ['~key' => 'value'],
];

echo JsonPatch::get($document, '/a~1b/~0key');
```

Invalid escape sequences raise `JsonPatchException`.

## Inspect a pointer

```php
use Alto\JsonPatch\Pointer;

$pointer = Pointer::parse('/users/0/name');

$pointer->segments();          // ['users', '0', 'name']
$pointer->parent()->toString(); // /users/0
$pointer->last();              // name
$pointer->isRoot();            // false
```

Parsed pointers are cached internally. Application code normally uses
`JsonPatch` methods directly; `Pointer` is useful when another component must
inspect or compose the same path model.
