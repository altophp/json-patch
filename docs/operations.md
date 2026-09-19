# Operations

`JsonPatch::apply()` accepts any PHP value as the document and a list of RFC
6902 operation arrays. It returns the transformed value.

## Apply all six operations

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Alto\JsonPatch\JsonPatch;

$document = [
    'name' => 'Draft',
    'tags' => ['php'],
    'metadata' => ['owner' => 'alice'],
];

$patch = [
    ['op' => 'test', 'path' => '/name', 'value' => 'Draft'],
    ['op' => 'replace', 'path' => '/name', 'value' => 'Published'],
    ['op' => 'add', 'path' => '/tags/-', 'value' => 'json'],
    ['op' => 'copy', 'from' => '/metadata/owner', 'path' => '/author'],
    ['op' => 'move', 'from' => '/metadata/owner', 'path' => '/owner'],
    ['op' => 'remove', 'path' => '/metadata'],
];

$result = JsonPatch::apply($document, $patch);
echo json_encode($result, JSON_THROW_ON_ERROR), "\n";
```

Output:

```text
{"name":"Published","tags":["php","json"],"author":"alice","owner":"alice"}
```

- `add` inserts or replaces an object member and inserts into a list. The `-`
  index appends to a list.
- `remove` deletes an existing value. The document root cannot be removed.
- `replace` requires an existing target, except that the empty root pointer
  replaces the complete document.
- `move` removes an existing value and adds it elsewhere. A parent cannot move
  into its own descendant.
- `copy` reads an existing value and adds it elsewhere.
- `test` uses strict recursive equality and aborts on mismatch.

## Inspect and validate

```php
$name = JsonPatch::get($result, '/name');
$isPublished = JsonPatch::test($result, '/name', 'Published');
$errors = JsonPatch::validate($patch);
```

`validate()` checks operation structure without reading a document. An empty
result means structurally valid; paths may still be missing when applied.

## JSON strings

```php
$json = JsonPatch::applyJson(
    '{"status":"draft"}',
    '[{"op":"replace","path":"/status","value":"published"}]',
);
```

`applyJson()` decodes JSON into associative arrays and returns compact JSON
with unescaped Unicode and slashes. Invalid JSON and a non-list patch raise
`JsonPatchException`. See [Errors](errors.md) for the failure categories and
recovery boundaries shared by array and JSON-string entry points.
