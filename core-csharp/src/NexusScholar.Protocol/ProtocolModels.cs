using NexusScholar.Artifacts;
using NexusScholar.Kernel;

namespace NexusScholar.Protocol;

public sealed class ProtocolTag
{
}

public sealed class ProtocolVersionTag
{
}

public enum ProtocolStatus
{
    Draft,
    Approved,
    Superseded
}

public sealed record ProtocolDecision(
    string Key,
    string Value,
    ActorId DecidedBy,
    DateTimeOffset DecidedAt);

public sealed record ProtocolVersion(
    EntityId<ProtocolVersionTag> Id,
    EntityId<ProtocolTag> ProtocolId,
    int Version,
    IReadOnlyList<ProtocolDecision> Decisions,
    ContentDigest Digest,
    ActorId ApprovedBy,
    DateTimeOffset ApprovedAt,
    EntityId<ProtocolVersionTag>? Supersedes = null);
