---
name: design-plugin-capability
description: Design a capability-scoped Nexus extension contract, invocation boundary, staged output, and security review before runtime implementation.
---

Define the extension purpose, trusted or untrusted status, requested capabilities, exact input handles, allowed outputs, validation schema, external communication policy, credential-reference policy, time and resource limits, audit events, failure behavior, and revocation behavior.

Use `plugin_security_reviewer` before implementation. Third-party code defaults to an out-of-process boundary. Never expose database credentials or unrestricted workspace access.

Create contract tests for excess capability requests, invalid output, timeout, cancellation, and attempted access outside the declared scope.
