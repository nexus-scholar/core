# Codex Task: Review This Branch

Do not edit files during the first pass.

Compare this branch with its base and run independent reviews using:

- `scientific_invariant_reviewer`
- `conformance_auditor`
- `dotnet_architect`
- `test_engineer`
- `plugin_security_reviewer` when extensibility changes
- `llm_governance_reviewer` when AI changes

Consolidate blocking, important, and minor findings. Include file and symbol, failing scenario, missing test, specification conflict, and required correction. Finish with a safe-to-merge verdict and the verification commands that were actually run.
