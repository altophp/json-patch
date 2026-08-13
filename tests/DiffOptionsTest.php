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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DiffOptions::class)]
final class DiffOptionsTest extends TestCase
{
    public function testConstructDefaults(): void
    {
        $options = new DiffOptions();
        self::assertNull($options->identityKeyFor('/any/path'));
    }

    public function testIdentityKeyLookup(): void
    {
        $options = new DiffOptions([
            '/users' => 'id',
            '/items' => 'uuid',
        ]);

        self::assertSame('id', $options->identityKeyFor('/users'));
        self::assertSame('uuid', $options->identityKeyFor('/items'));
        self::assertNull($options->identityKeyFor('/other'));
    }
}
