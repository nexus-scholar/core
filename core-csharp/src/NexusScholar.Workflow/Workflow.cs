using NexusScholar.Kernel;
using NexusScholar.Protocol;

namespace NexusScholar.Workflow;

public enum WorkflowNodeKind
{
    HumanTask,
    Approval,
    AutomatedTask,
    Milestone
}

public sealed record WorkflowNode(
    string Id,
    string Label,
    WorkflowNodeKind Kind,
    IReadOnlyList<string> DependsOn);

public sealed class WorkflowDefinition
{
    public WorkflowDefinition(string id, IEnumerable<WorkflowNode> nodes)
    {
        Id = Guard.NotBlank(id, nameof(id));
        Nodes = nodes?.ToArray() ?? throw new ArgumentNullException(nameof(nodes));

        var duplicate = Nodes
            .GroupBy(node => node.Id, StringComparer.Ordinal)
            .FirstOrDefault(group => group.Count() > 1);
        if (duplicate is not null)
        {
            throw new DomainRuleException($"Workflow node id '{duplicate.Key}' is duplicated.");
        }

        var known = Nodes.Select(node => node.Id).ToHashSet(StringComparer.Ordinal);
        var missing = Nodes
            .SelectMany(node => node.DependsOn)
            .Where(dependency => !known.Contains(dependency))
            .Distinct(StringComparer.Ordinal)
            .ToArray();
        if (missing.Length > 0)
        {
            throw new DomainRuleException($"Workflow has unknown dependencies: {string.Join(", ", missing)}.");
        }
    }

    public string Id { get; }

    public IReadOnlyList<WorkflowNode> Nodes { get; }
}

public sealed class WorkflowCompiler
{
    public WorkflowDefinition Compile(ProtocolVersion protocol)
    {
        ArgumentNullException.ThrowIfNull(protocol);

        var nodes = new[]
        {
            new WorkflowNode("protocol-approved", "Protocol approved", WorkflowNodeKind.Milestone, Array.Empty<string>()),
            new WorkflowNode("prepare-search", "Prepare and review search plan", WorkflowNodeKind.HumanTask, new[] { "protocol-approved" }),
            new WorkflowNode("approve-search", "Approve executable search manifest", WorkflowNodeKind.Approval, new[] { "prepare-search" }),
            new WorkflowNode("execute-search", "Execute approved search manifest", WorkflowNodeKind.AutomatedTask, new[] { "approve-search" }),
            new WorkflowNode("lock-corpus", "Approve immutable corpus snapshot", WorkflowNodeKind.Approval, new[] { "execute-search" })
        };

        return new WorkflowDefinition($"workflow-{protocol.Digest.Value[..12]}", nodes);
    }
}
