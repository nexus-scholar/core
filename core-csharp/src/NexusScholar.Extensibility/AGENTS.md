# Extensibility Instructions

Assume third-party extensions are untrusted. Dependency isolation is not a security boundary. Extensions declare capabilities and receive only an approved subset. They do not receive database credentials, unrestricted file access, or raw secrets. Validate staged outputs before they enter canonical state. Prefer an out-of-process host for third-party code.
