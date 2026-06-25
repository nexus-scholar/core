using NexusScholar.Artifacts;

namespace NexusScholar.Bundles;

public sealed record BundleArtifact(
    string Path,
    string MediaType,
    long SizeBytes,
    ContentDigest Digest);

public sealed record ReviewBundleManifest(
    string SchemaVersion,
    string ProjectId,
    ContentDigest ProtocolDigest,
    string WorkflowId,
    DateTimeOffset CreatedAt,
    IReadOnlyList<BundleArtifact> Artifacts);

public sealed record BundleVerification(bool IsValid, IReadOnlyList<string> Errors);
