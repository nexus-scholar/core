using NexusScholar.Kernel;

namespace NexusScholar.Provenance;

public interface IProvenanceStore
{
    void Append(ResearchEvent researchEvent);

    IReadOnlyList<ResearchEvent> ReadAll();
}

public sealed class InMemoryProvenanceStore : IProvenanceStore
{
    private readonly List<ResearchEvent> _events = new();

    public void Append(ResearchEvent researchEvent)
    {
        ArgumentNullException.ThrowIfNull(researchEvent);
        if (_events.Any(existing => existing.Id == researchEvent.Id))
        {
            throw new DomainRuleException($"Research event '{researchEvent.Id}' already exists.");
        }

        _events.Add(researchEvent);
    }

    public IReadOnlyList<ResearchEvent> ReadAll() => _events.ToArray();
}
