<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

/**
 * Append-only bit buffer. Pad to byte boundary with finish().
 *
 * @internal
 */
final class BitWriter
{
    private string $bits = '';

    public function position(): int
    {
        return strlen($this->bits);
    }

    public function writeInt(int $value, int $bits): void
    {
        if ($bits < 1 || $bits > 36) {
            throw TcfException::encode("writeInt out of range: {$bits} bits.");
        }
        if ($value < 0) {
            throw TcfException::encode("writeInt negative value: {$value}.");
        }
        $max = (1 << $bits) - 1;
        if ($value > $max) {
            throw TcfException::encode("writeInt value {$value} does not fit in {$bits} bits.");
        }
        $this->bits .= str_pad(decbin($value), $bits, '0', STR_PAD_LEFT);
    }

    public function writeBool(bool $value): void
    {
        $this->bits .= $value ? '1' : '0';
    }

    /**
     * Write a fixed-length bitfield from a 1-indexed list of set IDs.
     *
     * @param  list<int>  $ids
     */
    public function writeBitfield(array $ids, int $length): void
    {
        if ($length < 0) {
            throw TcfException::encode("writeBitfield negative length: {$length}.");
        }
        $bits = str_repeat('0', $length);
        foreach ($ids as $id) {
            if ($id < 1 || $id > $length) {
                throw TcfException::encode("bitfield id {$id} out of range 1..{$length}.");
            }
            $bits[$id - 1] = '1';
        }
        $this->bits .= $bits;
    }

    /**
     * Write a fixed-char six-bit string. Input must be uppercase A..Z.
     */
    public function writeSixBitString(string $value, int $chars): void
    {
        if (strlen($value) !== $chars) {
            throw TcfException::encode("six-bit string '{$value}' must be exactly {$chars} chars.");
        }
        for ($i = 0; $i < $chars; $i++) {
            $ord = ord($value[$i]);
            if ($ord < ord('A') || $ord > ord('Z')) {
                throw TcfException::encode("six-bit char must be A..Z, got '{$value[$i]}'.");
            }
            $this->writeInt($ord - ord('A'), 6);
        }
    }

    /**
     * Pad with zero bits to next byte boundary and return packed bytes.
     */
    public function finish(): string
    {
        $padded = $this->bits;
        $remainder = strlen($padded) % 8;
        if ($remainder !== 0) {
            $padded .= str_repeat('0', 8 - $remainder);
        }
        $bytes = '';
        $byteCount = intdiv(strlen($padded), 8);
        for ($i = 0; $i < $byteCount; $i++) {
            $bytes .= chr(((int) bindec(substr($padded, $i * 8, 8))) & 0xFF);
        }

        return $bytes;
    }
}
