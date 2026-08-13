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

namespace Alto\JsonPatch;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final readonly class DiffOptions
{
    /**
     * @param array<string, string> $listIdentityByPointer Path pointer => identity key (example: '/items' => 'id')
     * @param bool                  $useLcs                Use LCS algorithm for list diffing when no identity key is set (default: true)
     */
    public function __construct(
        public array $listIdentityByPointer = [],
        public bool $useLcs = true,
    ) {}

    public function identityKeyFor(string $pointer): ?string
    {
        return $this->listIdentityByPointer[$pointer] ?? null;
    }
}
