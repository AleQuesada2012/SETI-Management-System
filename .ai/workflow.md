# Working an issue

The steps any agent (Claude Code, Codex, Cursor, Copilot, a chat assistant) follows to take one GitHub issue from
"assigned" to "pull request ready for review". A person runs the agent and remains the author of the result.
Each step ends on its **done when**; finish it before starting the next.

## 1. Understand the issue

Read the issue (`gh issue view <number>`, or the page on GitHub) and its requirement in
`docs/requirements/<module>.md#rf_xx`. Note:

- the acceptance criteria (Spanish, as approved): each one becomes a test or a visible behaviour;
- owner, reviewer and sprint (the issue's table);
- **notes**: RFs that close inside this PR ("closes together with"), shared infrastructure this RF must build or
  reuse, the cut-order rank;
- the Lucid diagram, when there is one: it shows the intended flow and the fields.

**Done when** you can list every acceptance criterion and every issue this PR will close.

## 2. Load the rules

Open `.ai/rules/index.md`, read `all.md` and every rule file whose globs match the paths you will touch. Look up the
domain words in `CONTEXT.md` and, for each table you touch, its section of `docs/Agent-DB.md`. If the work touches a
decision in `docs/decisions.md`, follow it; changing it is a person's call (step 3).

**Done when** you have read every matching rule file and can name the tables and the glossary terms involved.

## 3. Plan, and stop for anything that belongs to a person

Write the plan before code: the page or component, the `app/Actions` class, the Query, the Policy ability, the route,
the `lang/es` strings, the migration if any, and one test per acceptance criterion. Mark files that belong to another
module: their owner reviews the PR.

Stop and ask the person running you before any of these:

- adding a Composer or npm package (Chart.js is already approved, for RF_80);
- editing a migration that is already on `main`, or changing an existing table's meaning;
- changing a decision in `docs/decisions.md`, a policy's rules, or the role → permission matrix;
- changing files of another module beyond the seam its rule file describes;
- pushing to `main`, merging, approving, or closing issues.

**Done when** the plan covers every criterion, and anything on the list above has an answer from a person.

## 4. Branch

```bash
git switch main && git pull
git switch -c <type>/<issue-number>-<short-slug>     # e.g. feat/12-register-asset
```

**Done when** you are on a new branch from an up-to-date `main`.

## 5. Implement

Follow the module's rule file. The conventions every change keeps:

- an operation is one `app/Actions/<Module>` class: authorize, validate, one `DB::transaction()` with its audit
  entries, notifications after commit;
- every Livewire action calls `$this->authorize()`; every route has `can:`;
- UI text is Spanish in `lang/es/<module>.php`; colors are palette tokens (`.ai/rules/frontend.md`);
- the page works at 360 px wide.

**Done when** every criterion is implemented, including those of the RFs that close with this one.

## 6. Test

Write Pest tests through the public surface (routes, `Livewire::test`, Actions). Sprint 1: a happy path and an
unauthorized test per requirement. From Sprint 2 also validation errors, the audit entry and each database rule the
requirement relies on (`CONTRIBUTING.md` § Definition of Done).

```bash
docker compose exec app php artisan test --compact --filter=<YourTest>
docker compose exec app composer test          # Pint + Larastan + the whole suite
```

**Done when** `composer test` is green and each acceptance criterion maps to a passing test or to a manual check you
performed as the right demo user (README).

## 7. Commit and open the pull request

- Commits: English, imperative ("add asset form"). Write no AI co-author trailer (`Co-Authored-By: <AI>`): the team
  has not decided whether AI tools are credited (`docs/decisions.md`).
- Push the branch and open the PR with `gh pr create` or on GitHub:
  - title `type(module): summary (RF_xx)` (`CONTRIBUTING.md`; CI checks it);
  - the PR template filled in, with `Closes #<issue>` for this issue and every issue that closes with it;
  - the AI-assistance section: tool(s) and what they did;
  - a manual note for `docs/manuales/`: a few lines and a screenshot of the new screen (from Sprint 2).
- Request the reviewer named in the issue.

**Done when** the PR is open, CI is green, and the reviewer is requested.

## 8. Review

Answer every comment with a change or a reason; each new push dismisses earlier approvals. The person who owns the
issue merges (squash) once it is approved, green and every conversation is resolved.

---

**Starting an agent on an issue** (any harness that reads the repository):

> Work on GitHub issue #<number>. Follow `AGENTS.md` and the steps in `.ai/workflow.md`. Stop at step 3 and show me
> the plan before writing code.

**Chat assistants without repository access** (ChatGPT, Claude.ai, Gemini web): generate a context pack and paste or
upload it with the same prompt:

```bash
scripts/ai/context-pack.sh RF_03 > rf03-context.md
```
