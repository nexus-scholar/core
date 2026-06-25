using Microsoft.VisualStudio.TestTools.UnitTesting;
using NexusScholar.AI;
using NexusScholar.Artifacts;
using NexusScholar.Bundles;
using NexusScholar.Extensibility;
using NexusScholar.Kernel;
using NexusScholar.Protocol;
using NexusScholar.Provenance;
using NexusScholar.Workflow;

namespace NexusScholar.Architecture.Tests;

[TestClass]
public sealed class DependencyRulesTests
{
    private static readonly string[] ForbiddenPrefixes =
    {
        "Microsoft.EntityFrameworkCore",
        "Microsoft.AspNetCore",
        "Avalonia",
        "Amazon.S3",
        "OpenAI"
    };

    [TestMethod]
    public void Domain_projects_do_not_reference_host_or_provider_frameworks()
    {
        var assemblies = new[]
        {
            typeof(IClock).Assembly,
            typeof(ContentDigest).Assembly,
            typeof(ProtocolDraft).Assembly,
            typeof(WorkflowDefinition).Assembly,
            typeof(ResearchEvent).Assembly,
            typeof(ReviewBundleManifest).Assembly,
            typeof(ExtensionManifest).Assembly,
            typeof(AiTaskPolicy).Assembly
        }.Distinct();

        foreach (var assembly in assemblies)
        {
            var forbidden = assembly.GetReferencedAssemblies()
                .Select(reference => reference.Name ?? string.Empty)
                .Where(name => ForbiddenPrefixes.Any(prefix => name.StartsWith(prefix, StringComparison.Ordinal)))
                .ToArray();

            Assert.AreEqual(
                0,
                forbidden.Length,
                $"{assembly.GetName().Name} has forbidden references: {string.Join(", ", forbidden)}");
        }
    }
}
