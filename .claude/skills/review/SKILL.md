---
name: review
description: Security + WP-standards + API-contract review of the current theme diff on Sonnet in an isolated subagent. Use before committing.
argument-hint: "[git-range | files]"
context: fork
agent: wp-reviewer
model: sonnet
effort: medium
---

Review these changes in the headless theme. Scope: $ARGUMENTS (empty = all uncommitted changes).

!`git status --short`

!`git diff --stat HEAD`

Read hunks with `git diff HEAD -- <file>`. Report in the severity format.
