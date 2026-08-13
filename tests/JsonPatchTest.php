<?php

declare(strict_types=1);

/*
 * This file is part of the ALTO library.
 *
 * © 2026-present Simon André
 *
 * For full copyright and license information, please see
 * the LICENSE file distributed with this source code.
 */

namespace Alto\JsonPatch\Tests;

use Alto\JsonPatch\DiffOptions;
use Alto\JsonPatch\Exception\JsonPatchException;
use Alto\JsonPatch\Exception\TestFailedException;
use Alto\JsonPatch\JsonPatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(JsonPatch::class)]
#[CoversClass(DiffOptions::class)]
#[CoversClass(JsonPatchException::class)]
#[CoversClass(TestFailedException::class)]
final class JsonPatchTest extends TestCase
{
    // From ApplyTest
    public function testApplyAddReplaceRemove(): void
    {
        $doc = [
            'patient' => ['id' => 'p1', 'name' => 'Alice'],
            'results' => ['bac' => 0.42],
        ];

        $patch = [
            ['op' => 'replace', 'path' => '/patient/name', 'value' => 'Alice Dupont'],
            ['op' => 'add', 'path' => '/status', 'value' => 'validated'],
            ['op' => 'remove', 'path' => '/results/bac'],
        ];

        $out = JsonPatch::apply($doc, $patch);

        self::assertSame([
            'patient' => ['id' => 'p1', 'name' => 'Alice Dupont'],
            'results' => [],
            'status' => 'validated',
        ], $out);
    }

    public function testApplyTest(): void
    {
        $doc = ['a' => 1];

        JsonPatch::apply($doc, [
            ['op' => 'test', 'path' => '/a', 'value' => 1],
        ]);

        $this->expectException(TestFailedException::class);

        JsonPatch::apply($doc, [
            ['op' => 'test', 'path' => '/a', 'value' => 2],
        ]);
    }

    public function testApplyArrayAddWithDashAppends(): void
    {
        $doc = ['items' => [1, 2]];

        $out = JsonPatch::apply($doc, [
            ['op' => 'add', 'path' => '/items/-', 'value' => 3],
        ]);

        self::assertSame(['items' => [1, 2, 3]], $out);
    }

    // From EdgeCaseTest
    public function testMoveToChildFails(): void
    {
        $doc = ['a' => ['b' => 1]];
        $patch = [
            ['op' => 'move', 'from' => '/a', 'path' => '/a/b'],
        ];

        // Accessing 'a' on empty list [] (after remove) triggers invalid index
        $this->expectException(JsonPatchException::class);
        JsonPatch::apply($doc, $patch);
    }

    public function testAddAppendToNonListSucceedsAsKey(): void
    {
        $doc = ['a' => ['foo' => 'bar']];
        // '-' is only valid for array (list) to append, but valid as key for object
        $patch = [
            ['op' => 'add', 'path' => '/a/-', 'value' => 'baz'],
        ];

        $result = JsonPatch::apply($doc, $patch);
        self::assertSame(['a' => ['foo' => 'bar', '-' => 'baz']], $result);
    }

