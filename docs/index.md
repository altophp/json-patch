# Alto JSON Patch

Alto JSON Patch applies RFC 6902 operations and generates deterministic
patches between PHP values.

```php
use Alto\JsonPatch\JsonPatch;

$document = ['status' => 'draft'];
$patch = [
    ['op' => 'replace', 'path' => '/status', 'value' => 'published'],
];

$result = JsonPatch::apply($document, $patch);
```

## Introduction

- [Installation](installation.md): install the package and verify the runtime.
- [Getting started](getting-started.md): apply and generate a first patch.

## Patching

- [Applying](applying.md): use the six RFC 6902 operations and handle failures.
- [Diffing](diffing.md): generate stable object and list changes.
- [Pointers](pointers.md): address document values with RFC 6901 paths.

The package transforms in-memory values and JSON strings. Persistence,
authorization, version storage, and conflict resolution remain application
responsibilities.
