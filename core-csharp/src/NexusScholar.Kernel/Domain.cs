namespace NexusScholar.Kernel;

public readonly record struct ActorId
{
    private ActorId(string value)
    {
        Value = value;
    }

    public string Value { get; }

    public static ActorId From(string value) => new(Guard.NotBlank(value, nameof(value)));

    public override string ToString() => Value;
}

public class DomainRuleException : InvalidOperationException
{
    public DomainRuleException(string message)
        : base(message)
    {
    }
}

public static class Guard
{
    public static string NotBlank(string? value, string parameterName)
    {
        if (string.IsNullOrWhiteSpace(value))
        {
            throw new ArgumentException("Value must not be blank.", parameterName);
        }

        return value.Trim();
    }
}
