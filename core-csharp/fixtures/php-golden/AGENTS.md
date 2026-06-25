# Golden Fixture Instructions

- Never edit generated outputs by hand.
- Generate fixtures only from the commit pinned in `specs/SOURCE.lock.json`.
- Store source commit, generator command, input digest, output digest, and generator version.
- A C# test failure is not permission to replace a fixture.
- Classify differences as equivalent serialization, intentional change, PHP defect, C# defect, or unresolved specification conflict.
