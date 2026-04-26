<?php

declare(strict_types=1);

namespace Nexus\Shared\ValueObject;

/**
 * Immutable identifier for a scholarly work in a specific namespace.
 * Always stored in normalized form — normalization is applied in the constructor.
 */
final class WorkId
{
    public readonly string $value;

    public function __construct(
        public readonly WorkIdNamespace $namespace,
        string $rawValue,
    ) {
        $this->value = self::normalize($namespace, $rawValue);
    }

    private static function normalize(WorkIdNamespace $ns, string $raw): string
    {
        $raw = trim($raw);

        return match ($ns) {
            WorkIdNamespace::DOI => strtolower(
                ltrim(
                    ltrim(
                        ltrim($raw, 'https://doi.org/'),
                        'http://dx.doi.org/'
                    ),
                    'doi:'
                )
            ),
            WorkIdNamespace::ARXIV => strtolower(
                preg_replace('/^arxiv:/i', '', $raw)
            ),
            default => strtolower($raw),
        };
    }

    public function equals(WorkId $other): bool
    {
        return $this->namespace === $other->namespace
            && $this->value === $other->value;
    }

    /** Returns "doi:10.1234/abc" or "arxiv:2301.12345" etc. */
    public function toString(): string
    {
        return $this->namespace->value . ':' . $this->value;
    }

    /**
     * Parse "doi:10.x", "arxiv:...", "openalex:W...", etc.
     *
     * @throws \InvalidArgumentException if prefix is missing or unrecognised
     */
    public static function fromString(string $s): self
    {
        $parts = explode(':', $s, 2);

        if (count($parts) !== 2 || $parts[1] === '') {
            throw new \InvalidArgumentException(
                "Cannot parse WorkId from \"{$s}\". Expected \"<namespace>:<value>\"."
            );
        }

        [$prefix, $value] = $parts;

        $ns = WorkIdNamespace::tryFrom(strtolower($prefix));

        if ($ns === null) {
            throw new \InvalidArgumentException(
                "Unknown WorkId namespace \"{$prefix}\" in \"{$s}\"."
            );
        }

        return new self($ns, $value);
    }
}
