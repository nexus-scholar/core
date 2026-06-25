---
name: generate-golden-fixture
description: Generate auditable PHP reference fixtures with source commit, command, input and output digests, and reproducible metadata.
---

Use only the pinned PHP checkout. Refuse to run when the checkout commit differs from `specs/SOURCE.lock.json`.

For each fixture save:

- immutable input;
- normalized expected output;
- source repository and commit;
- exact command and environment assumptions;
- generator version;
- input and output SHA-256 digests;
- notes about intentionally ignored nondeterministic fields.

Do not hand-edit generated expected outputs. Validate that regeneration produces the same semantic result before accepting the fixture.
