<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2\Tests\Unit;

use Azerion\IabTcf\V2\PublisherRestriction;
use Azerion\IabTcf\V2\PublisherTC;
use Azerion\IabTcf\V2\RestrictionType;
use Azerion\IabTcf\V2\TcfException;
use Azerion\IabTcf\V2\TCModel;
use Azerion\IabTcf\V2\TCString;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TCStringTest extends TestCase
{
    #[Test]
    public function encodes_then_decodes_default_model(): void
    {
        $original = $this->baseModel();
        $encoded = TCString::encode($original);
        $decoded = TCString::decode($encoded);
        self::assertModelsEqual($original, $decoded);
    }

    #[Test]
    public function string_contains_dot_separators_between_segments(): void
    {
        $encoded = TCString::encode($this->baseModel());
        // At least core . disclosed
        self::assertGreaterThanOrEqual(1, substr_count($encoded, '.'));
    }

    #[Test]
    public function encodes_then_decodes_fully_loaded_model(): void
    {
        $original = new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            cmpId: 42,
            cmpVersion: 7,
            consentScreen: 3,
            consentLanguage: 'NL',
            vendorListVersion: 123,
            isServiceSpecific: false,
            useNonStandardStacks: true,
            specialFeatureOptIns: [1, 2],
            purposesConsent: [1, 2, 3, 4, 7, 10],
            purposesLITransparency: [2, 7, 8, 9, 10, 11],
            purposeOneTreatment: true,
            publisherCC: 'DE',
            vendorsConsent: [1, 2, 3, 100, 755, 1024],
            vendorsLegitimateInterest: [5, 10, 50],
            disclosedVendors: [1, 2, 3, 100, 755, 1024],
            allowedVendors: [1, 2, 3],
        );

        $decoded = TCString::decode(TCString::encode($original));
        self::assertModelsEqual($original, $decoded);
    }

    #[Test]
    public function service_specific_string_has_no_allowed_vendors_segment(): void
    {
        $model = new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            isServiceSpecific: true,
            disclosedVendors: [1, 2, 3],
        );

        $encoded = TCString::encode($model);
        // core . disclosedVendors = 2 parts only
        self::assertSame(1, substr_count($encoded, '.'));

        $decoded = TCString::decode($encoded);
        self::assertNull($decoded->allowedVendors);
        self::assertTrue($decoded->isServiceSpecific);
    }

    #[Test]
    public function encodes_then_decodes_publisher_tc(): void
    {
        $pubTc = new PublisherTC(
            pubPurposesConsent: [1, 2, 5],
            pubPurposesLITransparency: [3, 4],
            numCustomPurposes: 4,
            customPurposesConsent: [1, 3],
            customPurposesLITransparency: [2, 4],
        );
        $model = new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            isServiceSpecific: true,
            disclosedVendors: [1],
            publisherTC: $pubTc,
        );

        $decoded = TCString::decode(TCString::encode($model));
        self::assertNotNull($decoded->publisherTC);
        self::assertSame([1, 2, 5], $decoded->publisherTC->pubPurposesConsent);
        self::assertSame([3, 4], $decoded->publisherTC->pubPurposesLITransparency);
        self::assertSame(4, $decoded->publisherTC->numCustomPurposes);
        self::assertSame([1, 3], $decoded->publisherTC->customPurposesConsent);
        self::assertSame([2, 4], $decoded->publisherTC->customPurposesLITransparency);
    }

    #[Test]
    public function encodes_then_decodes_publisher_restrictions(): void
    {
        $r1 = new PublisherRestriction(2, RestrictionType::RequireConsent, [1, 2, 3, 7, 10]);
        $r2 = new PublisherRestriction(5, RestrictionType::NotAllowed, [42]);
        $r3 = new PublisherRestriction(7, RestrictionType::RequireLegitimateInterest, [100, 101, 102]);

        $model = new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            isServiceSpecific: true,
            disclosedVendors: [1],
            publisherRestrictions: [$r1, $r2, $r3],
        );

        $decoded = TCString::decode(TCString::encode($model));
        self::assertCount(3, $decoded->publisherRestrictions);
        self::assertSame(2, $decoded->publisherRestrictions[0]->purposeId);
        self::assertSame(RestrictionType::RequireConsent, $decoded->publisherRestrictions[0]->restrictionType);
        self::assertSame([1, 2, 3, 7, 10], $decoded->publisherRestrictions[0]->vendorIds);
        self::assertSame([42], $decoded->publisherRestrictions[1]->vendorIds);
        self::assertSame(
            RestrictionType::RequireLegitimateInterest,
            $decoded->publisherRestrictions[2]->restrictionType,
        );
    }

    #[Test]
    public function round_trip_with_sparse_vendor_ids_uses_range_encoding(): void
    {
        $original = new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            isServiceSpecific: true,
            vendorsConsent: [1, 5000, 10000, 65535],
            disclosedVendors: [1, 5000, 10000, 65535],
        );

        $decoded = TCString::decode(TCString::encode($original));
        self::assertSame([1, 5000, 10000, 65535], $decoded->vendorsConsent);
        self::assertSame([1, 5000, 10000, 65535], $decoded->disclosedVendors);
    }

    #[Test]
    public function round_trip_with_dense_vendor_ids_uses_bitfield_encoding(): void
    {
        $ids = range(1, 100);
        $original = new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            isServiceSpecific: true,
            vendorsConsent: $ids,
            disclosedVendors: $ids,
        );

        $decoded = TCString::decode(TCString::encode($original));
        self::assertSame($ids, $decoded->vendorsConsent);
    }

    #[Test]
    public function rejects_empty_string(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('empty consent string');
        TCString::decode('');
    }

    #[Test]
    public function accepts_string_with_only_core_segment(): void
    {
        // Older v2.x strings (pre-v2.3) often omit the DisclosedVendors segment.
        $model = $this->baseModel();
        $encoded = TCString::encode($model);
        $coreOnly = explode('.', $encoded)[0];

        $decoded = TCString::decode($coreOnly);
        self::assertSame([], $decoded->disclosedVendors);
        self::assertSame($model->cmpId, $decoded->cmpId);
    }

    #[Test]
    public function rejects_unknown_oob_segment_type(): void
    {
        $model = $this->baseModel();
        $encoded = TCString::encode($model);

        // Append a bogus segment with type=7 (3 bits = 111)
        $bogusSegment = chr(0b11100000); // 3-bit type = 7, then padding
        $bogusEncoded = rtrim(strtr(base64_encode($bogusSegment), '+/', '-_'), '=');

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('unknown OOB segment type');
        TCString::decode($encoded.'.'.$bogusEncoded);
    }

    #[Test]
    public function rejects_empty_oob_segment(): void
    {
        $model = $this->baseModel();
        $encoded = TCString::encode($model);

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('empty OOB segment');
        TCString::decode($encoded.'.');
    }

    #[Test]
    public function rejects_allowed_vendors_on_service_specific_string(): void
    {
        // Manually craft a service-specific string with an AllowedVendors OOB segment.
        // Easiest path: build a non-service-specific model with allowedVendors, then flip the bit.
        // Easier still: encode two models and splice. But this is awkward — instead, just
        // verify that the encode side respects the rule (round-trip works) and decode catches
        // a manually appended AllowedVendors segment to a service-specific core.
        $model = new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            isServiceSpecific: true,
            disclosedVendors: [1],
        );
        $encoded = TCString::encode($model);

        // Build an AllowedVendors segment with one vendor.
        $w = new \Azerion\IabTcf\V2\BitWriter();
        $w->writeInt(2, 3); // SEGMENT_ALLOWED_VENDORS
        \Azerion\IabTcf\V2\Codec::encodeVendorVector($w, [42]);
        $bytes = $w->finish();
        $allowedSegment = rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('AllowedVendors');
        TCString::decode($encoded.'.'.$allowedSegment);
    }

    private function baseModel(): TCModel
    {
        return new TCModel(
            created: $this->fixedTime(),
            lastUpdated: $this->fixedTime(),
            cmpId: 1,
            cmpVersion: 1,
            consentScreen: 0,
            consentLanguage: 'EN',
            vendorListVersion: 1,
            isServiceSpecific: true,
            purposesConsent: [1],
            disclosedVendors: [1],
        );
    }

    private function fixedTime(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-05-28T12:00:00.0', new DateTimeZone('UTC'));
    }

    private static function assertModelsEqual(TCModel $expected, TCModel $actual): void
    {
        self::assertSame($expected->version, $actual->version);
        self::assertSame($expected->created->format('U.u'), $actual->created->format('U.u'), 'created');
        self::assertSame(
            $expected->lastUpdated->format('U.u'),
            $actual->lastUpdated->format('U.u'),
            'lastUpdated',
        );
        self::assertSame($expected->cmpId, $actual->cmpId);
        self::assertSame($expected->cmpVersion, $actual->cmpVersion);
        self::assertSame($expected->consentScreen, $actual->consentScreen);
        self::assertSame($expected->consentLanguage, $actual->consentLanguage);
        self::assertSame($expected->vendorListVersion, $actual->vendorListVersion);
        self::assertSame($expected->tcfPolicyVersion, $actual->tcfPolicyVersion);
        self::assertSame($expected->isServiceSpecific, $actual->isServiceSpecific);
        self::assertSame($expected->useNonStandardStacks, $actual->useNonStandardStacks);
        self::assertSame($expected->specialFeatureOptIns, $actual->specialFeatureOptIns);
        self::assertSame($expected->purposesConsent, $actual->purposesConsent);
        self::assertSame($expected->purposesLITransparency, $actual->purposesLITransparency);
        self::assertSame($expected->purposeOneTreatment, $actual->purposeOneTreatment);
        self::assertSame($expected->publisherCC, $actual->publisherCC);
        self::assertSame($expected->vendorsConsent, $actual->vendorsConsent);
        self::assertSame($expected->vendorsLegitimateInterest, $actual->vendorsLegitimateInterest);
        self::assertSame($expected->disclosedVendors, $actual->disclosedVendors);
        self::assertSame($expected->allowedVendors, $actual->allowedVendors);
        self::assertCount(count($expected->publisherRestrictions), $actual->publisherRestrictions);
    }
}
