<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Decoded IAB TCF v2.x consent string. Immutable value object.
 *
 * Construct directly with named arguments, or call TCModel::new() for a model
 * pre-filled with sensible defaults (version=2, tcfPolicyVersion=5, timestamps=now).
 *
 * Spec reference: https://github.com/InteractiveAdvertisingBureau/GDPR-Transparency-and-Consent-Framework/blob/master/TCFv2/IAB%20Tech%20Lab%20-%20Consent%20string%20and%20vendor%20list%20formats%20v2.md
 */
final readonly class TCModel
{
    public DateTimeImmutable $created;

    public DateTimeImmutable $lastUpdated;

    /** @var list<int> */
    public array $specialFeatureOptIns;

    /** @var list<int> */
    public array $purposesConsent;

    /** @var list<int> */
    public array $purposesLITransparency;

    /** @var list<int> */
    public array $vendorsConsent;

    /** @var list<int> */
    public array $vendorsLegitimateInterest;

    /** @var list<PublisherRestriction> */
    public array $publisherRestrictions;

    /** @var list<int> */
    public array $disclosedVendors;

    /** @var list<int>|null */
    public ?array $allowedVendors;

    /**
     * @param  list<int>  $specialFeatureOptIns
     * @param  list<int>  $purposesConsent
     * @param  list<int>  $purposesLITransparency
     * @param  list<int>  $vendorsConsent
     * @param  list<int>  $vendorsLegitimateInterest
     * @param  list<PublisherRestriction>  $publisherRestrictions
     * @param  list<int>  $disclosedVendors
     * @param  list<int>|null  $allowedVendors  null when service-specific
     */
    public function __construct(
        public int $version = 2,
        ?DateTimeImmutable $created = null,
        ?DateTimeImmutable $lastUpdated = null,
        public int $cmpId = 0,
        public int $cmpVersion = 0,
        public int $consentScreen = 0,
        public string $consentLanguage = 'EN',
        public int $vendorListVersion = 0,
        public int $tcfPolicyVersion = 5,
        public bool $isServiceSpecific = false,
        public bool $useNonStandardStacks = false,
        array $specialFeatureOptIns = [],
        array $purposesConsent = [],
        array $purposesLITransparency = [],
        public bool $purposeOneTreatment = false,
        public string $publisherCC = 'AA',
        array $vendorsConsent = [],
        array $vendorsLegitimateInterest = [],
        array $publisherRestrictions = [],
        array $disclosedVendors = [],
        ?array $allowedVendors = null,
        public ?PublisherTC $publisherTC = null,
    ) {
        if ($version !== 2) {
            throw TcfException::invalidField('version', "must be 2, got {$version}");
        }
        self::assertUnsignedFits('tcfPolicyVersion', $tcfPolicyVersion, 6);
        self::assertUnsignedFits('cmpId', $cmpId, 12);
        self::assertUnsignedFits('cmpVersion', $cmpVersion, 12);
        self::assertUnsignedFits('consentScreen', $consentScreen, 6);
        self::assertUnsignedFits('vendorListVersion', $vendorListVersion, 12);
        self::assertTwoLetterUpper('consentLanguage', $consentLanguage);
        self::assertTwoLetterUpper('publisherCC', $publisherCC);
        self::assertIdsInRange('specialFeatureOptIns', $specialFeatureOptIns, 12);
        self::assertIdsInRange('purposesConsent', $purposesConsent, 24);
        self::assertIdsInRange('purposesLITransparency', $purposesLITransparency, 24);
        self::assertVendorIds('vendorsConsent', $vendorsConsent);
        self::assertVendorIds('vendorsLegitimateInterest', $vendorsLegitimateInterest);
        self::assertVendorIds('disclosedVendors', $disclosedVendors);
        if ($allowedVendors !== null) {
            self::assertVendorIds('allowedVendors', $allowedVendors);
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $this->created = $created ?? $now;
        $this->lastUpdated = $lastUpdated ?? $now;
        $this->specialFeatureOptIns = Codec::sortUnique($specialFeatureOptIns);
        $this->purposesConsent = Codec::sortUnique($purposesConsent);
        $this->purposesLITransparency = Codec::sortUnique($purposesLITransparency);
        $this->vendorsConsent = Codec::sortUnique($vendorsConsent);
        $this->vendorsLegitimateInterest = Codec::sortUnique($vendorsLegitimateInterest);
        $this->publisherRestrictions = $publisherRestrictions;
        $this->disclosedVendors = Codec::sortUnique($disclosedVendors);
        $this->allowedVendors = $allowedVendors === null ? null : Codec::sortUnique($allowedVendors);
    }

    public static function new(): self
    {
        return new self();
    }

    private static function assertUnsignedFits(string $field, int $value, int $bits): void
    {
        $max = (1 << $bits) - 1;
        if ($value < 0 || $value > $max) {
            throw TcfException::invalidField($field, "must be 0..{$max}, got {$value}");
        }
    }

    private static function assertTwoLetterUpper(string $field, string $value): void
    {
        if (preg_match('/^[A-Z]{2}$/', $value) !== 1) {
            throw TcfException::invalidField($field, "must be 2 uppercase A..Z letters, got '{$value}'");
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function assertIdsInRange(string $field, array $ids, int $max): void
    {
        foreach ($ids as $id) {
            if ($id < 1 || $id > $max) {
                throw TcfException::invalidField($field, "id {$id} out of range 1..{$max}");
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function assertVendorIds(string $field, array $ids): void
    {
        foreach ($ids as $id) {
            if ($id < 1 || $id > 0xFFFF) {
                throw TcfException::invalidField($field, "vendor id {$id} out of range 1..65535");
            }
        }
    }
}
