<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2\Tests\Unit;

use Azerion\IabTcf\V2\BitReader;
use Azerion\IabTcf\V2\BitWriter;
use Azerion\IabTcf\V2\Codec;
use Azerion\IabTcf\V2\TcfException;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CodecTest extends TestCase
{
    #[Test]
    public function base64_url_round_trips_with_no_padding(): void
    {
        $raw = random_bytes(32);
        $encoded = Codec::base64UrlEncode($raw);
        self::assertStringNotContainsString('=', $encoded);
        self::assertStringNotContainsString('+', $encoded);
        self::assertStringNotContainsString('/', $encoded);
        self::assertSame($raw, Codec::base64UrlDecode($encoded));
    }

    #[Test]
    public function base64_url_decodes_with_optional_padding(): void
    {
        self::assertSame('foo', Codec::base64UrlDecode('Zm9v'));
        self::assertSame('fo', Codec::base64UrlDecode('Zm8'));
        self::assertSame('fo', Codec::base64UrlDecode('Zm8='));
    }

    #[Test]
    public function base64_url_invalid_payload_throws(): void
    {
        $this->expectException(TcfException::class);
        Codec::base64UrlDecode('!!!not-base64!!!');
    }

    #[Test]
    public function deci_seconds_round_trip(): void
    {
        $dt = new DateTimeImmutable('2026-05-28T12:34:56.700000Z', new DateTimeZone('UTC'));
        $deci = Codec::dateTimeToDeci($dt);
        self::assertSame($dt->format('U.u'), Codec::deciToDateTime($deci)->format('U.u'));
    }

    #[Test]
    public function deci_seconds_truncate_below_decisecond(): void
    {
        $dt = new DateTimeImmutable('2026-05-28T12:34:56.789000Z', new DateTimeZone('UTC'));
        $deci = Codec::dateTimeToDeci($dt);
        $restored = Codec::deciToDateTime($deci);
        // 0.789s truncates to 0.7s
        self::assertSame('1779971696.700000', $restored->format('U.u'));
    }

    #[Test]
    public function build_ranges_groups_consecutive_ints(): void
    {
        self::assertSame(
            [[1, 3], [7, 7], [10, 12]],
            Codec::buildRanges([1, 2, 3, 7, 10, 11, 12]),
        );
        self::assertSame([], Codec::buildRanges([]));
        self::assertSame([[5, 5]], Codec::buildRanges([5]));
    }

    #[Test]
    public function vendor_vector_round_trip_picks_optimal_encoding(): void
    {
        // Sparse + high max -> ranges win
        $sparse = [1, 2, 3, 1000];
        $writer = new BitWriter();
        Codec::encodeVendorVector($writer, $sparse);
        $reader = new BitReader($writer->finish());
        self::assertSame($sparse, Codec::decodeVendorVector($reader));

        // Dense + low max -> bitfield wins
        $dense = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $writer = new BitWriter();
        Codec::encodeVendorVector($writer, $dense);
        $reader = new BitReader($writer->finish());
        self::assertSame($dense, Codec::decodeVendorVector($reader));

        // Empty
        $writer = new BitWriter();
        Codec::encodeVendorVector($writer, []);
        $reader = new BitReader($writer->finish());
        self::assertSame([], Codec::decodeVendorVector($reader));
    }

    #[Test]
    public function vendor_vector_sorts_and_dedupes_input(): void
    {
        $writer = new BitWriter();
        Codec::encodeVendorVector($writer, [5, 1, 3, 1, 2]);
        $reader = new BitReader($writer->finish());
        self::assertSame([1, 2, 3, 5], Codec::decodeVendorVector($reader));
    }

    #[Test]
    public function decode_ranges_handles_single_and_range_entries(): void
    {
        $writer = new BitWriter();
        Codec::encodeRanges($writer, [[1, 1], [5, 7], [100, 200]]);
        $reader = new BitReader($writer->finish());
        $ids = Codec::decodeRanges($reader);
        self::assertSame([1, 5, 6, 7, ...range(100, 200)], $ids);
    }

    #[Test]
    public function decode_ranges_rejects_inverted_range(): void
    {
        // Manually craft NumEntries=1, IsRange=1, Start=10, End=5
        $writer = new BitWriter();
        $writer->writeInt(1, 12);
        $writer->writeBool(true);
        $writer->writeInt(10, 16);
        $writer->writeInt(5, 16);
        $reader = new BitReader($writer->finish());

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('range end 5 < start 10');
        Codec::decodeRanges($reader);
    }
}
