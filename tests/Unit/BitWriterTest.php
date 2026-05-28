<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2\Tests\Unit;

use Azerion\IabTcf\V2\BitReader;
use Azerion\IabTcf\V2\BitWriter;
use Azerion\IabTcf\V2\TcfException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BitWriterTest extends TestCase
{
    #[Test]
    public function round_trips_with_reader(): void
    {
        $w = new BitWriter();
        $w->writeInt(42, 6);
        $w->writeBool(true);
        $w->writeInt(2026, 12);
        $w->writeSixBitString('DE', 2);
        $w->writeBitfield([1, 5, 12], 12);

        $r = new BitReader($w->finish());
        self::assertSame(42, $r->readInt(6));
        self::assertTrue($r->readBool());
        self::assertSame(2026, $r->readInt(12));
        self::assertSame('DE', $r->readSixBitString(2));
        self::assertSame([1, 5, 12], $r->readBitfield(12));
    }

    #[Test]
    public function finish_pads_to_byte_boundary(): void
    {
        $w = new BitWriter();
        $w->writeInt(1, 3); // 3 bits, needs 5 zero pad
        self::assertSame("\x20", $w->finish()); // 001 00000 = 0x20
    }

    #[Test]
    public function finish_on_empty_returns_empty_string(): void
    {
        self::assertSame('', (new BitWriter())->finish());
    }

    #[Test]
    public function value_does_not_fit_throws(): void
    {
        $w = new BitWriter();

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('does not fit in 6 bits');
        $w->writeInt(64, 6);
    }

    #[Test]
    public function negative_value_throws(): void
    {
        $w = new BitWriter();

        $this->expectException(TcfException::class);
        $w->writeInt(-1, 6);
    }

    #[Test]
    public function bitfield_id_out_of_range_throws(): void
    {
        $w = new BitWriter();

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('bitfield id 9 out of range');
        $w->writeBitfield([9], 8);
    }

    #[Test]
    public function six_bit_string_rejects_lowercase(): void
    {
        $w = new BitWriter();

        $this->expectException(TcfException::class);
        $w->writeSixBitString('en', 2);
    }

    #[Test]
    public function six_bit_string_wrong_length_throws(): void
    {
        $w = new BitWriter();

        $this->expectException(TcfException::class);
        $w->writeSixBitString('ABC', 2);
    }
}
