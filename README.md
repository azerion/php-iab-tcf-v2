# azerion/iab-tcf-v2

[![CI](https://github.com/azerion/php-iab-tcf-v2/actions/workflows/ci.yml/badge.svg)](https://github.com/azerion/php-iab-tcf-v2/actions/workflows/ci.yml)
[![License: Apache 2.0](https://img.shields.io/badge/License-Apache_2.0-blue.svg)](LICENSE)

A clean, dependency-free PHP library for **decoding and encoding** [IAB TCF v2.x](https://iabeurope.eu/iab-europe-transparency-consent-framework-policies/) consent strings (v2.0, v2.1, v2.2, v2.3).

- PHP **8.3+**, immutable value objects, fully typed, PHPStan level max.
- **All TCF v2.x policy versions** accepted. `version` must be `2`; `tcfPolicyVersion` is read as-is.
- **No GVL support** — this library is purely a string encoder/decoder. Bring your own Global Vendor List if you need it.
- Public API is just three types: `TCString`, `TCModel`, and the `PublisherTC` / `PublisherRestriction` value objects.

## Installation

```bash
composer require azerion/iab-tcf-v2
```

## Decode

```php
use Azerion\IabTcf\V2\TCString;
use Azerion\IabTcf\V2\TcfException;

try {
    $model = TCString::decode($_COOKIE['euconsent-v2']);
} catch (TcfException $e) {
    // Malformed string, invalid bit layout, etc.
    error_log("TCF decode failed: {$e->getMessage()}");
    return;
}

if (in_array(1, $model->purposesConsent, true)) {
    // User consented to Purpose 1 ("Store and/or access information on a device")
}

if (in_array(755, $model->vendorsConsent, true)) {
    // User consented to vendor 755 (Google)
}
```

## Encode

```php
use Azerion\IabTcf\V2\TCModel;
use Azerion\IabTcf\V2\TCString;

$model = new TCModel(
    cmpId: 42,
    cmpVersion: 1,
    consentLanguage: 'EN',
    vendorListVersion: 200,
    isServiceSpecific: true,
    purposesConsent: [1, 2, 3],
    vendorsConsent: [1, 755, 1024],
    publisherCC: 'NL',
    disclosedVendors: [1, 755, 1024],
);

$consentString = TCString::encode($model);
// e.g. "CQ...A.YA...A"
```

Field validation runs in the `TCModel` constructor — IDs out of range, invalid language codes, or `version !== 2` will throw `TcfException` immediately.

## Field reference

The library's property names match the spec field names converted to camelCase.

| Spec field                  | `TCModel` property            | Type                                                  |
| --------------------------- | ----------------------------- | ----------------------------------------------------- |
| Version                     | `version`                     | `int` (always 2)                                      |
| Created                     | `created`                     | `DateTimeImmutable`                                   |
| LastUpdated                 | `lastUpdated`                 | `DateTimeImmutable`                                   |
| CmpId                       | `cmpId`                       | `int` (0..4095)                                       |
| CmpVersion                  | `cmpVersion`                  | `int` (0..4095)                                       |
| ConsentScreen               | `consentScreen`               | `int` (0..63)                                         |
| ConsentLanguage             | `consentLanguage`             | `string` (ISO 639-1, e.g. `EN`)                       |
| VendorListVersion           | `vendorListVersion`           | `int` (0..4095)                                       |
| TcfPolicyVersion            | `tcfPolicyVersion`            | `int` (2 = v2.0, 3 = v2.1, 4 = v2.2, 5 = v2.3)        |
| IsServiceSpecific           | `isServiceSpecific`           | `bool`                                                |
| UseNonStandardStacks        | `useNonStandardStacks`        | `bool` (a.k.a. `UseNonStandardTexts` in v2.3 docs)    |
| SpecialFeatureOptIns        | `specialFeatureOptIns`        | `list<int>` (1..12)                                   |
| PurposesConsent             | `purposesConsent`             | `list<int>` (1..24)                                   |
| PurposesLITransparency      | `purposesLITransparency`      | `list<int>` (1..24)                                   |
| PurposeOneTreatment         | `purposeOneTreatment`         | `bool`                                                |
| PublisherCC                 | `publisherCC`                 | `string` (ISO 3166-1 alpha-2)                         |
| VendorsConsent              | `vendorsConsent`              | `list<int>`                                           |
| VendorsLegitimateInterest   | `vendorsLegitimateInterest`   | `list<int>`                                           |
| PublisherRestrictions       | `publisherRestrictions`       | `list<PublisherRestriction>`                          |
| DisclosedVendors (segment)  | `disclosedVendors`            | `list<int>` (mandatory in v2.3, optional in v2.0-2.2) |
| AllowedVendors (segment)    | `allowedVendors`              | `?list<int>` (null = absent)                          |
| PublisherTC (segment)       | `publisherTC`                 | `?PublisherTC`                                        |

### `PublisherRestriction`

```php
final readonly class PublisherRestriction
{
    public int $purposeId;                       // 1..24
    public RestrictionType $restrictionType;     // NotAllowed | RequireConsent | RequireLegitimateInterest
    public array $vendorIds;                     // list<int>
}
```

### `PublisherTC`

```php
final readonly class PublisherTC
{
    public array $pubPurposesConsent;             // list<int>, ids 1..24
    public array $pubPurposesLITransparency;      // list<int>, ids 1..24
    public int $numCustomPurposes;                // 0..63
    public array $customPurposesConsent;          // list<int>, ids 1..numCustomPurposes
    public array $customPurposesLITransparency;   // list<int>, ids 1..numCustomPurposes
}
```

## Design notes

- **Re-encoding is not guaranteed to be bit-identical** to the input string. The encoder always picks the smallest representation (bitfield vs. range) per vendor segment, which may differ from the choice the original CMP made. Decoding then re-encoding will produce an equivalent *model*, not necessarily an equivalent *string*.
- **`maxVendorId` and `isRangeEncoding` are never on the model.** They are computed at encode time from the data.
- **Lenient decode, strict encode.** The decoder accepts whatever well-formed bits it sees; the encoder validates aggressively via the `TCModel` constructor.
- **No GVL.** Decoding does not require a Global Vendor List. If you need to look up vendor names or display purposes, fetch the GVL yourself from `https://vendor-list.consensu.org/v3/vendor-list.json` and join on the IDs.
- **Spec scope.** All TCF v2.x policy versions are supported (v2.0 through v2.3). TCF v1.x is **not** supported — `version !== 2` will throw on decode.
- **The `useNonStandardStacks` field** is the original IAB spec name. The v2.3 documentation renamed it to `UseNonStandardTexts`, but the bit position and semantics are identical.

## Versioning

Semantic versioning. Until `1.0.0`, expect minor versions to contain breaking changes.

## License

[Apache-2.0](LICENSE) © Azerion.

## Spec references

- [IAB Tech Lab — Consent string and vendor list formats v2](https://github.com/InteractiveAdvertisingBureau/GDPR-Transparency-and-Consent-Framework/blob/master/TCFv2/IAB%20Tech%20Lab%20-%20Consent%20string%20and%20vendor%20list%20formats%20v2.md)
- [IAB Europe — TCF v2.3 announcement](https://iabeurope.eu/all-you-need-to-know-about-the-transition-to-tcf-v2-3/)
- [iabtcf-es](https://github.com/InteractiveAdvertisingBureau/iabtcf-es) — the reference TypeScript implementation this library is structurally modelled after.
