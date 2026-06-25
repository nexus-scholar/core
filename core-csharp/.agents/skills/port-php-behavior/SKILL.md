---
name: port-php-behavior
description: Port one proven PHP behavior to C# through evidence, golden fixtures, semantic comparison, and explicit compatibility decisions.
---

1. Confirm the PHP commit in `specs/SOURCE.lock.json`.
2. Use `php_behavior_explorer` to trace entry points, invariants, persistence, failures, tests, and framework-specific details.
3. Write a behavior report before C# code.
4. Identify or generate minimal golden inputs and outputs.
5. Define semantic comparison rules, ignoring only documented serialization differences.
6. Implement the smallest framework-light C# behavior.
7. Add differential and negative tests.
8. Classify every difference as equivalent, intentional change, PHP defect, C# defect, or unresolved conflict.

Never translate classes mechanically and never edit golden outputs to satisfy the port.
