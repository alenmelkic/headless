---
name: diagnose
description: Evidence-first root-cause analysis of a WordPress-side bug on Opus in an isolated subagent (no edits) — REST/GraphQL, revalidation, ACF, cron, settings, preview, caching.
argument-hint: "<symptom / error / endpoint>"
context: fork
agent: diagnoser
model: opus
effort: high
---

Diagnose this issue in the headless theme (and rvkweb if the symptom crosses repos). Do NOT modify files.

Issue: $ARGUMENTS

Context:
!`git branch --show-current && git status --short | head -20 && git log --oneline -5`

Phase 1 only: evidence → labeled buckets → ordered fix plan with VERIFY per step. Stop there.
