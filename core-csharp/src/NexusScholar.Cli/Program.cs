using NexusScholar.Bundles;
using NexusScholar.Kernel;
using NexusScholar.Protocol;
using NexusScholar.Provenance;
using NexusScholar.Workflow;

var command = args.FirstOrDefault()?.ToLowerInvariant() ?? "doctor";

return command switch
{
    "doctor" => RunDoctor(),
    "sample" => RunSample(),
    _ => ShowHelp()
};

static int RunDoctor()
{
    Console.WriteLine("Nexus Scholar Core doctor");
    Console.WriteLine($"Framework: {System.Runtime.InteropServices.RuntimeInformation.FrameworkDescription}");
    Console.WriteLine($"OS: {System.Runtime.InteropServices.RuntimeInformation.OSDescription}");
    Console.WriteLine("Policy: model outputs are proposals; approved protocols are immutable.");
    return 0;
}

static int RunSample()
{
    var ids = new GuidV7IdGenerator();
    var clock = new SystemClock();
    var researcher = ActorId.From("local-researcher");

    var draft = ProtocolDraft.Create(ids, "Starter review", new[] { "review-type", "scope" });
    draft.RecordDecision("review-type", "scoping-review", researcher, clock);
    draft.RecordDecision("scope", "agricultural image segmentation", researcher, clock);
    var version = draft.Approve(researcher, clock, ids);
    var workflow = new WorkflowCompiler().Compile(version);

    var provenance = new InMemoryProvenanceStore();
    provenance.Append(ResearchEventFactory.Create(
        ids,
        clock,
        "protocol-approved",
        "protocol-version",
        version.Id.ToString(),
        researcher,
        outputs: new[] { version.Digest }));

    var manifest = new ReviewBundleManifest(
        "nexus.review-bundle/v1",
        "sample-project",
        version.Digest,
        workflow.Id,
        clock.UtcNow,
        Array.Empty<BundleArtifact>());
    var verification = new BundleVerifier().Verify(manifest);

    Console.WriteLine($"Protocol digest: {version.Digest}");
    Console.WriteLine($"Workflow: {workflow.Id} ({workflow.Nodes.Count} nodes)");
    Console.WriteLine($"Provenance events: {provenance.ReadAll().Count}");
    Console.WriteLine($"Bundle valid: {verification.IsValid}");
    return verification.IsValid ? 0 : 1;
}

static int ShowHelp()
{
    Console.Error.WriteLine("Usage: dotnet run --project src/NexusScholar.Cli -- [doctor|sample]");
    return 2;
}
