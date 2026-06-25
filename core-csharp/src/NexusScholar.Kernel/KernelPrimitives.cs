namespace NexusScholar.Kernel;

public interface IClock
{
    DateTimeOffset UtcNow { get; }
}
