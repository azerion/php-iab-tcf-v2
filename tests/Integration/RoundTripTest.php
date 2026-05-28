<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2\Tests\Integration;

use Azerion\IabTcf\V2\PublisherRestriction;
use Azerion\IabTcf\V2\PublisherTC;
use Azerion\IabTcf\V2\RestrictionType;
use Azerion\IabTcf\V2\TCModel;
use Azerion\IabTcf\V2\TCString;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RoundTripTest extends TestCase
{
    #[Test]
    #[DataProvider('models')]
    public function model_round_trips_through_encode_and_decode(TCModel $original): void
    {
        $encoded = TCString::encode($original);
        $decoded = TCString::decode($encoded);

        self::assertSame($original->version, $decoded->version);
        self::assertSame($original->created->format('U.u'), $decoded->created->format('U.u'));
        self::assertSame($original->lastUpdated->format('U.u'), $decoded->lastUpdated->format('U.u'));
        self::assertSame($original->cmpId, $decoded->cmpId);
        self::assertSame($original->cmpVersion, $decoded->cmpVersion);
        self::assertSame($original->consentScreen, $decoded->consentScreen);
        self::assertSame($original->consentLanguage, $decoded->consentLanguage);
        self::assertSame($original->vendorListVersion, $decoded->vendorListVersion);
        self::assertSame($original->tcfPolicyVersion, $decoded->tcfPolicyVersion);
        self::assertSame($original->isServiceSpecific, $decoded->isServiceSpecific);
        self::assertSame($original->useNonStandardStacks, $decoded->useNonStandardStacks);
        self::assertSame($original->specialFeatureOptIns, $decoded->specialFeatureOptIns);
        self::assertSame($original->purposesConsent, $decoded->purposesConsent);
        self::assertSame($original->purposesLITransparency, $decoded->purposesLITransparency);
        self::assertSame($original->purposeOneTreatment, $decoded->purposeOneTreatment);
        self::assertSame($original->publisherCC, $decoded->publisherCC);
        self::assertSame($original->vendorsConsent, $decoded->vendorsConsent);
        self::assertSame($original->vendorsLegitimateInterest, $decoded->vendorsLegitimateInterest);
        self::assertSame($original->disclosedVendors, $decoded->disclosedVendors);
        self::assertSame($original->allowedVendors, $decoded->allowedVendors);

        self::assertCount(count($original->publisherRestrictions), $decoded->publisherRestrictions);
        foreach ($original->publisherRestrictions as $i => $r) {
            self::assertSame($r->purposeId, $decoded->publisherRestrictions[$i]->purposeId);
            self::assertSame($r->restrictionType, $decoded->publisherRestrictions[$i]->restrictionType);
            self::assertSame($r->vendorIds, $decoded->publisherRestrictions[$i]->vendorIds);
        }

        if ($original->publisherTC === null) {
            self::assertNull($decoded->publisherTC);
        } else {
            self::assertNotNull($decoded->publisherTC);
            self::assertSame($original->publisherTC->pubPurposesConsent, $decoded->publisherTC->pubPurposesConsent);
            self::assertSame(
                $original->publisherTC->pubPurposesLITransparency,
                $decoded->publisherTC->pubPurposesLITransparency,
            );
            self::assertSame($original->publisherTC->numCustomPurposes, $decoded->publisherTC->numCustomPurposes);
            self::assertSame(
                $original->publisherTC->customPurposesConsent,
                $decoded->publisherTC->customPurposesConsent,
            );
            self::assertSame(
                $original->publisherTC->customPurposesLITransparency,
                $decoded->publisherTC->customPurposesLITransparency,
            );
        }
    }

    /**
     * @return iterable<string, array{TCModel}>
     */
    public static function models(): iterable
    {
        $t = self::time();

        yield 'minimal service-specific' => [
            new TCModel(created: $t, lastUpdated: $t, isServiceSpecific: true, disclosedVendors: [1]),
        ];

        yield 'minimal global' => [
            new TCModel(created: $t, lastUpdated: $t, disclosedVendors: [1, 2, 3], allowedVendors: [1, 2]),
        ];

        yield 'all purposes consented' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                purposesConsent: range(1, 11),
                purposesLITransparency: range(2, 11),
                disclosedVendors: [1],
            ),
        ];

        yield 'all special features opted in' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                specialFeatureOptIns: [1, 2],
                disclosedVendors: [1],
            ),
        ];

        yield 'one big consecutive vendor block (bitfield optimal)' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                vendorsConsent: range(1, 500),
                disclosedVendors: range(1, 500),
            ),
        ];

        yield 'sparse 16-bit vendor ids (range optimal)' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                vendorsConsent: [1, 1000, 30000, 65535],
                disclosedVendors: [1, 1000, 30000, 65535],
            ),
        ];

        yield 'mixed ranges and singletons' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                vendorsConsent: [...range(1, 10), 50, ...range(100, 105), 999, 1000],
                disclosedVendors: [...range(1, 10), 50, ...range(100, 105), 999, 1000],
            ),
        ];

        yield 'single publisher restriction' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                disclosedVendors: [1],
                publisherRestrictions: [
                    new PublisherRestriction(2, RestrictionType::RequireConsent, [1, 2, 3]),
                ],
            ),
        ];

        yield 'three publisher restrictions covering all three types' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                disclosedVendors: [1],
                publisherRestrictions: [
                    new PublisherRestriction(1, RestrictionType::NotAllowed, [1, 2, 3]),
                    new PublisherRestriction(2, RestrictionType::RequireConsent, [10, 11, 12]),
                    new PublisherRestriction(7, RestrictionType::RequireLegitimateInterest, [100]),
                ],
            ),
        ];

        yield 'with PublisherTC and no custom purposes' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                disclosedVendors: [1],
                publisherTC: new PublisherTC(
                    pubPurposesConsent: [1, 2, 3],
                    pubPurposesLITransparency: [4, 5],
                ),
            ),
        ];

        yield 'with PublisherTC and custom purposes' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                disclosedVendors: [1],
                publisherTC: new PublisherTC(
                    pubPurposesConsent: [1, 2],
                    pubPurposesLITransparency: [3],
                    numCustomPurposes: 10,
                    customPurposesConsent: [1, 5, 10],
                    customPurposesLITransparency: [2, 4],
                ),
            ),
        ];

        yield 'maximum custom purposes (63)' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: true,
                disclosedVendors: [1],
                publisherTC: new PublisherTC(
                    numCustomPurposes: 63,
                    customPurposesConsent: range(1, 63),
                    customPurposesLITransparency: range(1, 63),
                ),
            ),
        ];

        yield 'french language and country' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                consentLanguage: 'FR',
                publisherCC: 'FR',
                isServiceSpecific: true,
                disclosedVendors: [1],
            ),
        ];

        yield 'purposeOneTreatment set' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                purposeOneTreatment: true,
                consentLanguage: 'FR',
                publisherCC: 'FR',
                isServiceSpecific: true,
                disclosedVendors: [1],
            ),
        ];

        yield 'useNonStandardStacks set' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                useNonStandardStacks: true,
                isServiceSpecific: true,
                disclosedVendors: [1],
            ),
        ];

        yield 'maximum cmp ids' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                cmpId: 4095,
                cmpVersion: 4095,
                consentScreen: 63,
                vendorListVersion: 4095,
                isServiceSpecific: true,
                disclosedVendors: [1],
            ),
        ];

        yield 'global with allowed vendors not equal to disclosed' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: false,
                disclosedVendors: [1, 2, 3, 4, 5],
                allowedVendors: [2, 4],
            ),
        ];

        yield 'global with allowed vendors null (segment omitted)' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                isServiceSpecific: false,
                disclosedVendors: [1, 2, 3],
                allowedVendors: null,
            ),
        ];

        yield 'everything set' => [
            new TCModel(
                created: $t,
                lastUpdated: $t,
                cmpId: 99,
                cmpVersion: 3,
                consentScreen: 1,
                consentLanguage: 'NL',
                vendorListVersion: 200,
                isServiceSpecific: false,
                useNonStandardStacks: true,
                specialFeatureOptIns: [1, 2],
                purposesConsent: [1, 2, 3, 4, 5, 6, 7],
                purposesLITransparency: [2, 7, 8, 9],
                purposeOneTreatment: true,
                publisherCC: 'NL',
                vendorsConsent: [1, 2, 3, 100, 1000, 50000],
                vendorsLegitimateInterest: [1, 2],
                publisherRestrictions: [
                    new PublisherRestriction(2, RestrictionType::RequireConsent, [100, 200, 300]),
                    new PublisherRestriction(7, RestrictionType::NotAllowed, [1, 2, 3]),
                ],
                disclosedVendors: [1, 2, 3, 100, 1000, 50000],
                allowedVendors: [1, 100],
                publisherTC: new PublisherTC(
                    pubPurposesConsent: [1, 3, 5],
                    pubPurposesLITransparency: [2, 4, 6],
                    numCustomPurposes: 5,
                    customPurposesConsent: [1, 3, 5],
                    customPurposesLITransparency: [2, 4],
                ),
            ),
        ];

        yield 'epoch-ish timestamp' => [
            new TCModel(
                created: new DateTimeImmutable('2020-01-01T00:00:00.0Z', new DateTimeZone('UTC')),
                lastUpdated: new DateTimeImmutable('2020-01-01T00:00:00.0Z', new DateTimeZone('UTC')),
                isServiceSpecific: true,
                disclosedVendors: [1],
            ),
        ];

        yield 'odd decisecond timestamp' => [
            new TCModel(
                created: new DateTimeImmutable('2026-05-28T12:34:56.7Z', new DateTimeZone('UTC')),
                lastUpdated: new DateTimeImmutable('2026-05-28T12:34:56.3Z', new DateTimeZone('UTC')),
                isServiceSpecific: true,
                disclosedVendors: [1],
            ),
        ];
    }

    private static function time(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-05-28T12:00:00.0Z', new DateTimeZone('UTC'));
    }
}
