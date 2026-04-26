<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

/**
 * Immutable value object representing a single author.
 * Identity is determined first by ORCID, then by normalized full name.
 */
final class Author
{
    public readonly string $normalizedFullName;

    public function __construct(
        public readonly string   $familyName,
        public readonly ?string  $givenName  = null,
        public readonly ?OrcidId $orcid      = null,
        ?string $normalizedFullName          = null,
    ) {
        $this->normalizedFullName = $normalizedFullName
            ?? self::computeNormalizedName($familyName, $givenName);
    }

    public function fullName(): string
    {
        if ($this->givenName !== null) {
            return $this->givenName . ' ' . $this->familyName;
        }

        return $this->familyName;
    }

    public function hasOrcid(): bool
    {
        return $this->orcid !== null;
    }

    /**
     * Two authors are considered the same person if:
     *   1. Both have ORCID and they match, OR
     *   2. Their normalized full names are identical.
     */
    public function isSamePerson(Author $other): bool
    {
        if ($this->orcid !== null && $other->orcid !== null) {
            return $this->orcid->equals($other->orcid);
        }

        return $this->normalizedFullName === $other->normalizedFullName;
    }

    private static function computeNormalizedName(string $family, ?string $given): string
    {
        $full = $given !== null ? $given . ' ' . $family : $family;

        // lowercase → strip diacritics via transliteration → strip non-alpha/space → collapse
        $normalized = mb_strtolower($full);
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        $stripped = preg_replace('/[^a-z\s]/', '', $transliterated ?? $normalized);

        return trim((string) preg_replace('/\s+/', ' ', $stripped));
    }
}
