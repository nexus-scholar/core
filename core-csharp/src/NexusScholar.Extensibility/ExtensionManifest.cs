using NexusScholar.Kernel;

namespace NexusScholar.Extensibility;

public sealed record ExtensionManifest(
    string Id,
    string Version,
    string EntryPoint,
    IReadOnlySet<ExtensionCapability> RequestedCapabilities)
{
    public static ExtensionManifest Create(
        string id,
        string version,
        string entryPoint,
        IEnumerable<ExtensionCapability> requestedCapabilities)
    {
        ArgumentNullException.ThrowIfNull(requestedCapabilities);
        return new ExtensionManifest(
            Guard.NotBlank(id, nameof(id)),
            Guard.NotBlank(version, nameof(version)),
            Guard.NotBlank(entryPoint, nameof(entryPoint)),
            requestedCapabilities.ToHashSet());
    }
}
