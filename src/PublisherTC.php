<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

/**
 * Optional Publisher TC segment data. Holds the publisher's own purpose/custom-purpose
 * consent and legitimate-interest vectors.
 */
final readonly class PublisherTC
{
    /** @var list<int> */
    public array $pubPurposesConsent;

    /** @var list<int> */
    public array $pubPurposesLITransparency;

    /** @var list<int> */
    public array $customPurposesConsent;

    /** @var list<int> */
    public array $customPurposesLITransparency;

    /**
     * @param  list<int>  $pubPurposesConsent
     * @param  list<int>  $pubPurposesLITransparency
     * @param  list<int>  $customPurposesConsent
     * @param  list<int>  $customPurposesLITransparency
     */
    public function __construct(
        array $pubPurposesConsent = [],
        array $pubPurposesLITransparency = [],
        public int $numCustomPurposes = 0,
        array $customPurposesConsent = [],
        array $customPurposesLITransparency = [],
    ) {
        if ($numCustomPurposes < 0 || $numCustomPurposes > 63) {
            throw TcfException::invalidField('numCustomPurposes', "must be 0..63, got {$numCustomPurposes}");
        }
        self::assertIdsInRange('pubPurposesConsent', $pubPurposesConsent, 24);
        self::assertIdsInRange('pubPurposesLITransparency', $pubPurposesLITransparency, 24);
        self::assertIdsInRange('customPurposesConsent', $customPurposesConsent, $numCustomPurposes);
        self::assertIdsInRange('customPurposesLITransparency', $customPurposesLITransparency, $numCustomPurposes);

        $this->pubPurposesConsent = Codec::sortUnique($pubPurposesConsent);
        $this->pubPurposesLITransparency = Codec::sortUnique($pubPurposesLITransparency);
        $this->customPurposesConsent = Codec::sortUnique($customPurposesConsent);
        $this->customPurposesLITransparency = Codec::sortUnique($customPurposesLITransparency);
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
}
