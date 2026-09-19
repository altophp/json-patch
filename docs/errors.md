# Errors

Malformed operations, invalid pointers, missing paths, incompatible containers,
and failed preconditions raise package exceptions. Catch the common parent when
the same recovery applies to every patch failure:

```php
use Alto\JsonPatch\Exception\JsonPatchException;
use Alto\JsonPatch\JsonPatch;

try {
    $result = JsonPatch::apply($document, $patch);
} catch (JsonPatchException $error) {
    echo $error->getMessage();
}
```

## Failure categories

| Exception | Meaning |
| --- | --- |
| `InvalidOperationException` | An operation is unsupported, malformed, or uses an invalid list index. |
| `PathNotFoundException` | A required object member or list item does not exist. |
| `TypeMismatchException` | A pointer crosses a value that is not the required container type. |
| `TestFailedException` | A `test` operation did not match the current value. |
| `JsonPatchException` | Invalid JSON, an invalid JSON Pointer, an invalid patch list, or another shared package boundary failed. |

Every specific exception extends `JsonPatchException`. Error messages include
the operation index or path when that context exists.

## Recovery

Before retrying, correct the operation or pointer and ensure its parent
container exists. For a failed `test`, decide whether the caller should reload
the latest state or reject the requested change. Removing a failed precondition
can overwrite a concurrent update.

`JsonPatch::validate()` checks operation structure without reading a document.
An empty validation result does not prove that paths exist or that a `test`
will pass. Apply the patch to the current document and handle runtime failures.

The input value remains unchanged when an operation fails, and no partial
result is returned. Persistence, retries, and transaction boundaries remain
application responsibilities.
