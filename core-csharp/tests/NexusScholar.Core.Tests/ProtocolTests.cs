using Microsoft.VisualStudio.TestTools.UnitTesting;
using NexusScholar.Kernel;
using NexusScholar.Protocol;

namespace NexusScholar.Core.Tests;

[TestClass]
public sealed class ProtocolTests
{
    private static readonly ActorId Researcher = ActorId.From("researcher-1");
    private static readonly IClock Clock = new FixedClock();

    [TestMethod]
    public void Approval_requires_all_declared_decisions()
    {
        var ids = new GuidV7IdGenerator();
        var draft = ProtocolDraft.Create(ids, "Review", new[] { "review-type", "scope" });
        draft.RecordDecision("review-type", "scoping-review", Researcher, Clock);

        Assert.ThrowsExactly<DomainRuleException>(() => draft.Approve(Researcher, Clock, ids));
    }

    [TestMethod]
    public void Approval_digest_is_independent_of_decision_entry_order()
    {
        var ids = new GuidV7IdGenerator();
        var first = ProtocolDraft.Create(ids, "Review", new[] { "review-type", "scope" });
        first.RecordDecision("review-type", "scoping-review", Researcher, Clock);
        first.RecordDecision("scope", "agriculture", Researcher, Clock);

        var second = ProtocolDraft.Create(ids, "Review", new[] { "review-type", "scope" });
        second.RecordDecision("scope", "agriculture", Researcher, Clock);
        second.RecordDecision("review-type", "scoping-review", Researcher, Clock);

        Assert.AreEqual(
            first.Approve(Researcher, Clock, ids).Digest,
            second.Approve(Researcher, Clock, ids).Digest);
    }

    [TestMethod]
    public void Approved_draft_cannot_be_mutated()
    {
        var ids = new GuidV7IdGenerator();
        var draft = ProtocolDraft.Create(ids, "Review", new[] { "review-type" });
        draft.RecordDecision("review-type", "systematic-review", Researcher, Clock);
        _ = draft.Approve(Researcher, Clock, ids);

        Assert.ThrowsExactly<DomainRuleException>(() =>
            draft.RecordDecision("scope", "changed", Researcher, Clock));
    }

    private sealed class FixedClock : IClock
    {
        public DateTimeOffset UtcNow { get; } = new(2026, 6, 25, 12, 0, 0, TimeSpan.Zero);
    }
}
