<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

use DateTimeImmutable;

/**
 * Decode and encode IAB TCF v2.x consent strings.
 *
 * Spec: https://github.com/InteractiveAdvertisingBureau/GDPR-Transparency-and-Consent-Framework/blob/master/TCFv2/IAB%20Tech%20Lab%20-%20Consent%20string%20and%20vendor%20list%20formats%20v2.md
 */
final class TCString
{
    private const SEGMENT_DISCLOSED_VENDORS = 1;

    private const SEGMENT_ALLOWED_VENDORS = 2;

    private const SEGMENT_PUBLISHER_TC = 3;

    private function __construct()
    {
    }

    public static function decode(string $consentString): TCModel
    {
        if ($consentString === '') {
            throw TcfException::parse('empty consent string');
        }
        $parts = explode('.', $consentString);
        if ($parts[0] === '') {
            throw TcfException::parse('missing core segment');
        }

        $core = self::decodeCoreSegment(new BitReader(Codec::base64UrlDecode($parts[0])));

        $disclosedVendors = null;
        $allowedVendors = null;
        $publisherTC = null;

        $partCount = count($parts);
        for ($i = 1; $i < $partCount; $i++) {
            if ($parts[$i] === '') {
                throw TcfException::parse('empty OOB segment');
            }
            $reader = new BitReader(Codec::base64UrlDecode($parts[$i]));
            $type = $reader->readInt(3);
            match ($type) {
                self::SEGMENT_DISCLOSED_VENDORS => $disclosedVendors = Codec::decodeVendorVector($reader),
                self::SEGMENT_ALLOWED_VENDORS => $allowedVendors = Codec::decodeVendorVector($reader),
                self::SEGMENT_PUBLISHER_TC => $publisherTC = self::decodePublisherTC($reader),
                default => throw TcfException::parse("unknown OOB segment type {$type}"),
            };
        }

        if ($core['isServiceSpecific'] && $allowedVendors !== null) {
            throw TcfException::parse('AllowedVendors segment must not be present for service-specific strings');
        }

        $disclosedVendors ??= [];

        return new TCModel(
            version: $core['version'],
            created: $core['created'],
            lastUpdated: $core['lastUpdated'],
            cmpId: $core['cmpId'],
            cmpVersion: $core['cmpVersion'],
            consentScreen: $core['consentScreen'],
            consentLanguage: $core['consentLanguage'],
            vendorListVersion: $core['vendorListVersion'],
            tcfPolicyVersion: $core['tcfPolicyVersion'],
            isServiceSpecific: $core['isServiceSpecific'],
            useNonStandardStacks: $core['useNonStandardStacks'],
            specialFeatureOptIns: $core['specialFeatureOptIns'],
            purposesConsent: $core['purposesConsent'],
            purposesLITransparency: $core['purposesLITransparency'],
            purposeOneTreatment: $core['purposeOneTreatment'],
            publisherCC: $core['publisherCC'],
            vendorsConsent: $core['vendorsConsent'],
            vendorsLegitimateInterest: $core['vendorsLegitimateInterest'],
            publisherRestrictions: $core['publisherRestrictions'],
            disclosedVendors: $disclosedVendors,
            allowedVendors: $allowedVendors,
            publisherTC: $publisherTC,
        );
    }

    public static function encode(TCModel $model): string
    {
        $parts = [Codec::base64UrlEncode(self::encodeCoreSegment($model))];
        $parts[] = Codec::base64UrlEncode(self::encodeDisclosedVendors($model));
        if (! $model->isServiceSpecific && $model->allowedVendors !== null) {
            $parts[] = Codec::base64UrlEncode(self::encodeAllowedVendors($model));
        }
        if ($model->publisherTC !== null) {
            $parts[] = Codec::base64UrlEncode(self::encodePublisherTC($model->publisherTC));
        }

        return implode('.', $parts);
    }

    /**
     * @return array{
     *   version: int,
     *   created: DateTimeImmutable,
     *   lastUpdated: DateTimeImmutable,
     *   cmpId: int,
     *   cmpVersion: int,
     *   consentScreen: int,
     *   consentLanguage: string,
     *   vendorListVersion: int,
     *   tcfPolicyVersion: int,
     *   isServiceSpecific: bool,
     *   useNonStandardStacks: bool,
     *   specialFeatureOptIns: list<int>,
     *   purposesConsent: list<int>,
     *   purposesLITransparency: list<int>,
     *   purposeOneTreatment: bool,
     *   publisherCC: string,
     *   vendorsConsent: list<int>,
     *   vendorsLegitimateInterest: list<int>,
     *   publisherRestrictions: list<PublisherRestriction>,
     * }
     */
    private static function decodeCoreSegment(BitReader $r): array
    {
        $version = $r->readInt(6);
        if ($version !== 2) {
            throw TcfException::parse("unsupported TCF version {$version}; only version=2 is supported", 'core');
        }

        return [
            'version' => $version,
            'created' => Codec::deciToDateTime($r->readInt(36)),
            'lastUpdated' => Codec::deciToDateTime($r->readInt(36)),
            'cmpId' => $r->readInt(12),
            'cmpVersion' => $r->readInt(12),
            'consentScreen' => $r->readInt(6),
            'consentLanguage' => $r->readSixBitString(2),
            'vendorListVersion' => $r->readInt(12),
            'tcfPolicyVersion' => $r->readInt(6),
            'isServiceSpecific' => $r->readBool(),
            'useNonStandardStacks' => $r->readBool(),
            'specialFeatureOptIns' => $r->readBitfield(12),
            'purposesConsent' => $r->readBitfield(24),
            'purposesLITransparency' => $r->readBitfield(24),
            'purposeOneTreatment' => $r->readBool(),
            'publisherCC' => $r->readSixBitString(2),
            'vendorsConsent' => Codec::decodeVendorVector($r),
            'vendorsLegitimateInterest' => Codec::decodeVendorVector($r),
            'publisherRestrictions' => self::decodePublisherRestrictions($r),
        ];
    }

