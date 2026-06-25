namespace NexusScholar.Extensibility;

public sealed record CapabilitySelection(
    string ExtensionId,
    IReadOnlySet<ExtensionCapability> Capabilities);
