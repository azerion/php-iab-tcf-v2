<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

final readonly class PublisherRestriction
{
    /** @var list<int> */
    public array $vendorIds;

    /**
     * @param  list<int>  $vendorIds
     */
    public function __construct(
        public int $purposeId,
        public RestrictionType $restrictionType,
        array $vendorIds = [],
    ) {
        if ($purposeId < 1 || $purposeId > 24) {
            throw TcfException::invalidField('purposeId', "must be 1..24, got {$purposeId}");
        }
        foreach ($vendorIds as $id) {
            if ($id < 1 || $id > 0xFFFF) {
                throw TcfException::invalidField(
                    'publisherRestriction.vendorIds',
                    "vendor id {$id} out of range 1..65535",
                );
            }
        }
        $this->vendorIds = Codec::sortUnique($vendorIds);
    }
}
