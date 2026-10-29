---
name: verify
description: Verify theme changes (php -l, JSON validity, block.json sanity, REST/GraphQL smoke tests) in a cheap Haiku subagent; compact pass/fail report. Use after every change.
argument-hint: "[endpoint or graphql field to smoke-test]"
context: fork
agent: php-verifier
model: haiku
effort: low
---

Verify the current changes in the headless theme. Extra targets: $ARGUMENTS

Changed files:
!`git status --short`

Run all php-verifier steps and report in its format.
