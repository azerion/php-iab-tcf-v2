<?php

declare(strict_types=1);

namespace Azerion\IabTcf\V2;

use RuntimeException;

final class TcfException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $segment = null,
        public readonly ?int $bitPosition = null,
    ) {
        parent::__construct($message);
    }

    public static function parse(string $message, ?string $segment = null, ?int $bitPosition = null): self
    {
        $context = self::formatContext($segment, $bitPosition);

        return new self("Decode failed: {$message}{$context}", $segment, $bitPosition);
    }

    public static function encode(string $message): self
    {
        return new self("Encode failed: {$message}");
    }

    public static function invalidField(string $field, string $detail): self
    {
        return new self("Invalid value for field '{$field}': {$detail}");
    }

    private static function formatContext(?string $segment, ?int $bitPosition): string
    {
        $parts = [];
        if ($segment !== null) {
            $parts[] = "segment={$segment}";
        }
        if ($bitPosition !== null) {
            $parts[] = "bit={$bitPosition}";
        }

        return $parts === [] ? '' : ' ['.implode(', ', $parts).']';
    }
}
