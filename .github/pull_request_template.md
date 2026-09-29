<!--
Title: type(module): summary in the imperative (RF_xx)   e.g. feat(assets): register an asset in a space (RF_03)
Rules: CONTRIBUTING.md. Keep the PR to one issue.
-->

Closes #

## What changes

<!-- What the user can do now, or what behaves differently. Screenshots (phone and desktop) for UI changes. -->

## How to try it

<!-- Steps for the reviewer: which user to sign in as (demo users in README), which page, what to do. -->

## Checklist

- [ ] Every acceptance criterion of the issue is covered, or listed below as deferred.
- [ ] Routes have `can:` and every Livewire action calls `$this->authorize()`.
- [ ] Tests: happy path and unauthorized (from Sprint 2 also validation, audit entry and database rules).
- [ ] `docker compose exec app composer test` passes locally.
- [ ] UI text is Spanish, in `lang/es/<module>.php`, and the page works at 360 px wide.
- [ ] Schema changes are a new migration, with `docs/Agent-DB.md`, `lang/es/db.php` and `smoke-test.sql` updated.
- [ ] No new dependency, or it was approved in the issue by a platform owner.

## AI assistance

- [ ] No AI tool was used.
- [ ] AI tools were used, and I have read, run and understood every line:
  - Tool(s): <!-- Claude Code, Codex, ChatGPT… -->
  - What they did: <!-- e.g. "generated the Livewire page and its tests; I rewrote the validation" -->

## Deferred or follow-up

<!-- Anything left for another issue, with its number. -->
