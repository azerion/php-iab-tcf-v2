<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

/**
 * Stateful bit cursor over a binary buffer.
 *
 * @internal
 */
final class BitReader
{
    private readonly string $bits;

    private readonly int $length;

    private int $position = 0;

    public function __construct(string $bytes)
    {
        $bits = '';
        $byteLen = strlen($bytes);
        for ($i = 0; $i < $byteLen; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $this->bits = $bits;
        $this->length = strlen($bits);
    }

    public function position(): int
    {
        return $this->position;
    }

    public function remaining(): int
    {
        return $this->length - $this->position;
    }

    public function hasMore(int $bits = 1): bool
    {
        return $this->remaining() >= $bits;
    }

    public function readInt(int $bits): int
    {
        if ($bits < 1 || $bits > 36) {
            throw TcfException::parse("readInt out of range: {$bits} bits requested.");
        }
        $this->guard($bits);
        $slice = substr($this->bits, $this->position, $bits);
        $this->position += $bits;

        return (int) bindec($slice);
    }

    public function readBool(): bool
    {
        $this->guard(1);
        $bit = $this->bits[$this->position];
        $this->position++;

        return $bit === '1';
    }

    /**
     * Read a fixed-length bitfield. Returns 1-indexed IDs whose bit is set, sorted ascending.
     *
     * @return list<int>
     */
    public function readBitfield(int $length): array
    {
        if ($length < 0) {
            throw TcfException::parse("readBitfield negative length: {$length}.");
        }
        if ($length === 0) {
            return [];
        }
        $this->guard($length);
        $slice = substr($this->bits, $this->position, $length);
        $this->position += $length;

        $ids = [];
        for ($i = 0; $i < $length; $i++) {
            if ($slice[$i] === '1') {
                $ids[] = $i + 1;
            }
        }

        return $ids;
    }

    /**
     * Read N six-bit chars, each mapped 0..25 -> A..Z. Throws on values >= 26.
     */
    public function readSixBitString(int $chars): string
    {
        $out = '';
        for ($i = 0; $i < $chars; $i++) {
            $value = $this->readInt(6);
            if ($value > 25) {
                throw TcfException::parse(
                    "six-bit char out of A..Z range: value={$value}.",
                    bitPosition: $this->position - 6,
                );
            }
            $out .= chr(ord('A') + $value);
        }

        return $out;
    }

    private function guard(int $bits): void
    {
        if ($this->position + $bits > $this->length) {
            throw TcfException::parse(
                'unexpected end of buffer',
                bitPosition: $this->position,
            );
        }
    }
}
