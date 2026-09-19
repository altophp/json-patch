# ALTO JSON Patch

Strict RFC 6902 patching and deterministic diffs for PHP.

&nbsp; ![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-00B7FF?logoColor=00B7FF&labelColor=050608)
&nbsp; ![CI](https://img.shields.io/github/actions/workflow/status/altophp/json-patch/CI.yml?branch=main&label=Tests&labelColor=050608&color=00B7FF)
&nbsp; [![Packagist](https://img.shields.io/packagist/v/alto/json-patch?label=Packagist&labelColor=050608&color=00B7FF)](https://packagist.org/packages/alto/json-patch)
&nbsp; ![License](https://img.shields.io/github/license/altophp/json-patch?label=License&labelColor=050608&color=00B7FF)
&nbsp; [![GitHub Sponsors](https://img.shields.io/github/sponsors/smnandre?logo=githubsponsors&logoColor=00B7FF&label=%20Sponsor&labelColor=050608&color=00B7FF)](https://github.com/sponsors/smnandre)

ALTO JSON Patch applies all six JSON Patch operations and generates stable patches between PHP
values. Its identity-aware list diffing can express moves and nested changes instead of replacing
complete lists, keeping generated patches compact and readable.

```php
use Alto\JsonPatch\JsonPatch;

$before = ['status' => 'draft', 'tags' => ['php']];
$after = ['status' => 'published', 'tags' => ['php', 'json']];

$patch = JsonPatch::diff($before, $after);
$result = JsonPatch::apply($before, $patch);

assert($after === $result);
```

The package has no runtime dependencies beyond PHP's JSON extension. Its test suite includes the
RFC 6902 compliance corpus, and the codebase is analyzed at PHPStan level 10.

## Installation

Install ALTO JSON Patch with Composer:

```bash
composer require alto/json-patch
```

ALTO JSON Patch requires PHP 8.3 or later and the JSON extension. The extension ships with PHP.

## Quick Start

Apply a sequence of operations to an in-memory value:

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
echo json_encode($result, JSON_THROW_ON_ERROR);
```

The original value is unchanged. Operations run in order, and each operation sees the result of
the preceding one. The example prints:

```text
{"user":{"name":"Alice","role":"admin"},"status":"published"}
```

## Documentation

- [Installation](docs/installation.md)
- [Getting started](docs/getting-started.md)
- [Operations](docs/operations.md)
- [Pointers](docs/pointers.md)
- [Diffing](docs/diffing.md)
- [Errors](docs/errors.md)

The [documentation index](docs/index.md) lists these pages in site navigation
order.

## Operations

`JsonPatch::apply()` supports every RFC 6902 operation:

| Operation | Effect |
| --- | --- |
| `add` | Insert or replace a value |
| `remove` | Delete an existing value |
| `replace` | Replace an existing value |
| `move` | Move a value to another path |
| `copy` | Copy a value to another path |
| `test` | Assert that a value matches |

Use `JsonPatch::applyJson()` to work directly with JSON strings. Read
[Operations](docs/operations.md) for ordered semantics, validation, and JSON
handling. [Errors](docs/errors.md) covers runtime failures and recovery.

## Generating Patches

Generate the operations needed to transform one state into another:

```php
$patch = JsonPatch::diff(
    ['version' => 1, 'status' => 'draft'],
    ['version' => 2, 'status' => 'published'],
);
```

Object keys are compared recursively. Lists use a longest common subsequence by default, producing
stable `add` and `remove` operations while preserving unchanged items.

## Identity-aware Lists

Configure an identity key to express item moves and nested changes:

```php
use Alto\JsonPatch\DiffOptions;

$options = new DiffOptions(
    listIdentityByPointer: ['/items' => 'id'],
);

$patch = JsonPatch::diff($before, $after, $options);
```

Read [Generating patches](docs/diffing.md) for list strategies and their fallback behavior.

## JSON Pointers

Patch paths follow RFC 6901. Use `JsonPatch::get()` and `JsonPatch::test()` to inspect values at a
path, or `Pointer` when another component needs to parse and compose paths.

```php
$name = JsonPatch::get($document, '/user/name');
$isAdmin = JsonPatch::test($document, '/user/role', 'admin');
```

Read [JSON Pointers](docs/pointers.md) for root paths, list indices, and escaping. The
[complete guide](docs/index.md) also covers installation and a first end-to-end patch.

## Contributing

Contributions of all kinds are welcome. Visit the
[project on GitHub](https://github.com/altophp/json-patch) to
[report a bug](https://github.com/altophp/json-patch/issues/new),
[suggest a feature](https://github.com/altophp/json-patch/issues/new), or
[open a pull request](https://github.com/altophp/json-patch/pulls).

Before submitting code, run:

```bash
# Runs PHP CS Fixer, PHPStan, and PHPUnit
composer qa
```

Changes to public behavior should include tests and documentation.

## Support

ALTO JSON Patch is open source. You can support its continued development through
[GitHub Sponsors](https://github.com/sponsors/smnandre).

Sharing this package with others or
[starring it on GitHub](https://github.com/altophp/json-patch) is also much
appreciated.

## License

ALTO JSON Patch is released by [ALTO PHP](https://altophp.com) under the
[MIT License](LICENSE).
