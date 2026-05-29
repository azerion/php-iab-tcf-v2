<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

/**
 * Stateful bit cursor over a binary buffer.
 *
 * Internally keeps both the raw bytes and a 1s-and-0s view, using whichever is fastest
 * for each access pattern: substr+bindec for small fixed-width integer reads, and
 * byte-aligned bit-shifts with zero-byte skip for large bitfield reads.
 *
 * @internal
 */
final class BitReader
{
    /** @var array<string,string>|null  byte char => 8-bit string, built once */
    private static ?array $byteToBitsLookUpTable = null;

    private readonly string $bytes;

    private readonly string $bits;

    private readonly int $length;

    private int $position = 0;

    public function __construct(string $bytes)
    {
        $this->bytes = $bytes;
        $lookUpTable = self::$byteToBitsLookUpTable ??= self::buildLookUpTable();
        $this->bits = strtr($bytes, $lookUpTable);
        $this->length = strlen($this->bits);
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
     * Reads byte-aligned: zero-bytes are skipped without per-bit work; non-zero bytes are
     * unrolled into 8 mask tests. Much faster than scanning the bit-string char by char.
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

        $pos = $this->position;
        $this->position = $pos + $length;

        $bitOffset = $pos & 7;
        $byteIdx = $pos >> 3;
        $ids = [];
        $idBase = 1;
        $remaining = $length;

        // Handle unaligned leading bits in the first byte.
        if ($bitOffset !== 0) {
            $bitsInFirst = 8 - $bitOffset;
            if ($bitsInFirst > $remaining) {
                $bitsInFirst = $remaining;
            }
            $byte = ord($this->bytes[$byteIdx]);
            for ($b = 0; $b < $bitsInFirst; $b++) {
                if ((($byte >> (7 - $bitOffset - $b)) & 1) === 1) {
                    $ids[] = $idBase + $b;
                }
            }
            $idBase += $bitsInFirst;
            $byteIdx++;
            $remaining -= $bitsInFirst;
        }

        // Whole-byte fast path: skip zero bytes outright, unroll bit checks for non-zero.
        while ($remaining >= 8) {
            $byte = ord($this->bytes[$byteIdx]);
            if ($byte !== 0) {
                if (($byte & 0x80) !== 0) {
                    $ids[] = $idBase;
                }
                if (($byte & 0x40) !== 0) {
                    $ids[] = $idBase + 1;
                }
                if (($byte & 0x20) !== 0) {
                    $ids[] = $idBase + 2;
                }
                if (($byte & 0x10) !== 0) {
                    $ids[] = $idBase + 3;
                }
                if (($byte & 0x08) !== 0) {
                    $ids[] = $idBase + 4;
                }
                if (($byte & 0x04) !== 0) {
                    $ids[] = $idBase + 5;
                }
                if (($byte & 0x02) !== 0) {
                    $ids[] = $idBase + 6;
                }
                if (($byte & 0x01) !== 0) {
                    $ids[] = $idBase + 7;
                }
            }
            $idBase += 8;
            $byteIdx++;
            $remaining -= 8;
        }

        // Trailing partial byte (high bits only).
        if ($remaining > 0) {
            $byte = ord($this->bytes[$byteIdx]);
            for ($b = 0; $b < $remaining; $b++) {
                if ((($byte >> (7 - $b)) & 1) === 1) {
                    $ids[] = $idBase + $b;
                }
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
            if ($value < 0 || $value > 25) {
                throw TcfException::parse(
                    "six-bit char out of A..Z range: value={$value}.",
                    bitPosition: $this->position - 6,
                );
            }
            $out .= chr(65 + $value);
        }

        return $out;
    }

    /**
     * @return array<string,string>
     */
    private static function buildLookUpTable(): array
    {
        $lookUpTable = [];
        for ($i = 0; $i < 256; $i++) {
            $lookUpTable[chr($i)] = str_pad(decbin($i), 8, '0', STR_PAD_LEFT);
        }

        return $lookUpTable;
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
