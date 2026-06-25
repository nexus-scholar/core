namespace NexusScholar.Bundles;

public sealed class BundleVerifier
{
    public BundleVerification Verify(ReviewBundleManifest manifest)
    {
        ArgumentNullException.ThrowIfNull(manifest);
        var errors = new List<string>();

        if (manifest.SchemaVersion != "nexus.review-bundle/v1")
        {
            errors.Add("Unsupported bundle schema version.");
        }

        var duplicatePaths = manifest.Artifacts
            .GroupBy(artifact => artifact.Path, StringComparer.Ordinal)
            .Where(group => group.Count() > 1)
            .Select(group => group.Key)
            .ToArray();

        foreach (var path in duplicatePaths)
        {
            errors.Add($"Duplicate artifact path: {path}.");
        }

        foreach (var artifact in manifest.Artifacts)
        {
            if (artifact.SizeBytes < 0)
            {
                errors.Add($"Negative artifact size: {artifact.Path}.");
            }

            if (artifact.Digest.Value.Length != 64)
            {
                errors.Add($"Invalid artifact digest: {artifact.Path}.");
            }
        }

        return new BundleVerification(errors.Count == 0, errors);
    }
}
