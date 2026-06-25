# Gate 1: Repository Quality

Exit checks:

- .NET 10 restore succeeds.
- Release build succeeds with warnings treated as errors.
- Unit, architecture, and conformance tests pass.
- Formatting verification passes.
- CI runs on Linux and Windows.
- No live provider or model call occurs in tests.
- Domain projects have no host-framework or provider-SDK dependency.
