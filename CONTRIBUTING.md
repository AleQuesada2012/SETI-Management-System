# Contributing

How the five of us (and our AI assistants) change this repository. If something here is unclear or missing, open a
`type:docs` issue; do not guess.

- Installation and running the app: [README.md](README.md) (Spanish).
- Rules for code in each module: [`.ai/rules/`](.ai/rules/index.md). They apply to people and agents alike.
- Domain words: [CONTEXT.md](CONTEXT.md). Data model and its reasons: [docs/Agent-DB.md](docs/Agent-DB.md).
- Requirements (owner, reviewer, sprint of each RF): [docs/requirements/](docs/requirements/README.md). Sprint plan
  and cut order: [docs/PLANNING.md](docs/PLANNING.md).
- Why things are the way they are: [docs/decisions.md](docs/decisions.md); architecture records in [docs/adr/](docs/adr).

## Team and ownership

| Module (label) | Owner | Buddy reviewer | Requirements |
|---|---|---|---|
| `module:security` (sign-in, audit) | Alejandro Quesada · @AleQuesada2012 | David · @DavidZamoraB | RF_00_1–RF_00_4 |
| `module:assets` | David Zamora · @DavidZamoraB | Diego · @Dizabu | RF_01–RF_31 |
| `module:spaces` | Diego Zamora · @Dizabu | Jose · @JoseHerrera21 | RF_32–RF_45 |
| `module:reservations` | Jose Herrera · @JoseHerrera21 | Sebastián · @Rubiocai | RF_46–RF_59 |
| `module:reports` | Alejandro Quesada · @AleQuesada2012 | David · @DavidZamoraB | RF_60–RF_84 |
| `module:users` | Sebastián Sánchez · @Rubiocai | Alejandro · @AleQuesada2012 | RF_85–RF_101 |
| `module:platform` (+ `db`, `docs`, `ci`) | @AleQuesada2012, @Rubiocai | the other platform owner | — |

- **Change your own module.** To change another module's files, ask its owner in the issue or PR; they review it.
- **Planned exceptions** (planning round 3): Jose implements maintenance (RF_10, 11, 26, 27) in the assets module and
  the reservations/occupancy reports (RF_65, 79); Diego implements asset documents and photos (RF_20, 21) and the
  single-space report (RF_74); Sebastián implements lab manager assignment (RF_40, 43) in the spaces module. The
  module owner reviews those PRs; each issue names its reviewer.
