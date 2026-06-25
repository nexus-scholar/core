using NexusScholar.Artifacts;
using NexusScholar.Kernel;

namespace NexusScholar.AI;

public sealed record AiProposal<T>(
    string TaskType,
    T Value,
    IReadOnlyList<ContentDigest> Evidence,
    DateTimeOffset CreatedAt)
{
    public AcceptedAiProposal<T> Accept(ActorId acceptedBy, IClock clock)
    {
        ArgumentNullException.ThrowIfNull(clock);
        return new AcceptedAiProposal<T>(this, acceptedBy, clock.UtcNow);
    }
}

public sealed record AcceptedAiProposal<T>(
    AiProposal<T> Proposal,
    ActorId AcceptedBy,
    DateTimeOffset AcceptedAt);
