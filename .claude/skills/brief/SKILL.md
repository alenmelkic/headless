---
name: brief
description: Execute a structured markdown brief (from Claude chat) task by task with file allowlists, VERIFY markers and stop gates.
argument-hint: "<path-to-brief.md> [task-id]"
disable-model-invocation: true
model: sonnet
effort: medium
---

Execute the brief at: $ARGUMENTS

1. Read the brief once. Extract tasks/phases, file allowlist, VERIFY commands, stop gates.
   Print a ≤10-line plan. If a task-id was given, do only that task.
2. Never modify files outside the allowlist — if needed, STOP and explain.
3. "Diagnose" phases produce findings only. Stop after each phase/gate for approval.
4. Per task: smallest change → its VERIFY commands (or `/verify`) → PASS/FAIL.
   On FAIL: max two in-scope fix attempts, then stop and report.
5. Save context with subagents: `explorer` (locate), `wp-probe` (endpoint shapes).
6. Production commands (SSH, WP-CLI on prod) are never run — write them as `wp eval-file`
   scripts in the summary for the human to run.
7. Final summary for Claude chat (≤30 lines): per task status/files/verify, deviations,
   frontend follow-ups for rvkweb, open questions.
