<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2\Tests\Integration;

use Azerion\IabTcf\V2\TcfException;
use Azerion\IabTcf\V2\TCModel;
use Azerion\IabTcf\V2\TCString;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Decode real consent strings harvested in-the-wild and verify they round-trip
 * through encode + decode without losing information.
 *
 * Fixtures live in fixtures.txt — one consent string per line.
 */
final class RealWorldStringsTest extends TestCase
{
    #[Test]
    #[DataProvider('realStrings')]
    public function decodes_and_round_trips(string $original): void
    {
        $first = TCString::decode($original);
        $reencoded = TCString::encode($first);
        $second = TCString::decode($reencoded);

        $this->assertModelsEquivalent($first, $second);
    }

    #[Test]
    public function rejects_tcf_v1_string(): void
    {
        // Real v1 string from the wild (starts with 'B' = version 1)
        $v1 = 'BO2jBQlO2jBRwAHABAlxCb-AAAAo1rv___7__9_-____9uz7Ov_v_f__33e8779v_h_7_-___u_-3zd4u_1vf99yfm1-7ctr3tp_87uesm_Xur__59__3z3_9phP78k89r7337Ew4MA';

        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('version');
        TCString::decode($v1);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function realStrings(): iterable
    {
        $path = __DIR__.'/fixtures.txt';
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new \RuntimeException("Failed to read fixtures file: {$path}");
        }

        foreach (explode("\n", trim($contents)) as $i => $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            yield "fixture #{$i} (".strlen($line).' chars)' => [$line];
        }
    }

    private function assertModelsEquivalent(TCModel $a, TCModel $b): void
    {
        self::assertSame($a->version, $b->version);
        self::assertSame($a->created->format('U.u'), $b->created->format('U.u'));
        self::assertSame($a->lastUpdated->format('U.u'), $b->lastUpdated->format('U.u'));
        self::assertSame($a->cmpId, $b->cmpId);
        self::assertSame($a->cmpVersion, $b->cmpVersion);
        self::assertSame($a->consentScreen, $b->consentScreen);
        self::assertSame($a->consentLanguage, $b->consentLanguage);
        self::assertSame($a->vendorListVersion, $b->vendorListVersion);
        self::assertSame($a->tcfPolicyVersion, $b->tcfPolicyVersion);
        self::assertSame($a->isServiceSpecific, $b->isServiceSpecific);
        self::assertSame($a->useNonStandardStacks, $b->useNonStandardStacks);
        self::assertSame($a->specialFeatureOptIns, $b->specialFeatureOptIns);
        self::assertSame($a->purposesConsent, $b->purposesConsent);
        self::assertSame($a->purposesLITransparency, $b->purposesLITransparency);
        self::assertSame($a->purposeOneTreatment, $b->purposeOneTreatment);
        self::assertSame($a->publisherCC, $b->publisherCC);
        self::assertSame($a->vendorsConsent, $b->vendorsConsent);
        self::assertSame($a->vendorsLegitimateInterest, $b->vendorsLegitimateInterest);
        self::assertSame($a->disclosedVendors, $b->disclosedVendors);
        self::assertSame($a->allowedVendors, $b->allowedVendors);
        self::assertCount(count($a->publisherRestrictions), $b->publisherRestrictions);
        foreach ($a->publisherRestrictions as $i => $r) {
            self::assertSame($r->purposeId, $b->publisherRestrictions[$i]->purposeId);
            self::assertSame($r->restrictionType, $b->publisherRestrictions[$i]->restrictionType);
            self::assertSame($r->vendorIds, $b->publisherRestrictions[$i]->vendorIds);
        }
        if ($a->publisherTC === null) {
            self::assertNull($b->publisherTC);
        } else {
            self::assertNotNull($b->publisherTC);
            self::assertSame($a->publisherTC->pubPurposesConsent, $b->publisherTC->pubPurposesConsent);
            self::assertSame($a->publisherTC->pubPurposesLITransparency, $b->publisherTC->pubPurposesLITransparency);
            self::assertSame($a->publisherTC->numCustomPurposes, $b->publisherTC->numCustomPurposes);
            self::assertSame($a->publisherTC->customPurposesConsent, $b->publisherTC->customPurposesConsent);
            self::assertSame(
                $a->publisherTC->customPurposesLITransparency,
                $b->publisherTC->customPurposesLITransparency,
            );
        }
    }
}
