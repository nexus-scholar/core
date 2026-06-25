namespace NexusScholar.Kernel;

public interface IClock
{
    DateTimeOffset UtcNow { get; }
}

public sealed class SystemClock : IClock
{
    public DateTimeOffset UtcNow => DateTimeOffset.UtcNow;
}

public interface IIdGenerator
{
    Guid NewId();
}

public sealed class GuidV7IdGenerator : IIdGenerator
{
    public Guid NewId() => Guid.CreateVersion7();
}

public readonly record struct EntityId<TTag>(Guid Value)
    where TTag : class
{
    public static EntityId<TTag> New(IIdGenerator generator)
    {
        ArgumentNullException.ThrowIfNull(generator);
        return new EntityId<TTag>(generator.NewId());
    }

    public override string ToString() => Value.ToString("D");
}
