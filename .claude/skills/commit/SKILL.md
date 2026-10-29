---
name: commit
description: Stage and commit current theme changes with a Conventional Commit message (cheap, Haiku).
argument-hint: "[scope or hint]"
disable-model-invocation: true
model: haiku
effort: low
allowed-tools: Bash(git status:*) Bash(git diff:*) Bash(git add:*) Bash(git commit:*) Bash(git log:*)
---

Create one commit. Hint: $ARGUMENTS

!`git status --short`

!`git diff --stat HEAD`

!`git log --oneline -5`

1. Mixed unrelated changes → list groups and ask which to commit.
2. `git add` only relevant files (never `vendor/` changes unless intended, never `composer.phar`).
3. Message `type(scope): imperative summary` (feat|fix|refactor|perf|chore|docs), ≤72 chars, English,
   matching existing style (e.g. `feat(media): …`). Body = why, if non-obvious.
4. Do not push. Print `git log --oneline -1`.
