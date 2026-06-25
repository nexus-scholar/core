using System.Security.Cryptography;
using System.Text;
using NexusScholar.Kernel;

namespace NexusScholar.Artifacts;

public sealed class ArtifactTag
{
}

public readonly record struct ContentDigest
{
    private ContentDigest(string algorithm, string value)
    {
        Algorithm = algorithm;
        Value = value;
    }

    public string Algorithm { get; }

    public string Value { get; }

    public static ContentDigest Sha256(ReadOnlySpan<byte> content)
    {
        var bytes = SHA256.HashData(content);
        return new ContentDigest("sha256", Convert.ToHexStringLower(bytes));
    }

    public static ContentDigest Sha256Utf8(string content)
    {
        ArgumentNullException.ThrowIfNull(content);
        return Sha256(Encoding.UTF8.GetBytes(content));
    }

    public override string ToString() => $"{Algorithm}:{Value}";
}

public sealed record ArtifactDescriptor(
    EntityId<ArtifactTag> Id,
    string MediaType,
    long SizeBytes,
    ContentDigest Digest,
    string LogicalName)
{
    public static ArtifactDescriptor Create(
        IIdGenerator ids,
        string mediaType,
        long sizeBytes,
        ContentDigest digest,
        string logicalName)
    {
        if (sizeBytes < 0)
        {
            throw new ArgumentOutOfRangeException(nameof(sizeBytes));
        }

        return new ArtifactDescriptor(
            EntityId<ArtifactTag>.New(ids),
            Guard.NotBlank(mediaType, nameof(mediaType)),
            sizeBytes,
            digest,
            Guard.NotBlank(logicalName, nameof(logicalName)));
    }
}
