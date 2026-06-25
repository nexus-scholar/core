using NexusScholar.Artifacts;
using NexusScholar.Kernel;

namespace NexusScholar.Protocol;

public sealed class ProtocolDraft
{
    private readonly Dictionary<string, ProtocolDecision> _decisions = new(StringComparer.Ordinal);
    private readonly HashSet<string> _requiredDecisionKeys;

    private ProtocolDraft(
        EntityId<ProtocolTag> id,
        string title,
        IEnumerable<string> requiredDecisionKeys)
    {
        Id = id;
        Title = title;
        _requiredDecisionKeys = requiredDecisionKeys
            .Select(key => Guard.NotBlank(key, nameof(requiredDecisionKeys)))
            .ToHashSet(StringComparer.Ordinal);
    }

    public EntityId<ProtocolTag> Id { get; }

    public string Title { get; }

    public ProtocolStatus Status { get; private set; } = ProtocolStatus.Draft;

    public IReadOnlyCollection<ProtocolDecision> Decisions => _decisions.Values.ToArray();

    public static ProtocolDraft Create(
        IIdGenerator ids,
        string title,
        IEnumerable<string> requiredDecisionKeys)
    {
        ArgumentNullException.ThrowIfNull(ids);
        ArgumentNullException.ThrowIfNull(requiredDecisionKeys);

        return new ProtocolDraft(
            EntityId<ProtocolTag>.New(ids),
            Guard.NotBlank(title, nameof(title)),
            requiredDecisionKeys);
    }

    public ProtocolDecision RecordDecision(
        string key,
        string value,
        ActorId actor,
        IClock clock)
    {
        EnsureDraft();
        ArgumentNullException.ThrowIfNull(clock);

        key = Guard.NotBlank(key, nameof(key));
        value = Guard.NotBlank(value, nameof(value));

        if (_decisions.ContainsKey(key))
        {
            throw new DomainRuleException($"Decision '{key}' already exists. Create an explicit revision instead of overwriting it.");
        }

        var decision = new ProtocolDecision(key, value, actor, clock.UtcNow);
        _decisions.Add(key, decision);
        return decision;
    }

    public ProtocolVersion Approve(ActorId actor, IClock clock, IIdGenerator ids)
    {
        EnsureDraft();
        ArgumentNullException.ThrowIfNull(clock);
        ArgumentNullException.ThrowIfNull(ids);

        var missing = _requiredDecisionKeys
            .Except(_decisions.Keys, StringComparer.Ordinal)
            .OrderBy(value => value, StringComparer.Ordinal)
            .ToArray();

        if (missing.Length > 0)
        {
            throw new DomainRuleException($"Protocol cannot be approved. Missing decisions: {string.Join(", ", missing)}.");
        }

        var ordered = _decisions.Values
            .OrderBy(decision => decision.Key, StringComparer.Ordinal)
            .ToArray();
        var digestMaterial = string.Join(
            "\n",
            ordered.Select(decision => $"{decision.Key}={decision.Value}"));
        var version = new ProtocolVersion(
            EntityId<ProtocolVersionTag>.New(ids),
            Id,
            1,
            ordered,
            ContentDigest.Sha256Utf8(digestMaterial),
            actor,
            clock.UtcNow);

        Status = ProtocolStatus.Approved;
        return version;
    }

    private void EnsureDraft()
    {
        if (Status != ProtocolStatus.Draft)
        {
            throw new DomainRuleException("Only a draft protocol can be changed or approved.");
        }
    }
}
