# Alto JSON Patch

Alto JSON Patch applies all six RFC 6902 operations and generates deterministic
patches between PHP values.

```php
use Alto\JsonPatch\JsonPatch;

$document = ['status' => 'draft'];
$patch = [
    ['op' => 'replace', 'path' => '/status', 'value' => 'published'],
];

$result = JsonPatch::apply($document, $patch);
echo $result['status'];
```

The result is `published`; the input remains unchanged. The package transforms
in-memory values and JSON strings. Persistence, authorization, version storage,
and conflict resolution remain application responsibilities.

## Documentation

- [Installation](installation.md)
- [Getting started](getting-started.md)
- [Operations](operations.md)
- [Pointers](pointers.md)
- [Diffing](diffing.md)
- [Errors](errors.md)
