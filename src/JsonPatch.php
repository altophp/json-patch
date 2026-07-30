<?php

declare(strict_types=1);

/*
 * This file is part of the ALTO library.
 *
 * © 2026–present Simon André
 *
 * For full copyright and license information, please see
 * the LICENSE file distributed with this source code.
 */

namespace Alto\JsonPatch;

use Alto\JsonPatch\Exception\InvalidOperationException;
use Alto\JsonPatch\Exception\JsonPatchException;
use Alto\JsonPatch\Exception\PathNotFoundException;
use Alto\JsonPatch\Exception\TestFailedException;
use Alto\JsonPatch\Exception\TypeMismatchException;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class JsonPatch
{
    public const string VERSION = '1.0.0';

    private const int MAX_DEPTH = 512;

    /**
     * Apply a JSON Patch to a document.
     *
     * @param list<mixed> $patch
     *
     * @throws JsonPatchException If the patch is invalid or the operation fails
     */
    public static function apply(mixed $document, array $patch): mixed
    {
        foreach ($patch as $i => $op) {
            if (!is_array($op)) {
                throw new JsonPatchException('A patch must be a list of objects.');
            }

            /** @var array<string, mixed> $op */
            $document = self::applyOperation($document, $op, $i);
        }

        return $document;
    }

    /**
     * Apply a JSON Patch to a JSON string.
     *
     * @throws JsonPatchException If the JSON is invalid or the patch fails
     */
    public static function applyJson(string $documentJson, string $patchJson, int $jsonDecodeFlags = 0): string
    {
        $doc = json_decode($documentJson, true, flags: $jsonDecodeFlags);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonPatchException('Invalid document JSON: '.json_last_error_msg());
        }

        $patch = json_decode($patchJson, true, flags: $jsonDecodeFlags);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new JsonPatchException('Invalid patch JSON: '.json_last_error_msg());
        }

        if (!is_array($patch) || !array_is_list($patch)) {
            throw new JsonPatchException('Patch JSON must be a list.');
        }

        $result = self::apply($doc, $patch);

        return json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Generate a JSON Patch that transforms the $from document into $to.
     *
     * @return list<array<string, mixed>>
     *
     * @throws JsonPatchException If the recursion depth limit is exceeded
     */
    public static function diff(mixed $from, mixed $to, ?DiffOptions $options = null): array
    {
        $options ??= new DiffOptions();
        $ops = [];
        self::diffAt('', $from, $to, $ops, $options);

        return $ops;
    }

    /**
     * Retrieve a value at the given JSON Pointer path.
     *
     * @throws JsonPatchException if the path does not exist or is invalid
     */
    public static function get(mixed $document, string $path): mixed
    {
        $pointer = Pointer::parse($path);

        if ($pointer->isRoot()) {
            return $document;
        }

        return self::getAt($document, $pointer);
    }

    /**
     * Test if a value at the given path equals the expected value.
     *
     * Unlike the 'test' operation in apply(), this method returns a boolean
     * instead of throwing an exception on mismatch.
     *
     * @throws JsonPatchException if the path does not exist or is invalid
     */
    public static function test(mixed $document, string $path, mixed $expected): bool
    {
        $actual = self::get($document, $path);

        return self::deepEquals($actual, $expected);
    }

    /**
     * Validate a patch without applying it.
     *
     * Returns an array of error messages. Empty array means the patch is valid.
     * This checks structural validity only, not whether paths exist in a document.
     *
     * @param list<mixed> $patch
     *
     * @return list<string>
     */
    public static function validate(array $patch): array
    {
        $errors = [];

        foreach ($patch as $i => $op) {
            if (!is_array($op)) {
                $errors[] = sprintf('Operation %d: must be an object.', $i);
                continue;
            }

            $name = $op['op'] ?? null;
            if (!is_string($name) || '' === $name) {
                $errors[] = sprintf('Operation %d: missing valid \'op\'.', $i);
                continue;
            }

            if (!in_array($name, ['add', 'remove', 'replace', 'move', 'copy', 'test'], true)) {
                $errors[] = sprintf('Operation %d: unsupported operation \'%s\'.', $i, $name);
            }

            $path = $op['path'] ?? null;
            if (!is_string($path)) {
                $errors[] = sprintf('Operation %d (%s): missing valid \'path\'.', $i, $name);
            } elseif ('' !== $path && !str_starts_with($path, '/')) {
                $errors[] = sprintf('Operation %d (%s): path must be empty or start with \'/\'.', $i, $name);
            }

            if (in_array($name, ['add', 'replace', 'test'], true) && !array_key_exists('value', $op)) {
                $errors[] = sprintf('Operation %d (%s): missing \'value\'.', $i, $name);
            }

            if (in_array($name, ['move', 'copy'], true)) {
                $from = $op['from'] ?? null;
                if (!is_string($from)) {
                    $errors[] = sprintf('Operation %d (%s): missing \'from\'.', $i, $name);
                } elseif ('' !== $from && !str_starts_with($from, '/')) {
                    $errors[] = sprintf('Operation %d (%s): from must be empty or start with \'/\'.', $i, $name);
                }
            }
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $op
     */
    private static function applyOperation(mixed $document, array $op, int $index): mixed
    {
        $name = $op['op'] ?? null;
        if (!is_string($name) || '' === $name) {
            throw new InvalidOperationException(sprintf('Operation %d: missing valid \'op\'.', $index));
        }

        $path = $op['path'] ?? null;
        if (!is_string($path)) {
            throw new InvalidOperationException(sprintf('Operation %d (%s): missing valid \'path\'.', $index, $name));
        }

        $pointer = Pointer::parse($path);

        return match ($name) {
            'add' => self::opAdd($document, $pointer, $op, $index),
            'remove' => self::opRemove($document, $pointer, $index),
            'replace' => self::opReplace($document, $pointer, $op, $index),
            'move' => self::opMove($document, $pointer, $op, $index),
            'copy' => self::opCopy($document, $pointer, $op, $index),
            'test' => self::opTest($document, $pointer, $op, $index),
            default => throw new InvalidOperationException(sprintf('Operation %d (%s): Unsupported operation.', $index, $name)),
        };
    }

    /**
     * @param array<string, mixed> $op
     */
    private static function opAdd(mixed $document, Pointer $path, array $op, int $index): mixed
    {
        if (!array_key_exists('value', $op)) {
            throw new InvalidOperationException(sprintf('Operation %d (add): Missing \'value\'.', $index));
        }

        $value = $op['value'];

        if ($path->isRoot()) {
            return $value;
        }

        [$parent, $key] = self::resolveParent($document, $path, $index, 'add');

        if (is_array($parent) && array_is_list($parent)) {
            if ('-' === $key) {
                $parent[] = $value;
            } else {
                $pos = self::parseArrayIndexForAdd($key, $parent, $index);
                array_splice($parent, $pos, 0, [$value]);
            }
        } elseif (is_array($parent)) {
            $parent[$key] = $value;
        } else {
            throw new TypeMismatchException(sprintf('Operation %d (add): Parent at %s is not a container.', $index, $path->parent()->toString() ?: '/'));
        }

        return self::replaceAt($document, $path->parent(), $parent);
    }

    private static function opRemove(mixed $document, Pointer $path, int $index): mixed
    {
        if ($path->isRoot()) {
            throw new InvalidOperationException(sprintf('Operation %d (remove): Cannot remove the document root.', $index));
        }

        [$parent, $key] = self::resolveParent($document, $path, $index, 'remove');

        if (is_array($parent) && array_is_list($parent)) {
            $pos = self::parseArrayIndexForExisting($key, $parent, $index, 'remove');
            array_splice($parent, $pos, 1);
        } elseif (is_array($parent)) {
            if (!array_key_exists($key, $parent)) {
                throw new PathNotFoundException(sprintf('Operation %d (remove): Path does not exist: %s', $index, $path->toString()));
            }
            unset($parent[$key]);
        } else {
            throw new TypeMismatchException(sprintf('Operation %d (remove): Parent at %s is not a container.', $index, $path->parent()->toString() ?: '/'));
        }

        return self::replaceAt($document, $path->parent(), $parent);
    }

    /**
     * @param array<string, mixed> $op
     */
    private static function opReplace(mixed $document, Pointer $path, array $op, int $index): mixed
    {
        if (!array_key_exists('value', $op)) {
            throw new InvalidOperationException(sprintf('Operation %d (replace): Missing \'value\'.', $index));
        }

        $value = $op['value'];

        if ($path->isRoot()) {
            return $value;
        }

        [$parent, $key] = self::resolveParent($document, $path, $index, 'replace');

        if (is_array($parent) && array_is_list($parent)) {
            $pos = self::parseArrayIndexForExisting($key, $parent, $index, 'replace');
            $parent[$pos] = $value;
        } elseif (is_array($parent)) {
            if (!array_key_exists($key, $parent)) {
                throw new PathNotFoundException(sprintf('Operation %d (replace): Path does not exist: %s', $index, $path->toString()));
            }
            $parent[$key] = $value;
        } else {
            throw new TypeMismatchException(sprintf('Operation %d (replace): Parent at %s is not a container.', $index, $path->parent()->toString() ?: '/'));
        }

        return self::replaceAt($document, $path->parent(), $parent);
    }

    /**
     * @param array<string, mixed> $op
     */
    private static function opMove(mixed $document, Pointer $path, array $op, int $index): mixed
    {
        $from = $op['from'] ?? null;
        if (!is_string($from)) {
            throw new InvalidOperationException(sprintf('Operation %d (move): Missing \'from\'.', $index));
        }

        // RFC 6902 Section 4.4: "from" must not be a proper prefix of "path"
        $pathStr = $path->toString();
        if ($pathStr !== $from && str_starts_with($pathStr, $from.'/')) {
            throw new InvalidOperationException(sprintf('Operation %d (move): \'from\' cannot be a proper prefix of \'path\'.', $index));
        }

        // Short-circuit: moving to same location is a no-op
        if ($pathStr === $from) {
            return $document;
        }

        $fromPtr = Pointer::parse($from);
        $value = self::getAt($document, $fromPtr);

        $document = self::opRemove($document, $fromPtr, $index);

        return self::opAdd($document, $path, ['op' => 'add', 'path' => $path->toString(), 'value' => $value], $index);
    }

    /**
     * @param array<string, mixed> $op
     */
    private static function opCopy(mixed $document, Pointer $path, array $op, int $index): mixed
    {
        $from = $op['from'] ?? null;
        if (!is_string($from)) {
            throw new InvalidOperationException(sprintf('Operation %d (copy): Missing \'from\'.', $index));
        }

        $fromPtr = Pointer::parse($from);
        $value = self::getAt($document, $fromPtr);

        return self::opAdd($document, $path, ['op' => 'add', 'path' => $path->toString(), 'value' => $value], $index);
    }

    /**
     * @param array<string, mixed> $op
     */
    private static function opTest(mixed $document, Pointer $path, array $op, int $index): mixed
    {
        if (!array_key_exists('value', $op)) {
            throw new InvalidOperationException(sprintf('Operation %d (test): Missing \'value\'.', $index));
        }

        $expected = $op['value'];
        $actual = $path->isRoot() ? $document : self::getAt($document, $path);

        if (!self::deepEquals($actual, $expected)) {
            throw new TestFailedException('Test failed at path: '.$path->toString());
        }

        return $document;
    }

    /**
     * @return array{mixed, string}
     */
    private static function resolveParent(mixed $document, Pointer $path, int $index, string $op): array
    {
        $parentPtr = $path->parent();
        $parent = $parentPtr->isRoot() ? $document : self::getAt($document, $parentPtr);

        $key = $path->last();

        return [$parent, $key];
    }

    private static function replaceAt(mixed $document, Pointer $pointer, mixed $value): mixed
    {
        if ($pointer->isRoot()) {
            return $value;
        }

        $segments = $pointer->segments();
        $current = $document;

        $stack = [];
        foreach ($segments as $seg) {
            $stack[] = [$current, $seg];
            $current = self::readChild($current, $seg, $pointer->toString());
        }

        $new = $value;

        // Reconstruct document from leaf to root
        // Note: all containers in stack are guaranteed to be arrays (readChild validates this)
        for ($i = count($stack) - 1; $i >= 0; --$i) {
            [$container, $seg] = $stack[$i];
            /** @var array<mixed> $container */
            if (array_is_list($container)) {
                $pos = self::parseArrayIndexForExisting($seg, $container, 0, 'internal');
                $container[$pos] = $new;
            } else {
                $container[$seg] = $new;
            }
            $new = $container;
        }

        return $new;
    }

    private static function getAt(mixed $document, Pointer $pointer): mixed
    {
        $current = $document;

        foreach ($pointer->segments() as $seg) {
            $current = self::readChild($current, $seg, $pointer->toString());
        }

        return $current;
    }

    private static function readChild(mixed $current, string $segment, string $fullPath): mixed
    {
        if (!is_array($current)) {
            throw new TypeMismatchException('Non-container encountered at: '.$fullPath);
        }

        if (array_is_list($current)) {
            $pos = self::parseArrayIndexForExisting($segment, $current, 0, 'read');

            return $current[$pos];
        }

        if (!array_key_exists($segment, $current)) {
            throw new PathNotFoundException('Path does not exist: '.$fullPath);
        }

        return $current[$segment];
    }

    /**
     * @param array<mixed> $list
     */
    private static function parseArrayIndexForExisting(string $segment, array $list, int $index, string $op): int
    {
        if ('-' === $segment || '' === $segment) {
            throw new InvalidOperationException(sprintf('Operation %d (%s): Invalid array index segment.', $index, $op));
        }

        if (!ctype_digit($segment)) {
            throw new InvalidOperationException(sprintf('Operation %d (%s): Array index must be an integer.', $index, $op));
        }

        if (strlen($segment) > 1 && '0' === $segment[0]) {
            throw new InvalidOperationException(sprintf('Operation %d (%s): Array index must not have leading zeros.', $index, $op));
        }

        $pos = (int) $segment;

        if ($pos < 0 || $pos >= count($list)) {
            throw new InvalidOperationException(sprintf('Operation %d (%s): Array index out of range.', $index, $op));
        }

        return $pos;
    }

    /**
     * @param array<mixed> $list
     */
    private static function parseArrayIndexForAdd(string $segment, array $list, int $index): int
    {
        if ('' === $segment) {
            throw new InvalidOperationException(sprintf('Operation %d (add): Invalid array index segment.', $index));
        }

        // Note: '-' is handled inline in opAdd before calling this function

        if (!ctype_digit($segment)) {
            throw new InvalidOperationException(sprintf('Operation %d (add): Array index must be an integer.', $index));
        }

        if (strlen($segment) > 1 && '0' === $segment[0]) {
            throw new InvalidOperationException(sprintf('Operation %d (add): Array index must not have leading zeros.', $index));
        }

        $pos = (int) $segment;

        if ($pos < 0 || $pos > count($list)) {
            throw new InvalidOperationException(sprintf('Operation %d (add): Array index out of range for add.', $index));
        }

        return $pos;
    }

    private static function deepEquals(mixed $a, mixed $b): bool
    {
        if (gettype($a) !== gettype($b)) {
            return false;
        }

        if (is_array($a) && is_array($b)) {
            if (array_is_list($a) !== array_is_list($b)) {
                return false;
            }

            if (count($a) !== count($b)) {
                return false;
            }

            if (array_is_list($a)) {
                for ($i = 0; $i < count($a); ++$i) {
                    if (!self::deepEquals($a[$i], $b[$i])) {
                        return false;
                    }
                }

                return true;
            }

            $keysA = array_keys($a);
            $keysB = array_keys($b);
            sort($keysA);
            sort($keysB);

            if ($keysA !== $keysB) {
                return false;
            }

            foreach ($keysA as $k) {
                if (!self::deepEquals($a[$k], $b[$k])) {
                    return false;
                }
            }

            return true;
        }

        return $a === $b;
    }

    /**
     * @param list<array<string, mixed>> $ops
     */
    private static function diffAt(string $path, mixed $from, mixed $to, array &$ops, DiffOptions $options, int $depth = 0): void
    {
        if ($depth > self::MAX_DEPTH) {
            throw new JsonPatchException('Maximum nesting depth exceeded during diff.');
        }

        if (self::deepEquals($from, $to)) {
            return;
        }

        if (is_array($from) && is_array($to)) {
            $fromIsList = array_is_list($from);
            $toIsList = array_is_list($to);

            if ($fromIsList !== $toIsList) {
                $ops[] = ['op' => 'replace', 'path' => $path, 'value' => $to];

                return;
            }

            if ($fromIsList) {
                /** @var list<mixed> $from */
                /** @var list<mixed> $to */
                $identityKey = $options->identityKeyFor($path);
                if (null !== $identityKey) {
                    if (self::diffListById($path, $from, $to, $identityKey, $ops, $options, $depth)) {
                        return;
                    }
                }

                // Use LCS-based diffing for lists without identity keys
                if ($options->useLcs) {
                    self::diffListByLcs($path, $from, $to, $ops, $options, $depth);

                    return;
                }

                $ops[] = ['op' => 'replace', 'path' => $path, 'value' => $to];

                return;
            }

            /** @var array<string, mixed> $from */
            $from = $from;
            /** @var array<string, mixed> $to */
            $to = $to;

            self::diffObject($path, $from, $to, $ops, $options, $depth);

            return;
        }

        $ops[] = ['op' => 'replace', 'path' => $path, 'value' => $to];
    }

    /**
     * @param array<string, mixed>       $from
     * @param array<string, mixed>       $to
     * @param list<array<string, mixed>> $ops
     */
    private static function diffObject(string $path, array $from, array $to, array &$ops, DiffOptions $options, int $depth): void
    {
        foreach ($from as $k => $_) {
            if (!array_key_exists($k, $to)) {
                $ops[] = ['op' => 'remove', 'path' => self::join($path, $k)];
            }
        }

        foreach ($to as $k => $vTo) {
            $p = self::join($path, $k);

            if (!array_key_exists($k, $from)) {
                $ops[] = ['op' => 'add', 'path' => $p, 'value' => $vTo];
                continue;
            }

            self::diffAt($p, $from[$k], $vTo, $ops, $options, $depth + 1);
        }
    }

    /**
     * Returns true if it successfully produced a by-id diff, false if it had to fallback.
     *
     * @param list<mixed>                $from
     * @param list<mixed>                $to
     * @param list<array<string, mixed>> $ops
     */
    private static function diffListById(string $path, array $from, array $to, string $idKey, array &$ops, DiffOptions $options, int $depth): bool
    {
        $fromIndexById = self::indexListById($from, $idKey);
        $toIndexById = self::indexListById($to, $idKey);

        if (null === $fromIndexById || null === $toIndexById) {
            return false;
        }

        $current = $from;
        $currentIndex = $fromIndexById;

        // Remove items not in target (iterate backwards to preserve indices)
        // Note: indexListById already validated all items have valid IDs
        for ($i = count($current) - 1; $i >= 0; --$i) {
            /** @var string $id */
            $id = self::readId($current[$i], $idKey);

            if (!array_key_exists($id, $toIndexById)) {
                $ops[] = ['op' => 'remove', 'path' => self::join($path, (string) $i)];
                array_splice($current, $i, 1);
                unset($currentIndex[$id]);
                foreach ($currentIndex as $cid => $cidx) {
                    if ($cidx > $i) {
                        $currentIndex[$cid] = $cidx - 1;
                    }
                }
            }
        }

        for ($i = 0; $i < count($to); ++$i) {
            $toItem = $to[$i];
            /** @var string $toId */
            $toId = self::readId($toItem, $idKey);

            if ($i < count($current)) {
                /** @var string $currentId */
                $currentId = self::readId($current[$i], $idKey);

                if ($currentId === $toId) {
                    self::diffAt(self::join($path, (string) $i), $current[$i], $toItem, $ops, $options, $depth + 1);
                    continue;
                }

                // O(1) lookup instead of O(n) findIndexById
                $j = $currentIndex[$toId] ?? null;
                if (null !== $j) {
                    $ops[] = ['op' => 'move', 'from' => self::join($path, (string) $j), 'path' => self::join($path, (string) $i)];

                    $moved = $current[$j];
                    array_splice($current, $j, 1);
                    array_splice($current, $i, 0, [$moved]);

                    $currentIndex = self::rebuildIndexFromList($current, $idKey);

                    self::diffAt(self::join($path, (string) $i), $current[$i], $toItem, $ops, $options, $depth + 1);
                    continue;
                }

                $ops[] = ['op' => 'add', 'path' => self::join($path, (string) $i), 'value' => $toItem];
                array_splice($current, $i, 0, [$toItem]);
                $currentIndex = self::rebuildIndexFromList($current, $idKey);
                continue;
            }

            $ops[] = ['op' => 'add', 'path' => self::join($path, '-'), 'value' => $toItem];
            $current[] = $toItem;
            $currentIndex[$toId] = count($current) - 1;
        }

        return true;
    }

    /**
     * Rebuild id => position index from current list state.
     *
     * @param list<mixed> $list
     *
     * @return array<string, int>
     */
    private static function rebuildIndexFromList(array $list, string $idKey): array
    {
        $index = [];
        foreach ($list as $i => $item) {
            $id = self::readId($item, $idKey);
            if (null !== $id) {
                $index[$id] = (int) $i;
            }
        }

        return $index;
    }

    /**
     * @param list<mixed> $list
     *
     * @return array<string, int>|null
     */
    private static function indexListById(array $list, string $idKey): ?array
    {
        $index = [];

        foreach ($list as $i => $item) {
            $id = self::readId($item, $idKey);
            if (null === $id) {
                return null;
            }
            if (array_key_exists($id, $index)) {
                return null;
            }
            $index[$id] = (int) $i;
        }

        return $index;
    }

    private static function readId(mixed $item, string $idKey): ?string
    {
        if (!is_array($item) || array_is_list($item)) {
            return null;
        }

        if (!array_key_exists($idKey, $item)) {
            return null;
        }

        $v = $item[$idKey];

        if (is_int($v) || is_string($v)) {
            return (string) $v;
        }

        return null;
    }

    /**
     * Diff two lists using LCS (Longest Common Subsequence) algorithm.
     * Generates minimal add/remove operations.
     *
     * @param list<mixed>                $from
     * @param list<mixed>                $to
     * @param list<array<string, mixed>> $ops
     */
    private static function diffListByLcs(string $path, array $from, array $to, array &$ops, DiffOptions $options, int $depth): void
    {
        $m = count($from);
        $n = count($to);

        $lcs = self::buildLcsTable($from, $to);

        // Backtrack to find the diff operations
        $i = $m;
        $j = $n;

        // Collect operations in reverse order, then reverse at the end
        $removeOps = [];
        $addOps = [];

        while ($i > 0 || $j > 0) {
            if ($i > 0 && $j > 0 && self::deepEquals($from[$i - 1], $to[$j - 1])) {
                // Elements match - part of LCS, no operation needed
                --$i;
                --$j;
            } elseif ($j > 0 && (0 === $i || $lcs[$i][$j - 1] >= $lcs[$i - 1][$j])) {
                // Element in $to but not in LCS - need to add
                $addOps[] = ['index' => $j - 1, 'value' => $to[$j - 1]];
                --$j;
            } elseif (0 === $j || $lcs[$i][$j - 1] < $lcs[$i - 1][$j]) {
                // Element in $from but not in LCS - need to remove
                $removeOps[] = ['index' => $i - 1];
                --$i;
            }
        }

        foreach ($removeOps as $op) {
            $ops[] = ['op' => 'remove', 'path' => self::join($path, (string) $op['index'])];
        }

        $addOps = array_reverse($addOps);
        foreach ($addOps as $op) {
            $ops[] = ['op' => 'add', 'path' => self::join($path, (string) $op['index']), 'value' => $op['value']];
        }
    }

    /**
     * Build LCS (Longest Common Subsequence) length table using dynamic programming.
     *
     * @param list<mixed> $from
     * @param list<mixed> $to
     *
     * @return array<int, array<int, int>>
     */
    private static function buildLcsTable(array $from, array $to): array
    {
        $m = count($from);
        $n = count($to);

        $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; ++$i) {
            for ($j = 1; $j <= $n; ++$j) {
                if (self::deepEquals($from[$i - 1], $to[$j - 1])) {
                    $lcs[$i][$j] = $lcs[$i - 1][$j - 1] + 1;
                } else {
                    $lcs[$i][$j] = max($lcs[$i - 1][$j], $lcs[$i][$j - 1]);
                }
            }
        }

        return $lcs;
    }

    private static function join(string $base, string $segment): string
    {
        $seg = str_replace(['~', '/'], ['~0', '~1'], $segment);

        if ('' === $base) {
            return '/'.$seg;
        }

        return $base.'/'.$seg;
    }
}