- **Shared pages** have one owner and named slots for others (see `.ai/rules/spaces.md` and `reservations.md`):
  the spaces list belongs to Jose (Diego adds search and "Añadir espacio"); the space page belongs to Diego (it embeds
  Jose's reservation components); the reservations calendar (RF_45) is Diego's; creating and viewing a reservation
  (RF_46, RF_51) are Jose's.
- **Platform files** (bootstrap, config, Docker, CI, shared layout, `app/Support`) need a platform owner's review;
  `.github/CODEOWNERS` requests it automatically.

## Language

| What | Language |
|---|---|
| Code, comments, commit messages, branch names, PRs, issues, `AGENTS.md`, this file, `.ai/rules` | English |
| Everything the user reads (UI strings in `lang/es`, validation and error messages), `README.md`, user manuals (`docs/manuales/`) | Spanish |
| Requirement text (RF titles and criteria) | Quoted in Spanish, as approved |

Use the glossary's words: the English term in code (`Asset`, `SpaceManager`, `decommission`), the Spanish term in the
UI (Activo, Encargado del espacio, Baja).

## Workflow (GitHub Flow)

`main` always works and is protected: nobody pushes to it directly. Every change goes through an issue, a branch and
a pull request, and is **squash-merged**.

1. **Issue.** Take an issue assigned to you (or open one: GitHub Issues is our only tracker). Each RF already has its
   issue. Move it to *In progress* on the Project board.
2. **Branch** from an up-to-date `main`: `type/<issue-number>-<short-slug>`, lowercase, hyphens.

   ```bash
   git switch main && git pull
   git switch -c feat/12-register-asset
   ```

   Types: `feat` (new behaviour), `fix` (bug), `refactor`, `test`, `docs`, `chore` (tooling, deps), `ci`, `perf`.
3. **Commit** as often as you like; your branch's commits are squashed. Write them in English and in the imperative
   ("add asset form", not "added"). Keep `main` merged into long-lived branches: `git merge main`, not rebase, once
   the PR is open.
4. **Check locally**: `docker compose exec app composer test` (Pint, Larastan, Pest) must be green.
5. **Pull request.** Open it early as a draft if you want feedback. Fill in the template, including the AI-use section.
   The body contains `Closes #<issue>`.
6. **Review and merge.** The buddy reviewer (table above) is requested; CODEOWNERS adds a platform owner when needed.
   When checks are green, the review is approved and every conversation is resolved, **the author** clicks
   *Squash and merge* and deletes the branch.

### Pull request title = the commit on `main`

Conventional Commits, checked by CI:

```
type(module): summary in the imperative (RF_xx)
```

- `type`: one of the branch types above.
- `module`: `security`, `assets`, `spaces`, `reservations`, `reports`, `users`, `platform`, `db`, `docs`, `ci`.
- `summary`: lowercase start, no final period, the whole title ≤ 72 characters.
- `(RF_xx)`: the requirement(s) the PR delivers or advances; several as `(RF_07, RF_08)`. Required for `feat`; add
  it to other types when they relate to a requirement.

Examples: `feat(assets): register an asset in a space (RF_03)` ·
`fix(reservations): accept back-to-back reservations (RF_46)` · `docs(platform): explain the export pipeline`

### Merge rules (branch protection on `main`)

- 1 approving review, from someone other than the author.
- All CI checks green: `ci` (Pint, Larastan, Pest on PostgreSQL, schema smoke test, asset build, `composer audit`,
  gitleaks) and `pr-title`.
- All review conversations resolved.
- A new push dismisses earlier approvals: the reviewer looks again.
- Squash merge only; the branch is deleted after merging.

### Reviewing

Answer review requests within one day (within a few hours in the last two days of a sprint). Check that the PR:

- does what its issue asks, and every acceptance criterion is covered or explicitly deferred;
- follows the module's `.ai/rules` file (authorization in every action, one transaction per operation, audit, Spanish
  strings in `lang/es`, the glossary's words);
- has the tests the Definition of Done asks for, and they would fail without the change;
- works at phone width (try it at 360 px in the browser's device mode).

Approve, or *Request changes* with concrete comments. Small suggestions go in GitHub's suggestion blocks.

## Definition of Done

**Sprint 1 (until 2026-09-30):** the requirement works in the app, in Spanish, at phone width; its page or action is
authorized (route `can:` + `$this->authorize()` in Livewire actions); it has a happy-path test and an unauthorized
test; `composer test` is green; the PR is merged and the issue closed.

**From Sprint 2 on,** also:

- a test for each validation error the requirement mentions;
- a test that the operation writes its audit entry;
- a test for each database rule it relies on (e.g. an overlapping reservation is rejected with its Spanish message);
- the user manual (`docs/manuales/`) describes the new screen or action.

## Database changes

- Every schema change is a **new migration**; a migration that has reached `main` is never edited (ADR-0013).
- In the same PR, keep in sync: the PHP enum for any code-driven set, the Spanish message for any new constraint
  (`lang/es/db.php`), a check in `docs/db/smoke-test.sql` for any new rule, and `docs/Agent-DB.md`.
- Hard-to-reverse decisions get an ADR in `docs/adr/` (copy the format of the existing ones).

## Dependencies

Adding a Composer or npm package is a team decision: propose it in the issue with the reason and the alternative you
considered, and get a platform owner's approval before the PR. Dependabot keeps existing packages current; its PRs
follow the same merge rules.

## AI assistants

We use AI agents (Claude Code, Codex, ChatGPT and others). They read `AGENTS.md`, the same rules as people, and work
an issue with the steps in [`.ai/workflow.md`](.ai/workflow.md). To start one:

> Work on GitHub issue #12. Follow `AGENTS.md` and `.ai/workflow.md`. Stop at step 3 and show me the plan first.

- **Agents that read the repository** (Claude Code, Codex, Cursor, Copilot…) pick up `AGENTS.md` by themselves
  (Copilot through `.github/copilot-instructions.md`). Laravel Boost's skills are committed for Claude Code
  (`.claude/skills`) and Codex (`.agents/skills`).
- **Chat assistants without repository access** (ChatGPT, Claude.ai web): generate a context pack with the rules and
  the requirement, and upload it: `scripts/ai/context-pack.sh RF_03 > rf03-context.md`.

- **You are the author** of every line you push, whoever typed it: read it, run it, and be able to explain it in review.
- **Agents do not**: push to `main`, merge or approve PRs, add dependencies, or edit merged migrations. Those are
  decisions for a person; an agent may propose them.
- **Declare AI use** in the PR template: which tool, and for what (e.g. "Claude Code wrote the Livewire page and the
  tests; I rewrote the validation").
- **No AI co-author trailers** (`Co-Authored-By: Claude…`, "Generated with…") in commits or PR descriptions until the
  team decides otherwise. Turn the option off in your tool if it adds them by default.
- **Recording a new rule**: when the team settles a convention, add it to the matching `.ai/rules/<area>.md` in a PR
  (or ask your agent to use Boost's `record-rule` tool, which also updates `.ai/rules/index.md`).
- Laravel Boost gives agents project-aware tools (routes, schema, docs search). `.mcp.json` starts it inside Docker
  for Claude Code; for Codex add the same command to `~/.codex/config.toml`:

  ```toml
  [mcp_servers.laravel-boost]
  command = "docker"
  args = ["compose", "exec", "-T", "app", "php", "artisan", "boost:mcp"]
  ```

## Sprints and releases

- Sprint 1 ends Wed 2026-09-30 18:30 · Sprint 2 Wed 2026-10-14 18:30 · Sprint 3 Wed 2026-10-28 18:30.
- Each sprint is a GitHub milestone; each issue carries a `module:` and a `type:` label.
- At each delivery the project manager tags `main` as `sprint-1`, `sprint-2`, `sprint-3`.

## Security

Never commit secrets: `.env` is ignored, and gitleaks blocks PRs that contain keys or passwords. Report
vulnerabilities privately as described in [SECURITY.md](SECURITY.md).
