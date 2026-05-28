<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2\Tests\Unit;

use Azerion\IabTcf\V2\PublisherRestriction;
use Azerion\IabTcf\V2\PublisherTC;
use Azerion\IabTcf\V2\RestrictionType;
use Azerion\IabTcf\V2\TcfException;
use Azerion\IabTcf\V2\TCModel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TCModelTest extends TestCase
{
    #[Test]
    public function default_model_has_sane_defaults(): void
    {
        $model = TCModel::new();
        self::assertSame(2, $model->version);
        self::assertSame(5, $model->tcfPolicyVersion);
        self::assertSame('EN', $model->consentLanguage);
        self::assertSame('AA', $model->publisherCC);
        self::assertSame([], $model->vendorsConsent);
        self::assertNull($model->allowedVendors);
        self::assertNull($model->publisherTC);
    }

    #[Test]
    public function vendor_consent_is_sorted_and_deduped(): void
    {
        $model = new TCModel(vendorsConsent: [5, 1, 3, 1, 2]);
        self::assertSame([1, 2, 3, 5], $model->vendorsConsent);
    }

    #[Test]
    public function accepts_any_v2_policy_version(): void
    {
        foreach ([2, 3, 4, 5] as $policyVersion) {
            $model = new TCModel(tcfPolicyVersion: $policyVersion);
            self::assertSame($policyVersion, $model->tcfPolicyVersion);
        }
    }

    #[Test]
    public function rejects_policy_version_out_of_six_bit_range(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('tcfPolicyVersion');
        new TCModel(tcfPolicyVersion: 64);
    }

    #[Test]
    public function rejects_invalid_consent_language(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('consentLanguage');
        new TCModel(consentLanguage: 'en');
    }

    #[Test]
    public function rejects_purpose_id_above_24(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('purposesConsent');
        new TCModel(purposesConsent: [25]);
    }

    #[Test]
    public function rejects_special_feature_above_12(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('specialFeatureOptIns');
        new TCModel(specialFeatureOptIns: [13]);
    }

    #[Test]
    public function rejects_vendor_id_above_16_bit(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('vendorsConsent');
        new TCModel(vendorsConsent: [65536]);
    }

    #[Test]
    public function accepts_publisher_restriction(): void
    {
        $r = new PublisherRestriction(2, RestrictionType::RequireConsent, [1, 5, 10]);
        $model = new TCModel(publisherRestrictions: [$r]);
        self::assertCount(1, $model->publisherRestrictions);
        self::assertSame(2, $model->publisherRestrictions[0]->purposeId);
        self::assertSame(RestrictionType::RequireConsent, $model->publisherRestrictions[0]->restrictionType);
        self::assertSame([1, 5, 10], $model->publisherRestrictions[0]->vendorIds);
    }

    #[Test]
    public function publisher_tc_validates_custom_purpose_range(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('customPurposesConsent');
        new PublisherTC(
            numCustomPurposes: 3,
            customPurposesConsent: [4],
        );
    }

    #[Test]
    public function publisher_tc_caps_num_custom_purposes_at_63(): void
    {
        $this->expectException(TcfException::class);
        $this->expectExceptionMessage('numCustomPurposes');
        new PublisherTC(numCustomPurposes: 64);
    }
}
