<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

final class LanguageCode
{
    public readonly string $value;

    public function __construct(string $value)
    {
        if (! preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $value)) {
            throw new \InvalidArgumentException(
                "Invalid language code \"{$value}\". Expected ISO 639-1 (e.g. \"en\") "
                . "or locale (e.g. \"en-US\")."
            );
        }

        $this->value = $value;
    }

    public static function english(): self
    {
        return new self('en');
    }

    public static function french(): self
    {
        return new self('fr');
    }

    public static function arabic(): self
    {
        return new self('ar');
    }

    public function equals(LanguageCode $other): bool
    {
        return $this->value === $other->value;
    }
}
