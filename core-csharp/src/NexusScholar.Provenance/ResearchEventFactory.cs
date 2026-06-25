using NexusScholar.Artifacts;
using NexusScholar.Kernel;

namespace NexusScholar.Provenance;

public static class ResearchEventFactory
{
    public static ResearchEvent Create(
        IIdGenerator ids,
        IClock clock,
        string activity,
        string subjectType,
        string subjectId,
        ActorId performedBy,
        IEnumerable<ContentDigest>? inputs = null,
        IEnumerable<ContentDigest>? outputs = null)
    {
        ArgumentNullException.ThrowIfNull(ids);
        ArgumentNullException.ThrowIfNull(clock);

        return new ResearchEvent(
            EntityId<ProvenanceEventTag>.New(ids),
            Guard.NotBlank(activity, nameof(activity)),
            Guard.NotBlank(subjectType, nameof(subjectType)),
            Guard.NotBlank(subjectId, nameof(subjectId)),
            performedBy,
            clock.UtcNow,
            inputs?.ToArray() ?? Array.Empty<ContentDigest>(),
            outputs?.ToArray() ?? Array.Empty<ContentDigest>());
    }
}