    private static function encodeCoreSegment(TCModel $m): string
    {
        $w = new BitWriter();
        $w->writeInt($m->version, 6);
        $w->writeInt(Codec::dateTimeToDeci($m->created), 36);
        $w->writeInt(Codec::dateTimeToDeci($m->lastUpdated), 36);
        $w->writeInt($m->cmpId, 12);
        $w->writeInt($m->cmpVersion, 12);
        $w->writeInt($m->consentScreen, 6);
        $w->writeSixBitString($m->consentLanguage, 2);
        $w->writeInt($m->vendorListVersion, 12);
        $w->writeInt($m->tcfPolicyVersion, 6);
        $w->writeBool($m->isServiceSpecific);
        $w->writeBool($m->useNonStandardStacks);
        $w->writeBitfield($m->specialFeatureOptIns, 12);
        $w->writeBitfield($m->purposesConsent, 24);
        $w->writeBitfield($m->purposesLITransparency, 24);
        $w->writeBool($m->purposeOneTreatment);
        $w->writeSixBitString($m->publisherCC, 2);
        Codec::encodeVendorVector($w, $m->vendorsConsent);
        Codec::encodeVendorVector($w, $m->vendorsLegitimateInterest);
        self::encodePublisherRestrictions($w, $m->publisherRestrictions);

        return $w->finish();
    }

    /**
     * @return list<PublisherRestriction>
     */
    private static function decodePublisherRestrictions(BitReader $r): array
    {
        $numRestrictions = $r->readInt(12);
        $out = [];
        for ($i = 0; $i < $numRestrictions; $i++) {
            $purposeId = $r->readInt(6);
            $type = RestrictionType::fromWire($r->readInt(2));
            $vendorIds = Codec::decodeRanges($r);
            $out[] = new PublisherRestriction($purposeId, $type, $vendorIds);
        }

        return $out;
    }

    /**
     * @param  list<PublisherRestriction>  $restrictions
     */
    private static function encodePublisherRestrictions(BitWriter $w, array $restrictions): void
    {
        $w->writeInt(count($restrictions), 12);
        foreach ($restrictions as $restriction) {
            $w->writeInt($restriction->purposeId, 6);
            $w->writeInt($restriction->restrictionType->value, 2);
            Codec::encodeRanges($w, Codec::buildRanges($restriction->vendorIds));
        }
    }

    private static function encodeDisclosedVendors(TCModel $m): string
    {
        $w = new BitWriter();
        $w->writeInt(self::SEGMENT_DISCLOSED_VENDORS, 3);
        Codec::encodeVendorVector($w, $m->disclosedVendors);

        return $w->finish();
    }

    private static function encodeAllowedVendors(TCModel $m): string
    {
        if ($m->allowedVendors === null) {
            throw TcfException::encode('cannot encode AllowedVendors when model has none');
        }
        $w = new BitWriter();
        $w->writeInt(self::SEGMENT_ALLOWED_VENDORS, 3);
        Codec::encodeVendorVector($w, $m->allowedVendors);

        return $w->finish();
    }

    private static function decodePublisherTC(BitReader $r): PublisherTC
    {
        $pubPurposesConsent = $r->readBitfield(24);
        $pubPurposesLI = $r->readBitfield(24);
        $numCustomPurposes = $r->readInt(6);
        $customConsent = $r->readBitfield($numCustomPurposes);
        $customLI = $r->readBitfield($numCustomPurposes);

        return new PublisherTC(
            pubPurposesConsent: $pubPurposesConsent,
            pubPurposesLITransparency: $pubPurposesLI,
            numCustomPurposes: $numCustomPurposes,
            customPurposesConsent: $customConsent,
            customPurposesLITransparency: $customLI,
        );
    }

    private static function encodePublisherTC(PublisherTC $tc): string
    {
        $w = new BitWriter();
        $w->writeInt(self::SEGMENT_PUBLISHER_TC, 3);
        $w->writeBitfield($tc->pubPurposesConsent, 24);
        $w->writeBitfield($tc->pubPurposesLITransparency, 24);
        $w->writeInt($tc->numCustomPurposes, 6);
        $w->writeBitfield($tc->customPurposesConsent, $tc->numCustomPurposes);
        $w->writeBitfield($tc->customPurposesLITransparency, $tc->numCustomPurposes);

        return $w->finish();
    }
}
