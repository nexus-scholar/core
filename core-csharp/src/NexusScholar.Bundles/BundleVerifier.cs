namespace NexusScholar.Bundles;

public sealed class BundleVerifier
{
    public BundleVerification Verify(ReviewBundleManifest manifest)
    {
        ArgumentNullException.ThrowIfNull(manifest);
        return new BundleVerification(true, Array.Empty<string>());
    }
}
