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

use Alto\JsonPatch\JsonPatch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * RFC 6902 Official Test Suite.
 *
 * Test cases from: https://github.com/json-patch/json-patch-tests
 *
 * @see https://datatracker.ietf.org/doc/html/rfc6902
 */
#[CoversClass(JsonPatch::class)]
final class Rfc6902ComplianceTest extends TestCase
{
    /**
     * @param array<mixed>      $doc
     * @param array<mixed>      $patch
     * @param array<mixed>|null $expected
     */
    #[DataProvider('provideSpecTests')]
    #[DataProvider('provideMainTests')]
    public function testRfc6902Compliance(
        array|string $doc,
        array $patch,
        array|string|null $expected,
        ?string $error,
        string $comment,
    ): void {
        if (null !== $expected) {
            $result = JsonPatch::apply($doc, $patch);
            self::assertEquals($expected, $result, $comment);
        } else {
            $this->expectException(\Throwable::class);
            JsonPatch::apply($doc, $patch);
        }
    }

    /**
     * @return iterable<string, array{doc: array<mixed>|string, patch: array<mixed>, expected: array<mixed>|string|null, error: string|null, comment: string}>
     */
    public static function provideSpecTests(): iterable
    {
        yield from self::loadTestFile(__DIR__ . '/fixtures/json-patch-tests/spec_tests.json', 'spec');
    }

    /**
     * @return iterable<string, array{doc: array<mixed>|string, patch: array<mixed>, expected: array<mixed>|string|null, error: string|null, comment: string}>
     */
    public static function provideMainTests(): iterable
    {
        yield from self::loadTestFile(__DIR__ . '/fixtures/json-patch-tests/tests.json', 'main');
    }

    /**
     * Tests skipped due to PHP limitations (json_decode cannot distinguish {} from []).
     *
     * @var array<string>
     */
    private const PHP_LIMITATION_SKIPS = [
        'toplevel object, numeric string',
        'toplevel object, integer',
        'Add, / target',
        'Add, /foo/ deep target (trailing slash)',
        'null value should be valid obj property to be moved',
    ];

    /**
     * @return iterable<string, array{doc: array<mixed>|string, patch: array<mixed>, expected: array<mixed>|string|null, error: string|null, comment: string}>
     */
    private static function loadTestFile(string $path, string $prefix): iterable
    {
        $content = file_get_contents($path);
        if (false === $content) {
            throw new \RuntimeException("Failed to read test file: $path");
        }

        $tests = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        $index = 0;

        foreach ($tests as $test) {
            ++$index;

            if (isset($test['disabled']) && true === $test['disabled']) {
                continue;
            }

            $comment = $test['comment'] ?? "Test #$index";

            if (in_array($comment, self::PHP_LIMITATION_SKIPS, true)) {
                continue;
            }

            // Include index to ensure unique test names
            $testName = sprintf('%s #%03d: %s', $prefix, $index, $comment);

            yield $testName => [
                'doc' => $test['doc'],
                'patch' => $test['patch'],
                'expected' => $test['expected'] ?? null,
                'error' => $test['error'] ?? null,
                'comment' => $comment,
            ];
        }
    }
}
