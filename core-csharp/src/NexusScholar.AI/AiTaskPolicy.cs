using NexusScholar.Kernel;

namespace NexusScholar.AI;

public enum AiAuthority
{
    ReadOnlySuggestion,
    BoundedTransformation,
    ScientificDecisionProposal,
    ExternalActionProposal
}

public sealed record AiTaskPolicy(
    string TaskType,
    AiAuthority Authority,
    bool HumanApprovalRequired,
    bool EvidenceRequired,
    bool ExternalDataTransferAllowed)
{
    public static AiTaskPolicy Create(
        string taskType,
        AiAuthority authority,
        bool humanApprovalRequired,
        bool evidenceRequired,
        bool externalDataTransferAllowed)
    {
        if (authority is AiAuthority.ScientificDecisionProposal or AiAuthority.ExternalActionProposal &&
            !humanApprovalRequired)
        {
            throw new DomainRuleException("High-authority AI tasks require a recorded human approval step.");
        }

        return new AiTaskPolicy(
            Guard.NotBlank(taskType, nameof(taskType)),
            authority,
            humanApprovalRequired,
            evidenceRequired,
            externalDataTransferAllowed);
    }
}
