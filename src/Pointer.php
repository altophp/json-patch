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

use Alto\JsonPatch\Exception\JsonPatchException;

/**
 * @author Simon André <smn.andre@gmail.com>
 */
final class Pointer
{
    private const int MAX_CACHE_SIZE = 1000;

    /**
     * @var array<string, self>
     */
    private static array $cache = [];

    /**
     * @var list<string>
     */
    private array $segments;

    /**
     * @param list<string> $segments
     */
    private function __construct(array $segments)
    {
        $this->segments = $segments;
    }

    public static function parse(string $pointer): self
    {
        if (isset(self::$cache[$pointer])) {
            return self::$cache[$pointer];
        }

        $parsed = self::doParse($pointer);

        if (count(self::$cache) >= self::MAX_CACHE_SIZE) {
            unset(self::$cache[array_key_first(self::$cache)]);
        }

        return self::$cache[$pointer] = $parsed;
    }

    /**
     * Clear the internal pointer cache. Primarily for testing.
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }

    private static function doParse(string $pointer): self
    {
        if ('' === $pointer) {
            return new self([]);
        }

        if (!str_starts_with($pointer, '/')) {
            throw new JsonPatchException('A JSON Pointer must be empty or start with \'/\'.');
        }

        $raw = explode('/', substr($pointer, 1));
        $segments = [];

        foreach ($raw as $part) {
            $segments[] = self::decodeSegment($part);
        }

        return new self($segments);
    }

    /**
     * @return list<string>
     */
    public function segments(): array
    {
        return $this->segments;
    }

    public function isRoot(): bool
    {
        return [] === $this->segments;
    }

    public function parent(): self
    {
        if ($this->isRoot()) {
            return $this;
        }

        return new self(array_slice($this->segments, 0, -1));
    }

    public function last(): string
    {
        if ([] === $this->segments) {
            throw new JsonPatchException('Root pointer has no last segment.');
        }

        /* @var string */
        return $this->segments[array_key_last($this->segments)];
    }

    public function toString(): string
    {
        if ([] === $this->segments) {
            return '';
        }

        $encoded = array_map(self::encodeSegment(...), $this->segments);

        return '/' . implode('/', $encoded);
    }

    private static function decodeSegment(string $segment): string
    {
        if (str_contains($segment, '~')) {
            if (preg_match('/~[^01]/', $segment) || str_ends_with($segment, '~')) {
                throw new JsonPatchException('Invalid pointer escape sequence.');
            }
        }

        return str_replace(['~1', '~0'], ['/', '~'], $segment);
    }

    private static function encodeSegment(string $segment): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $segment);
    }
}
