<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

enum RestrictionType: int
{
    case NotAllowed = 0;
    case RequireConsent = 1;
    case RequireLegitimateInterest = 2;

    public static function fromWire(int $value): self
    {
        return self::tryFrom($value)
            ?? throw TcfException::parse("unknown RestrictionType value {$value}");
    }
}
