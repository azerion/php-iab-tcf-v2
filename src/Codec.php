<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Shared serialization primitives. Stateless static helpers.
 *
 * @internal
 */
final class Codec
{
    private function __construct()
    {
    }

    public static function base64UrlEncode(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $value): string
    {
        $normalised = strtr($value, '-_', '+/');
        $remainder = strlen($normalised) % 4;
        if ($remainder !== 0) {
            $normalised .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode($normalised, true);
        if ($decoded === false) {
            throw TcfException::parse('invalid base64url payload');
        }

        return $decoded;
    }

    public static function deciToDateTime(int $deci): DateTimeImmutable
    {
        $seconds = intdiv($deci, 10);
        $fraction = $deci % 10;
        $formatted = sprintf('%d.%d00000', $seconds, $fraction);

        $dt = DateTimeImmutable::createFromFormat('U.u', $formatted, new DateTimeZone('UTC'));
        if ($dt === false) {
            throw TcfException::parse("invalid timestamp deciseconds={$deci}");
        }

        return $dt->setTimezone(new DateTimeZone('UTC'));
    }

    public static function dateTimeToDeci(DateTimeImmutable $dt): int
    {
        $seconds = (int) $dt->format('U');
        $micros = (int) $dt->format('u');

        return $seconds * 10 + intdiv($micros, 100_000);
    }

    /**
     * Read a vendor vector (MaxVendorId, IsRangeEncoding, body). Returns sorted unique IDs.
     *
     * @return list<int>
     */
    public static function decodeVendorVector(BitReader $reader): array
    {
        $maxVendorId = $reader->readInt(16);
        $isRange = $reader->readBool();

        if (! $isRange) {
            return $reader->readBitfield($maxVendorId);
        }

        return self::decodeRanges($reader);
    }

    /**
     * Write a vendor vector with optimal bitfield-vs-range selection.
     *
     * @param  list<int>  $ids  arbitrary (will be sorted + deduped)
     */
    public static function encodeVendorVector(BitWriter $writer, array $ids): void
    {
        $sorted = self::sortUnique($ids);
        $maxVendorId = $sorted === [] ? 0 : $sorted[array_key_last($sorted)];
        $ranges = self::buildRanges($sorted);

        $bitfieldBits = $maxVendorId;
        $rangeBits = 12;
        foreach ($ranges as [$start, $end]) {
            $rangeBits += $start === $end ? 17 : 33;
        }

        $writer->writeInt($maxVendorId, 16);

        if ($rangeBits < $bitfieldBits) {
            $writer->writeBool(true);
            self::encodeRanges($writer, $ranges);
        } else {
            $writer->writeBool(false);
            $writer->writeBitfield($sorted, $maxVendorId);
        }
    }

    /**
     * Read a list of vendor ranges (NumEntries + entries). Returns expanded sorted unique IDs.
     *
     * @return list<int>
     */
    public static function decodeRanges(BitReader $reader): array
    {
        $numEntries = $reader->readInt(12);
        $ids = [];
        for ($i = 0; $i < $numEntries; $i++) {
            $isRange = $reader->readBool();
            $start = $reader->readInt(16);
            $end = $isRange ? $reader->readInt(16) : $start;
            if ($end < $start) {
                throw TcfException::parse("range end {$end} < start {$start}", bitPosition: $reader->position());
            }
            for ($v = $start; $v <= $end; $v++) {
                $ids[] = $v;
            }
        }

        return self::sortUnique($ids);
    }

    /**
     * Write a list of vendor ranges as (NumEntries, entries...).
     *
     * @param  list<array{int,int}>  $ranges  list of [start, end] pairs (start <= end)
     */
    public static function encodeRanges(BitWriter $writer, array $ranges): void
    {
        $writer->writeInt(count($ranges), 12);
        foreach ($ranges as [$start, $end]) {
            if ($end < $start) {
                throw TcfException::encode("range end {$end} < start {$start}");
            }
            $writer->writeBool($start !== $end);
            $writer->writeInt($start, 16);
            if ($start !== $end) {
                $writer->writeInt($end, 16);
            }
        }
    }

    /**
     * Group consecutive ints into [start, end] ranges. Input must be sorted unique.
     *
     * @param  list<int>  $sorted
     * @return list<array{int,int}>
     */
    public static function buildRanges(array $sorted): array
    {
        if ($sorted === []) {
            return [];
        }
        $ranges = [];
        $start = $sorted[0];
        $prev = $start;
        $count = count($sorted);
        for ($i = 1; $i < $count; $i++) {
            $current = $sorted[$i];
            if ($current === $prev + 1) {
                $prev = $current;

                continue;
            }
            $ranges[] = [$start, $prev];
            $start = $current;
            $prev = $current;
        }
        $ranges[] = [$start, $prev];

        return $ranges;
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public static function sortUnique(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $unique = array_values(array_unique($ids));
        sort($unique);

        return $unique;
    }
}
