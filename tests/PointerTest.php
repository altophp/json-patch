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

use Alto\JsonPatch\Exception\JsonPatchException;
use Alto\JsonPatch\Pointer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Pointer::class)]
final class PointerTest extends TestCase
{
    public function testParseEmpty(): void
    {
        $p = Pointer::parse('');
        self::assertTrue($p->isRoot());
        self::assertSame([], $p->segments());
        self::assertSame('', $p->toString());
    }

    public function testParseSimple(): void
    {
        $p = Pointer::parse('/foo');
        self::assertFalse($p->isRoot());
        self::assertSame(['foo'], $p->segments());
        self::assertSame('/foo', $p->toString());
    }

    public function testParseEscaped(): void
    {
        // "~1" -> "/", "~0" -> "~"
        // RFC 6901 examples
        $p = Pointer::parse('/a~1b/m~0n');
        self::assertSame(['a/b', 'm~n'], $p->segments());
        self::assertSame('/a~1b/m~0n', $p->toString());
    }

    public function testInvalidStart(): void
    {
        $this->expectException(JsonPatchException::class);
        Pointer::parse('foo');
    }

    public function testParent(): void
    {
        $p = Pointer::parse('/a/b/c');
        $parent = $p->parent();
        self::assertSame('/a/b', $parent->toString());
        self::assertSame('/a', $parent->parent()->toString());
        self::assertTrue($parent->parent()->parent()->isRoot());
    }

    public function testParentOfRootReturnsSelf(): void
    {
        $p = Pointer::parse('');
        self::assertTrue($p->isRoot());
        self::assertSame($p, $p->parent());
    }

    public function testParentOfSingleSegmentPointer(): void
    {
        $p = Pointer::parse('/a');
        $parent = $p->parent();
        self::assertTrue($parent->isRoot());
        self::assertSame('', $parent->toString());
    }

    public function testParentOfMultiSegmentPointer(): void
    {
        $p = Pointer::parse('/a/b/c');
        $parent = $p->parent();
        self::assertSame('/a/b', $parent->toString());
        self::assertSame('/a', $parent->parent()->toString());
        self::assertTrue($parent->parent()->parent()->isRoot());
    }

    public function testParseWithTrailingEmptySegmentIsHandledCorrectly(): void
    {
        $p = Pointer::parse('/a/');
        self::assertFalse($p->isRoot());
        self::assertSame(['a', ''], $p->segments());
        self::assertSame('/a/', $p->toString());
        self::assertSame('', $p->last());
        self::assertSame('/a', $p->parent()->toString());
    }

    public function testLast(): void
    {
        $p = Pointer::parse('/a/b');
        self::assertSame('b', $p->last());
    }

    public function testLastOnRootThrows(): void
    {
        $p = Pointer::parse('');
        $this->expectException(JsonPatchException::class);
        $p->last();
    }

    public function testInvalidEscapeSequenceThrows(): void
    {
        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Invalid pointer escape sequence');
        Pointer::parse('/a~2b'); // ~2 is invalid, only ~0 and ~1 are valid
    }

    public function testTrailingTildeThrows(): void
    {
        $this->expectException(JsonPatchException::class);
        $this->expectExceptionMessage('Invalid pointer escape sequence');
        Pointer::parse('/a~'); // trailing ~ without 0 or 1
    }

    public function testClearCacheAndParseEmpty(): void
    {
        Pointer::clearCache();

        $p = Pointer::parse('');
        self::assertTrue($p->isRoot());
        self::assertSame([], $p->segments());
    }

    public function testClearsOldestCacheEntryWhenMaxSizeExceeded(): void
    {
        Pointer::clearCache();

        $maxCacheSize = (new \ReflectionClassConstant(Pointer::class, 'MAX_CACHE_SIZE'))->getValue();

        for ($i = 0; $i < $maxCacheSize; ++$i) {
            Pointer::parse('/entry' . $i);
        }

        $this->assertCount($maxCacheSize, (new \ReflectionClass(Pointer::class))->getStaticPropertyValue('cache'));

        Pointer::parse('/new-entry');

        $this->assertCount($maxCacheSize, (new \ReflectionClass(Pointer::class))->getStaticPropertyValue('cache'));
        $this->assertArrayNotHasKey('/entry0', (new \ReflectionClass(Pointer::class))->getStaticPropertyValue('cache'));
    }
}
