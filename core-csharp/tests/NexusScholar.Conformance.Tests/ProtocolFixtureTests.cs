using System.Text.Json;
using Microsoft.VisualStudio.TestTools.UnitTesting;

namespace NexusScholar.Conformance.Tests;

[TestClass]
public sealed class ProtocolFixtureTests
{
    [TestMethod]
    public void Minimal_protocol_fixture_has_required_contract_fields()
    {
        var path = Path.Combine(AppContext.BaseDirectory, "fixtures", "protocol-minimal.json");
        using var document = JsonDocument.Parse(File.ReadAllText(path));
        var root = document.RootElement;

        Assert.AreEqual("nexus.review-protocol/v1", root.GetProperty("schema").GetString());
        Assert.IsTrue(root.GetProperty("subject").GetString()?.Length > 0);
        Assert.AreEqual(2, root.GetProperty("required_decisions").GetArrayLength());
        Assert.AreEqual("scoping-review", root.GetProperty("decisions").GetProperty("review-type").GetString());
    }
}
