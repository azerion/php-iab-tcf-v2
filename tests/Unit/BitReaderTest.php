<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2\Tests\Unit;

use Azerion\IabTcf\V2\BitReader;
use Azerion\IabTcf\V2\TcfException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BitReaderTest extends TestCase
{
    #[Test]
    public function reads_int_across_byte_boundary(): void
    {
        // bytes: 10110101 11001010 -> reading 12 bits gives 101101011100 = 2908
        $r = new BitReader("\xb5\xca");
        self::assertSame(2908, $r->readInt(12));
        self::assertSame(12, $r->position());
        self::assertSame(4, $r->remaining());
    }

    #[Test]
    public function reads_bool(): void
    {
        $r = new BitReader("\xb0"); // 1011 0000
        self::assertTrue($r->readBool());
        self::assertFalse($r->readBool());
        self::assertTrue($r->readBool());
        self::assertTrue($r->readBool());
        self::assertSame(4, $r->position());
    }

    #[Test]
    public function reads_bitfield_returns_sorted_one_indexed_ids(): void
    {
        // bits 1010 0001 -> ids {1, 3, 8}
        $r = new BitReader("\xa1");
        self::assertSame([1, 3, 8], $r->readBitfield(8));
    }

    #[Test]
    public function bitfield_of_zero_length_is_empty(): void
    {
        $r = new BitReader("\x00");
        self::assertSame([], $r->readBitfield(0));
        self::assertSame(0, $r->position());
    }

    #[Test]
    public function reads_six_bit_string(): void
    {
        // 'EN' -> E=4, N=13 -> 000100 001101 = 00010000 11010000 = 0x10 0xd0
        $r = new BitReader("\x10\xd0");
        self::assertSame('EN', $r->readSixBitString(2));
    }

    #[Test]
    public function six_bit_value_above_25_throws(): void
    {
        // 011010 = 26 (out of A..Z), pad with zero bits to make a full byte
        $r = new BitReader("\x68"); // 01101000

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('six-bit char out of A..Z range');
        $r->readSixBitString(1);
    }

    #[Test]
    public function read_past_end_throws_with_position(): void
    {
        $r = new BitReader("\xff"); // 8 bits available
        $r->readInt(8);

        try {
            $r->readInt(1);
            self::fail('expected exception');
        } catch (TcfException $e) {
            self::assertSame(8, $e->bitPosition);
            self::assertStringContainsString('unexpected end of buffer', $e->getMessage());
        }
    }

    #[Test]
    public function read_int_rejects_out_of_range_bit_count(): void
    {
        $r = new BitReader("\xff");

        $this->expectException(TcfException::class);
        $r->readInt(37);
    }

    #[Test]
    public function has_more_reflects_remaining(): void
    {
        $r = new BitReader("\xff\xff"); // 16 bits
        self::assertTrue($r->hasMore(16));
        self::assertFalse($r->hasMore(17));
        $r->readInt(10);
        self::assertTrue($r->hasMore(6));
        self::assertFalse($r->hasMore(7));
    }
}
