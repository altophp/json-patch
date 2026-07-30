# ALTO \ JSON Patch

A strict, auditable [JSON Patch](https://en.wikipedia.org/wiki/JSON_Patch) implementation for PHP 8.3+. This library handles two concerns with precision:

1. **Apply**: A deterministic **[RFC 6902](https://datatracker.ietf.org/doc/html/rfc6902)** engine that replays patches exactly.
2. **Diff**: A smart diff generator that produces stable, readable patches.

Built for systems where change history matters.

---

&nbsp; [![PHP Version](https://img.shields.io/badge/PHP-8.3+-ffefdf?logoColor=white&labelColor=000)](https://github.com/altophp/json-patch)
&nbsp; [![CI](https://img.shields.io/github/actions/workflow/status/altophp/json-patch/CI.yml?branch=main&label=Tests&logoColor=white&logoSize=auto&labelColor=000&color=ffefdf)](https://github.com/altophp/json-patch/actions)
&nbsp; [![Packagist Version](https://img.shields.io/packagist/v/alto/json-patch?label=Stable&logoColor=white&logoSize=auto&labelColor=000&color=ffefdf)](https://packagist.org/packages/alto/json-patch)
&nbsp; [![PHP Version](https://img.shields.io/badge/PHPUnit-100%25-ffefdf?logoColor=white&labelColor=000)](https://github.com/altophp/json-patch)
&nbsp; [![PHP Version](https://img.shields.io/badge/PHPStan-LVL%2010-ffefdf?logoColor=white&labelColor=000)](https://github.com/altophp/json-patch)
&nbsp; [![License](https://img.shields.io/github/license/altophp/json-patch?label=License&logoColor=white&logoSize=auto&labelColor=000&color=ffefdf)](./LICENSE)

* **Pure PHP**: Tiny surface area, no heavy dependencies.
* **Strict Types**: Built for PHP 8.3+ with strict typing.
* **Deterministic**: Error model designed for auditability.
* **Smart Diffing**: Supports standard list replacement or smart "by-id" list diffing for readable patches.

## Installation

```bash
composer require alto/json-patch
```

## Why Alto JSON Patch?

**For audit logs**: Deterministic apply means you can verify patch integrity. Store the parent hash, the patch, and the
result hash. Replaying the patch will always produce the same result.

**For readable diffs**: Generate clean patches that humans can review. Optional identity-based list diffing produces
granular operations instead of replacing entire arrays.

**For reliability**: Pure PHP with strict types. No magic, no surprises.

## Quick Start

```php
use Alto\JsonPatch\JsonPatch;

$document = [
    'user' => ['name' => 'Alice', 'role' => 'editor'],
    'status' => 'draft',
];

$patch = [
    ['op' => 'replace', 'path' => '/user/role', 'value' => 'admin'],
    ['op' => 'replace', 'path' => '/status', 'value' => 'published'],
];

$result = JsonPatch::apply($document, $patch);
// ['user' => ['name' => 'Alice', 'role' => 'admin'], 'status' => 'published']
```

## Generate Patches

Create patches automatically by diffing two states:

```php
$before = ['version' => 1, 'status' => 'draft'];
$after = ['version' => 2, 'status' => 'published', 'author' => 'Alice'];

$patch = JsonPatch::diff($before, $after);
// [
//     ['op' => 'replace', 'path' => '/version', 'value' => 2],
//     ['op' => 'replace', 'path' => '/status', 'value' => 'published'],
//     ['op' => 'add', 'path' => '/author', 'value' => 'Alice'],
// ]
```

## Smart List Diffing

By default, lists are replaced entirely when they differ. For granular control, use identity-based diffing:

```php
use Alto\JsonPatch\DiffOptions;

$before = [
    'items' => [
        ['id' => 'a', 'qty' => 1],
        ['id' => 'b', 'qty' => 2],
    ],
];

$after = [
    'items' => [
        ['id' => 'b', 'qty' => 3],  // Modified and reordered
        ['id' => 'c', 'qty' => 1],  // Added
    ],
];

$options = new DiffOptions(['/items' => 'id']);
$patch = JsonPatch::diff($before, $after, $options);
// Generates move, add, remove, and replace operations for individual items
```

This produces readable patches where reviewers can see exactly which items changed.

## Utility Methods

```php
// Get a value at a JSON pointer path
$name = JsonPatch::get($document, '/user/name');

// Test if a value matches (returns bool)
$isAdmin = JsonPatch::test($document, '/user/role', 'admin');

// Validate patch structure without applying
$errors = JsonPatch::validate($patch);
```

## Audit Trail Example

```php
class ChangeLog
{
    public function recordChange(array $before, array $after): void
    {
        $patch = JsonPatch::diff($before, $after);

        $this->store([
            'parent_hash' => hash('sha256', json_encode($before)),
            'patch' => $patch,
            'result_hash' => hash('sha256', json_encode($after)),
            'timestamp' => time(),
        ]);
    }

    public function verifyIntegrity(string $recordId): bool
    {
        $record = $this->fetch($recordId);
        $parent = $this->reconstructState($record['parent_hash']);

        $result = JsonPatch::apply($parent, $record['patch']);
        $computedHash = hash('sha256', json_encode($result));

        return $computedHash === $record['result_hash'];
    }
}
```

## Supported Operations

All RFC 6902 operations:

- `add`: Add a value at a path
- `remove`: Remove a value at a path
- `replace`: Replace a value at a path
- `move`: Move a value from one path to another
- `copy`: Copy a value from one path to another
- `test`: Assert a value matches (useful for conditional patches)

## Error Handling

Operations throw `JsonPatchException` with clear messages:

```php
try {
    JsonPatch::apply($doc, $patch);
} catch (JsonPatchException $e) {
    // "Operation 0 (replace): path '/missing/path' not found."
    // "Operation 1 (add): invalid path '/items/-1'."
}
```

## Advanced Usage

### Float Comparison
`JsonPatch` uses strict equality (`===`) for values. Be aware that `json_decode` may treat numbers differently depending on flags.
For example, `1.0` (float) is not strictly equal to `1` (int). Ensure your input documents use consistent types if strict equality is required.

## Limitations

### `applyJson`: Empty Object vs Array

When using `JsonPatch::applyJson()`, the underlying `json_decode` converts empty JSON objects `{}` into empty PHP arrays
`[]`.
Since PHP does not distinguish between empty associative arrays (objects) and empty indexed arrays (lists), an input of
`{"key": {}}` may result in `{"key": []}` after a round-trip.
If strictly preserving `{}` vs `[]` is critical, consider using `apply()` with pre-decoded structures where you can
control the object mapping (e.g. `json_decode($json, false)` for `stdClass`).

## API Reference

### `JsonPatch`

| Method                                                                  | Description                              |
|-------------------------------------------------------------------------|------------------------------------------|
| `apply(array $doc, array $patch): array`                                | Apply a patch to a document              |
| `applyJson(string $docJson, string $patchJson, int $flags = 0): string` | Apply patch to JSON string               |
| `diff(array $from, array $to, ?DiffOptions $opts = null): array`        | Generate patch from two states           |
| `get(array $doc, string $path): mixed`                                  | Get value at JSON pointer path           |
| `test(array $doc, string $path, mixed $value): bool`                    | Test if value matches at path            |
| `validate(array $patch): array`                                         | Validate patch structure, returns errors |

### `DiffOptions`

Configure identity-based list diffing:

```php
$options = new DiffOptions([
    '/users' => 'id',        // Use 'id' field for /users array
    '/items' => 'sku',       // Use 'sku' field for /items array
]);
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
````