    public function testRemoveRootFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'remove', 'path' => ''],
        ];

        $this->expectException(JsonPatchException::class);
        JsonPatch::apply($doc, $patch);
    }

    public function testReplaceRoot(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'replace', 'path' => '', 'value' => ['b' => 2]],
        ];

        $result = JsonPatch::apply($doc, $patch);
        self::assertSame(['b' => 2], $result);
    }

    public function testAddRoot(): void
    {
        // RFC 6902: "add" to root replaces the document
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'add', 'path' => '', 'value' => 'foo'],
        ];

        $result = JsonPatch::apply($doc, $patch);
        self::assertSame('foo', $result);
    }

    public function testPointerTrailingSlashIsValidKey(): void
    {
        // Path "/a/" means key "a" then key "" (empty string)
        $doc = ['a' => ['' => 'found']];
        $patch = [
            ['op' => 'replace', 'path' => '/a/', 'value' => 'replaced'],
        ];

        $result = JsonPatch::apply($doc, $patch);
        self::assertSame(['a' => ['' => 'replaced']], $result);
    }

    public function testArrayIndexLeadingZerosShouldFail(): void
    {
        // RFC 6901 forbids leading zeros in array indices
        $doc = ['list' => [0, 1, 2]];
        $patch = [
            ['op' => 'replace', 'path' => '/list/01', 'value' => 99],
        ];

        $this->expectException(JsonPatchException::class);
        JsonPatch::apply($doc, $patch);
    }

    public function testInvalidPointerEscapeSequence(): void
    {
        // RFC 6901: only ~0 and ~1 are valid. ~2 is invalid.
        $doc = ['foo' => 'bar'];
        $patch = [
            ['op' => 'add', 'path' => '/~2', 'value' => 'x'],
        ];

        $this->expectException(JsonPatchException::class);
        JsonPatch::apply($doc, $patch);
    }

    // From DiffObjectsTest
    public function testDiffObjects(): void
    {
        $from = [
            'patient' => ['id' => 'p1', 'name' => 'Alice'],
            'status' => 'draft',
        ];

        $to = [
            'patient' => ['id' => 'p1', 'name' => 'Alice Dupont'],
            'status' => 'validated',
            'meta' => ['version' => 2],
        ];

        $patch = JsonPatch::diff($from, $to);
        $out = JsonPatch::apply($from, $patch);

        self::assertSame($to, $out);
    }

    // From DiffListsByIdTest
    public function testDiffListByIdGeneratesMovesAndNestedChanges(): void
    {
        $from = [
            'items' => [
                ['id' => 'a', 'qty' => 1],
                ['id' => 'b', 'qty' => 1],
                ['id' => 'c', 'qty' => 1],
            ],
        ];

        $to = [
            'items' => [
                ['id' => 'b', 'qty' => 2],
                ['id' => 'a', 'qty' => 1],
                ['id' => 'd', 'qty' => 1],
            ],
        ];

        $opts = new DiffOptions([
            '/items' => 'id',
        ]);

        $patch = JsonPatch::diff($from, $to, $opts);
        $out = JsonPatch::apply($from, $patch);

        self::assertSame($to, $out);
    }

    // From SecurityTest
    public function testDeeplyNestedStructure(): void
    {
        $depth = 100;
        $doc = 1;
        for ($i = 0; $i < $depth; ++$i) {
            $doc = ['a' => $doc];
        }

        // Access deep value
        $path = str_repeat('/a', $depth);
        $patch = [
            ['op' => 'replace', 'path' => $path, 'value' => 'ok'],
        ];

        $result = JsonPatch::apply($doc, $patch);

        // verify
        self::assertIsArray($result);
        $current = $result;
        for ($i = 0; $i < $depth; ++$i) {
            self::assertIsArray($current);
            $current = $current['a'];
        }
        self::assertSame('ok', $current);
    }

    public function testLargeArrayOperations(): void
    {
        $count = 1000;
        $doc = range(0, $count - 1);

        $patch = [
            ['op' => 'remove', 'path' => '/500'], // remove middle
            ['op' => 'add', 'path' => '/-', 'value' => 'end'], // append
        ];

        $result = JsonPatch::apply($doc, $patch);

        self::assertIsArray($result);
        self::assertCount($count, $result); // removed one, added one
        self::assertSame(501, $result[500]); // shifted
        self::assertSame('end', $result[$count - 1]);
    }

    public function testCopyOperation(): void
    {
        $doc = ['a' => ['value' => 42], 'b' => null];

        $patch = [
            ['op' => 'copy', 'from' => '/a', 'path' => '/b'],
        ];

        $result = JsonPatch::apply($doc, $patch);

        self::assertSame(['a' => ['value' => 42], 'b' => ['value' => 42]], $result);
    }

    public function testCopyOperationToNewKey(): void
    {
        $doc = ['source' => 'hello'];

        $patch = [
            ['op' => 'copy', 'from' => '/source', 'path' => '/destination'],
        ];

        $result = JsonPatch::apply($doc, $patch);

        self::assertSame(['source' => 'hello', 'destination' => 'hello'], $result);
    }

    public function testCopyMissingFromFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'copy', 'path' => '/b'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("Missing 'from'");
        JsonPatch::apply($doc, $patch);
    }

    public function testApplyJsonBasic(): void
    {
        $docJson = '{"name": "Alice", "age": 30}';
        $patchJson = '[{"op": "replace", "path": "/age", "value": 31}]';

        $result = JsonPatch::applyJson($docJson, $patchJson);

        self::assertSame('{"name":"Alice","age":31}', $result);
    }

    public function testApplyJsonInvalidDocument(): void
    {
        $docJson = 'not valid json';
        $patchJson = '[]';

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Invalid document JSON');
        JsonPatch::applyJson($docJson, $patchJson);
    }

    public function testApplyJsonInvalidPatch(): void
    {
        $docJson = '{"a": 1}';
        $patchJson = 'not valid json';

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Invalid patch JSON');
        JsonPatch::applyJson($docJson, $patchJson);
    }

    public function testApplyJsonPatchMustBeList(): void
    {
        $docJson = '{"a": 1}';
        $patchJson = '{"op": "add", "path": "/b", "value": 2}';

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Patch JSON must be a list');
        JsonPatch::applyJson($docJson, $patchJson);
    }

    public function testPatchMissingOpFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['path' => '/a', 'value' => 2],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("missing valid 'op'");
        JsonPatch::apply($doc, $patch);
    }

    public function testPatchMissingPathFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'add', 'value' => 2],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("missing valid 'path'");
        JsonPatch::apply($doc, $patch);
    }

    public function testPatchInvalidOpTypeFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 123, 'path' => '/a', 'value' => 2],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("missing valid 'op'");
        JsonPatch::apply($doc, $patch);
    }

    public function testPatchUnsupportedOpFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'unsupported', 'path' => '/a'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Unsupported operation');
        JsonPatch::apply($doc, $patch);
    }

    public function testPatchMustBeListOfObjects(): void
    {
        $doc = ['a' => 1];
        $patch = ['not an object'];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('A patch must be a list of objects');
        JsonPatch::apply($doc, $patch);
    }

    public function testAddMissingValueFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'add', 'path' => '/b'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("Missing 'value'");
        JsonPatch::apply($doc, $patch);
    }

    public function testReplaceMissingValueFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'replace', 'path' => '/a'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("Missing 'value'");
        JsonPatch::apply($doc, $patch);
    }

    public function testMoveMissingFromFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'move', 'path' => '/b'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("Missing 'from'");
        JsonPatch::apply($doc, $patch);
    }

    public function testEscapeSequenceTildeInKey(): void
    {
        // Key containing ~ should be encoded as ~0
        $doc = ['a~b' => 'value'];
        $patch = [
            ['op' => 'replace', 'path' => '/a~0b', 'value' => 'new'],
        ];

        $result = JsonPatch::apply($doc, $patch);

        self::assertSame(['a~b' => 'new'], $result);
    }

    public function testEscapeSequenceSlashInKey(): void
    {
        // Key containing / should be encoded as ~1
        $doc = ['a/b' => 'value'];
        $patch = [
            ['op' => 'replace', 'path' => '/a~1b', 'value' => 'new'],
        ];

        $result = JsonPatch::apply($doc, $patch);

        self::assertSame(['a/b' => 'new'], $result);
    }

    public function testEscapeSequenceCombined(): void
    {
        // Key containing both ~ and /
        $doc = ['a~b/c' => 'value'];
        $patch = [
            ['op' => 'replace', 'path' => '/a~0b~1c', 'value' => 'new'],
        ];

        $result = JsonPatch::apply($doc, $patch);

        self::assertSame(['a~b/c' => 'new'], $result);
    }

    public function testDiffPreservesEscapeSequences(): void
    {
        $from = ['a~b/c' => 'old'];
        $to = ['a~b/c' => 'new'];

        $patch = JsonPatch::diff($from, $to);

        self::assertCount(1, $patch);
        self::assertSame('replace', $patch[0]['op']);
        self::assertSame('/a~0b~1c', $patch[0]['path']);

        // Verify round-trip
        $result = JsonPatch::apply($from, $patch);
        self::assertSame($to, $result);
    }

    public function testMoveFromPrefixOfPathFails(): void
    {
        $doc = ['a' => ['b' => ['c' => 1]]];
        $patch = [
            ['op' => 'move', 'from' => '/a', 'path' => '/a/b/c'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("'from' cannot be a proper prefix of 'path'");
        JsonPatch::apply($doc, $patch);
    }

    public function testMoveSamePathAllowed(): void
    {
        // Moving to same location is a no-op, should be allowed
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'move', 'from' => '/a', 'path' => '/a'],
        ];

        $result = JsonPatch::apply($doc, $patch);

        self::assertSame(['a' => 1], $result);
    }

    public function testDiffExceedsMaxDepthFails(): void
    {
        // Create structures deeper than MAX_DEPTH (512)
        $depth = 600;
        $from = 'start';
        $to = 'end';
        for ($i = 0; $i < $depth; ++$i) {
            $from = ['nested' => $from];
            $to = ['nested' => $to];
        }

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Maximum nesting depth exceeded');
        JsonPatch::diff($from, $to);
    }

    public function testTestMissingValueFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'test', 'path' => '/a'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage("Missing 'value'");
        JsonPatch::apply($doc, $patch);
    }

    public function testReplaceNonexistentPathFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'replace', 'path' => '/nonexistent', 'value' => 2],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Path does not exist');
        JsonPatch::apply($doc, $patch);
    }

    public function testRemoveNonexistentPathFails(): void
    {
        $doc = ['a' => 1];
        $patch = [
            ['op' => 'remove', 'path' => '/nonexistent'],
        ];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Path does not exist');
        JsonPatch::apply($doc, $patch);
    }

    public function testGetRoot(): void
    {
        $doc = ['a' => 1, 'b' => 2];

        $result = JsonPatch::get($doc, '');

        self::assertSame($doc, $result);
    }

    public function testGetNestedValue(): void
    {
        $doc = ['user' => ['profile' => ['name' => 'Alice', 'age' => 30]]];

        self::assertSame('Alice', JsonPatch::get($doc, '/user/profile/name'));
        self::assertSame(30, JsonPatch::get($doc, '/user/profile/age'));
        self::assertSame(['name' => 'Alice', 'age' => 30], JsonPatch::get($doc, '/user/profile'));
    }

    public function testGetArrayIndex(): void
    {
        $doc = ['items' => ['a', 'b', 'c']];

        self::assertSame('b', JsonPatch::get($doc, '/items/1'));
    }

    public function testGetNonexistentPathFails(): void
    {
        $doc = ['a' => 1];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Path does not exist');
        JsonPatch::get($doc, '/nonexistent');
    }

    public function testTestReturnsTrueOnMatch(): void
    {
        $doc = ['a' => 1, 'b' => ['c' => 'hello']];

        self::assertTrue(JsonPatch::test($doc, '/a', 1));
        self::assertTrue(JsonPatch::test($doc, '/b/c', 'hello'));
        self::assertTrue(JsonPatch::test($doc, '/b', ['c' => 'hello']));
    }

    public function testTestReturnsFalseOnMismatch(): void
    {
        $doc = ['a' => 1];

        self::assertFalse(JsonPatch::test($doc, '/a', 2));
        self::assertFalse(JsonPatch::test($doc, '/a', '1'));
        self::assertFalse(JsonPatch::test($doc, '/a', true));
    }

    public function testTestNonexistentPathFails(): void
    {
        $doc = ['a' => 1];

        $this->expectException(JsonPatchException::class);
        JsonPatch::test($doc, '/nonexistent', 'any');
    }

    public function testValidateReturnsEmptyForValidPatch(): void
    {
        $patch = [
            ['op' => 'add', 'path' => '/a', 'value' => 1],
            ['op' => 'remove', 'path' => '/b'],
            ['op' => 'replace', 'path' => '/c', 'value' => 2],
            ['op' => 'move', 'from' => '/d', 'path' => '/e'],
            ['op' => 'copy', 'from' => '/f', 'path' => '/g'],
            ['op' => 'test', 'path' => '/h', 'value' => 3],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertSame([], $errors);
    }

    public function testValidateDetectsMissingOp(): void
    {
        $patch = [
            ['path' => '/a', 'value' => 1],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertCount(1, $errors);
        self::assertStringContainsString("missing valid 'op'", $errors[0]);
    }

    public function testValidateDetectsMissingPath(): void
    {
        $patch = [
            ['op' => 'add', 'value' => 1],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertCount(1, $errors);
        self::assertStringContainsString("missing valid 'path'", $errors[0]);
    }

    public function testValidateDetectsMissingValue(): void
    {
        $patch = [
            ['op' => 'add', 'path' => '/a'],
            ['op' => 'replace', 'path' => '/b'],
            ['op' => 'test', 'path' => '/c'],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertCount(3, $errors);
        foreach ($errors as $error) {
            self::assertStringContainsString("missing 'value'", $error);
        }
    }

    public function testValidateDetectsMissingFrom(): void
    {
        $patch = [
            ['op' => 'move', 'path' => '/a'],
            ['op' => 'copy', 'path' => '/b'],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertCount(2, $errors);
        foreach ($errors as $error) {
            self::assertStringContainsString("missing 'from'", $error);
        }
    }

    public function testValidateDetectsUnsupportedOp(): void
    {
        $patch = [
            ['op' => 'unknown', 'path' => '/a'],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertCount(1, $errors);
        self::assertStringContainsString("unsupported operation 'unknown'", $errors[0]);
    }

    public function testValidateDetectsInvalidPathFormat(): void
    {
        $patch = [
            ['op' => 'add', 'path' => 'no-leading-slash', 'value' => 1],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertCount(1, $errors);
        self::assertStringContainsString("path must be empty or start with '/'", $errors[0]);
    }

    public function testValidateDetectsNonObjectOperation(): void
    {
        $patch = [
            'not an object',
        ];

        $errors = JsonPatch::validate($patch);

        self::assertCount(1, $errors);
        self::assertStringContainsString('must be an object', $errors[0]);
    }

    public function testLcsDiffSimpleList(): void
    {
        $from = ['a', 'b', 'c'];
        $to = ['a', 'c'];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
        // Should generate a single remove operation, not a full replace
        self::assertCount(1, $patch);
        self::assertSame('remove', $patch[0]['op']);
    }

    public function testLcsDiffAddElement(): void
    {
        $from = ['a', 'c'];
        $to = ['a', 'b', 'c'];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
        // Should generate a single add operation
        self::assertCount(1, $patch);
        self::assertSame('add', $patch[0]['op']);
    }

    public function testLcsDiffMultipleChanges(): void
    {
        $from = [1, 2, 3, 4, 5];
        $to = [1, 3, 5, 6];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testLcsDiffCompleteReplacement(): void
    {
        $from = ['a', 'b', 'c'];
        $to = ['x', 'y', 'z'];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testLcsDiffEmptyToFilled(): void
    {
        $from = [];
        $to = ['a', 'b', 'c'];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
        self::assertCount(3, $patch);
    }

    public function testLcsDiffFilledToEmpty(): void
    {
        $from = ['a', 'b', 'c'];
        $to = [];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
        self::assertCount(3, $patch);
    }

    public function testLcsDiffNestedInObject(): void
    {
        $from = ['items' => [1, 2, 3, 4]];
        $to = ['items' => [1, 3, 4, 5]];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testLcsDiffWithObjectsInList(): void
    {
        $from = [
            ['name' => 'Alice'],
            ['name' => 'Bob'],
            ['name' => 'Charlie'],
        ];
        $to = [
            ['name' => 'Alice'],
            ['name' => 'Charlie'],
            ['name' => 'David'],
        ];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testLcsDiffDisabled(): void
    {
        $from = ['a', 'b', 'c'];
        $to = ['a', 'c'];

        // Disable LCS - should fall back to full replace
        $options = new DiffOptions(useLcs: false);
        $patch = JsonPatch::diff($from, $to, $options);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
        // Should be a single replace operation
        self::assertCount(1, $patch);
        self::assertSame('replace', $patch[0]['op']);
        self::assertSame('', $patch[0]['path']);
    }

    public function testLcsDiffPreservesOrder(): void
    {
        $from = ['d', 'a', 'b', 'c'];
        $to = ['a', 'b', 'c', 'e'];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testLcsDiffIdenticalLists(): void
    {
        $from = ['a', 'b', 'c'];
        $to = ['a', 'b', 'c'];

        $patch = JsonPatch::diff($from, $to);

        self::assertSame([], $patch);
    }

    public function testLcsDiffSingleElement(): void
    {
        $from = ['a'];
        $to = ['b'];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testValidateInvalidFromPath(): void
    {
        $patch = [
            ['op' => 'move', 'path' => '/a', 'from' => 'invalid'],
        ];

        $errors = JsonPatch::validate($patch);

        self::assertNotEmpty($errors);
        self::assertStringContainsString("from must be empty or start with '/'", $errors[0]);
    }

    public function testAddToScalarParentThrows(): void
    {
        $doc = ['foo' => 'bar'];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('not a container');

        JsonPatch::apply($doc, [['op' => 'add', 'path' => '/foo/child', 'value' => 1]]);
    }

    public function testRemoveFromScalarParentThrows(): void
    {
        $doc = ['foo' => 'bar'];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('not a container');

        JsonPatch::apply($doc, [['op' => 'remove', 'path' => '/foo/child']]);
    }

    public function testReplaceInScalarParentThrows(): void
    {
        $doc = ['foo' => 'bar'];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('not a container');

        JsonPatch::apply($doc, [['op' => 'replace', 'path' => '/foo/child', 'value' => 1]]);
    }

    public function testGetFromScalarThrows(): void
    {
        $doc = ['foo' => 'bar'];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Non-container');

        JsonPatch::get($doc, '/foo/child');
    }

    public function testArrayIndexDashForExistingThrows(): void
    {
        $doc = ['a', 'b', 'c'];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Invalid array index');

        JsonPatch::apply($doc, [['op' => 'remove', 'path' => '/-']]);
    }

    public function testArrayIndexEmptyForAddThrows(): void
    {
        // This tests parseArrayIndexForAdd with empty segment
        // Need to craft a scenario where add is called on array with empty key
        $doc = ['items' => ['a', 'b']];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Invalid array index');

        // Path /items/ has trailing slash = empty segment
        JsonPatch::apply($doc, [['op' => 'add', 'path' => '/items/', 'value' => 'c']]);
    }

    public function testArrayIndexLeadingZerosForAddThrows(): void
    {
        $doc = ['a', 'b'];

        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('leading zeros');

        JsonPatch::apply($doc, [['op' => 'add', 'path' => '/01', 'value' => 'c']]);
    }

    public function testDeepEqualsKeysMismatch(): void
    {
        // Test that diff detects key mismatch (line 509)
        $from = ['a' => 1, 'b' => 2];
        $to = ['a' => 1, 'c' => 3];

        $patch = JsonPatch::diff($from, $to);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testDiffListToObjectReplaces(): void
    {
        // Test diffAt when list becomes object (line 542)
        $from = ['items' => [1, 2, 3]];
        $to = ['items' => ['a' => 1, 'b' => 2]];

        $patch = JsonPatch::diff($from, $to);

        self::assertCount(1, $patch);
        self::assertSame('replace', $patch[0]['op']);
        self::assertSame('/items', $patch[0]['path']);
    }

    public function testDiffObjectRemoveKey(): void
    {
        // Test diffObject remove operation (line 584)
        $from = ['a' => 1, 'b' => 2, 'c' => 3];
        $to = ['a' => 1, 'c' => 3];

        $patch = JsonPatch::diff($from, $to);

        self::assertCount(1, $patch);
        self::assertSame('remove', $patch[0]['op']);
        self::assertSame('/b', $patch[0]['path']);
    }

    public function testDiffListByIdMissingIdInSource(): void
    {
        // Test diffListById when source item lacks ID (line 613)
        $from = [
            ['id' => '1', 'name' => 'Alice'],
            ['name' => 'Bob'], // Missing id
        ];
        $to = [
            ['id' => '1', 'name' => 'Alice Updated'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);

        // Should fall back to LCS since id-based diff fails
        $result = JsonPatch::apply($from, $patch);
        self::assertSame($to, $result);
    }

    public function testDiffListByIdWithItemRemovalAndIndexShift(): void
    {
        // Test diffListById index shift after removal (line 633)
        $from = [
            ['id' => '1', 'v' => 'a'],
            ['id' => '2', 'v' => 'b'],
            ['id' => '3', 'v' => 'c'],
        ];
        $to = [
            ['id' => '1', 'v' => 'a'],
            ['id' => '3', 'v' => 'c'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testDiffListByIdInsertNewItem(): void
    {
        // Test diffListById insert at position (lines 673-677)
        $from = [
            ['id' => '1', 'v' => 'a'],
            ['id' => '3', 'v' => 'c'],
        ];
        $to = [
            ['id' => '1', 'v' => 'a'],
            ['id' => '2', 'v' => 'b'],
            ['id' => '3', 'v' => 'c'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testDiffListByIdRemoveTrailingItems(): void
    {
        // Test diffListById remove trailing items (lines 686-688)
        $from = [
            ['id' => '1', 'v' => 'a'],
            ['id' => '2', 'v' => 'b'],
            ['id' => '3', 'v' => 'c'],
            ['id' => '4', 'v' => 'd'],
        ];
        $to = [
            ['id' => '1', 'v' => 'a'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);
        $result = JsonPatch::apply($from, $patch);

        self::assertSame($to, $result);
    }

    public function testDiffListByIdDuplicateIds(): void
    {
        // Test indexListById with duplicate IDs (line 729)
        $from = [
            ['id' => '1', 'v' => 'a'],
            ['id' => '1', 'v' => 'b'], // Duplicate ID
        ];
        $to = [
            ['id' => '1', 'v' => 'c'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);

        // Falls back to LCS since id-based fails
        $result = JsonPatch::apply($from, $patch);
        self::assertSame($to, $result);
    }

    public function testDiffListByIdItemIsNotArray(): void
    {
        // Test readId when item is not an array (line 740)
        $from = [
            ['id' => '1'],
            'not-an-array',
        ];
        $to = [
            ['id' => '1'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);

        // Falls back to LCS
        $result = JsonPatch::apply($from, $patch);
        self::assertSame($to, $result);
    }

    public function testDiffListByIdItemIsList(): void
    {
        // Test readId when item is a list (line 740)
        $from = [
            ['id' => '1'],
            [1, 2, 3], // This is a list, not an object
        ];
        $to = [
            ['id' => '1'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);

        $result = JsonPatch::apply($from, $patch);
        self::assertSame($to, $result);
    }

    public function testDiffListByIdMissingIdKey(): void
    {
        // Test readId when item lacks ID key (line 744)
        $from = [
            ['id' => '1', 'v' => 'a'],
            ['v' => 'b'], // No 'id' key
        ];
        $to = [
            ['id' => '2', 'v' => 'c'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);

        $result = JsonPatch::apply($from, $patch);
        self::assertSame($to, $result);
    }

    public function testDiffListByIdNonStringIntId(): void
    {
        // Test readId when ID is not int/string (line 753)
        $from = [
            ['id' => ['nested' => 'value']], // ID is array
        ];
        $to = [
            ['id' => '1'],
        ];

        $options = new DiffOptions(['' => 'id']);
        $patch = JsonPatch::diff($from, $to, $options);

        $result = JsonPatch::apply($from, $patch);
        self::assertSame($to, $result);
    }

    public function testAddWithDashAppendsToArray(): void
    {
        // Test parseArrayIndexForAdd with '-' (line 458)
        $doc = ['a', 'b'];

        $result = JsonPatch::apply($doc, [['op' => 'add', 'path' => '/-', 'value' => 'c']]);

        self::assertSame(['a', 'b', 'c'], $result);
    }

    public function testReplaceAtNonContainerThrows(): void
    {
        // This is tricky - we need to trigger replaceAt on a non-container
        // This happens when traversing back up and hitting a scalar
        $doc = 'scalar';

        $this->expectException(JsonPatchException::class);

        JsonPatch::apply($doc, [['op' => 'replace', 'path' => '/a/b', 'value' => 1]]);
    }
}
