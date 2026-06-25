using NexusScholar.Artifacts;
using NexusScholar.Kernel;

namespace NexusScholar.Provenance;

public sealed class ProvenanceEventTag
{
}

public sealed record ResearchEvent(
    EntityId<ProvenanceEventTag> Id,
    string Activity,
    string SubjectType,
    string SubjectId,
    ActorId PerformedBy,
    DateTimeOffset OccurredAt,
    IReadOnlyList<ContentDigest> Inputs,
    IReadOnlyList<ContentDigest> Outputs);
