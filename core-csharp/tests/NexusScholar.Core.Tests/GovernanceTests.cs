using Microsoft.VisualStudio.TestTools.UnitTesting;
using NexusScholar.AI;
using NexusScholar.Artifacts;
using NexusScholar.Bundles;
using NexusScholar.Kernel;
using NexusScholar.Provenance;

namespace NexusScholar.Core.Tests;

[TestClass]
public sealed class GovernanceTests
{
    [TestMethod]
    public void Scientific_model_proposal_requires_human_approval_policy()
    {
        Assert.ThrowsExactly<DomainRuleException>(() => AiTaskPolicy.Create(
            "screen-title-abstract",
            AiAuthority.ScientificDecisionProposal,
            humanApprovalRequired: false,
            evidenceRequired: true,
            externalDataTransferAllowed: false));
    }

    [TestMethod]
    public void Provenance_store_is_append_only_and_rejects_duplicate_identity()
    {
        var ids = new GuidV7IdGenerator();
        var clock = new FixedClock();
        var researchEvent = ResearchEventFactory.Create(
            ids,
            clock,
            "protocol-approved",
            "protocol",
            "p-1",
            ActorId.From("researcher-1"));
        var store = new InMemoryProvenanceStore();
        store.Append(researchEvent);

        Assert.ThrowsExactly<DomainRuleException>(() => store.Append(researchEvent));
    }

    [TestMethod]
    public void Bundle_verifier_rejects_duplicate_paths()
    {
        var digest = ContentDigest.Sha256Utf8("same content");
        var manifest = new ReviewBundleManifest(
            "nexus.review-bundle/v1",
            "project-1",
            digest,
            "workflow-1",
            new FixedClock().UtcNow,
            new[]
            {
                new BundleArtifact("protocol/protocol.json", "application/json", 12, digest),
                new BundleArtifact("protocol/protocol.json", "application/json", 12, digest)
            });

        Assert.IsFalse(new BundleVerifier().Verify(manifest).IsValid);
    }

    private sealed class FixedClock : IClock
    {
        public DateTimeOffset UtcNow { get; } = new(2026, 6, 25, 12, 0, 0, TimeSpan.Zero);
    }
}
