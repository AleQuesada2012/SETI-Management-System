# Decision log

Every project-level decision the team made, why, and where it is enforced. Architecture decisions that are hard to
reverse have a full record in [adr/](adr); this log is the index of all decisions, including process and planning ones.
Before proposing to change something listed here, read its reason; a change is a new, dated entry (never an edit of
an old one) agreed by the PM.

Sessions: **G1** = grilling rounds 1–2 (2026-09-28, stack, process, data model; the data model's own 34 decisions are
in [Agent-DB.md §20](Agent-DB.md)). **G3** = planning round 3 (2026-09-29). **B** = made while building the bootstrap
(2026-09-29) and reviewed by the PM before the first push.

## Scope and schedule

| # | Decision | Why | Enforced in |
|---|---|---|---|
| G1 | Sprint deliveries: Wed 2026-09-30, 2026-10-14, 2026-10-28, 18:30. Laptop demos until AWS in Sprint 3. | Course calendar. | [PLANNING.md](PLANNING.md), milestones |
| G1 | Bootstrap = walking skeleton + RF_00_1–RF_00_4 + RF_89 ("option C"). | Everyone builds on working auth, audit, permissions and export instead of inventing them five times. | Closed issues, [PLANNING.md](PLANNING.md) |
| G3 | Sprint 1 leftovers carry into the next sprint as its first priority. | Nobody could start before the repository existed (2026-09-29). | `scripts/github/carry-over.sh` |
| G3 | Code across both weeks of a sprint; plan about 8 RFs per person per sprint. | Remaining work ≈ 95 RFs for five people. | [PLANNING.md](PLANNING.md) |
| G3 | RFs may be reassigned by module fit; every move is recorded. | David had 31 open RFs, Jose 12. | [PLANNING.md § Reassignments](PLANNING.md#reassignments-planning-round-3-2026-09-29) |
| G3 | Rebalance: RF_20/21 → Diego, RF_10/11/26/27 → Jose, RF_40/43 → Sebastián, RF_65/79 → Jose, RF_74 → Diego. | One mechanism per person (uploads, scheduling, manager assignment); balanced load. | `requirements.json`, issues |
| G3 | Sprint 2 = write side, Sprint 3 = read side; exceptions: RF_40/43 in Sprint 2, maintenance in Sprint 3. | Dependencies first; the ★ rule needs manager assignment; capacity. | [PLANNING.md](PLANNING.md) |
| G3 | Cut order if late: RF_99, RF_79, RF_83, RF_37/38, RF_73 (manual refresh). | Decide calmly now what to drop, not on the last day. | [PLANNING.md § Cut order](PLANNING.md#cut-order) |
| G3 | The PM's RF_60/63/64 stay his and open Sprint 2. | RF_63 is the example every other report copies. | issues |
| G3 | Shared infrastructure is built by the first RF that needs it, reviewed by a platform owner. | No speculative infrastructure; one owner per mechanism. | [PLANNING.md § Shared infrastructure](PLANNING.md#shared-infrastructure) |

## Architecture and stack

| # | Decision | Why | Enforced in |
|---|---|---|---|
| G1 | Laravel 13, Livewire 4 single-file components, Flux (free), Tailwind 4, Fortify, Pest 5, PHP 8.5, PostgreSQL 18. | One language for the team; the official starter kit. | [ADR-0011](adr/0011-livewire-single-file-components-with-flux-for-the-ui.md) |
| G1 | Docker Compose on serversideup/php (dev and prod targets); DB on host port 5433. | Same environment on three operating systems and in production. | [ADR-0012](adr/0012-docker-compose-on-serversideup-php-instead-of-sail.md) |
| G1 | Production: one AWS VM, PostgreSQL as a container, files on the VM disk (no MinIO). | Course budget; simplest thing that works. | [ADR-0007](adr/0007-files-live-outside-the-database-and-are-linked-with-foreign-keys.md), `compose.prod.yaml` |
| G1 | Migrations are the schema's source of truth; `schema.sql`/`seed.sql` frozen; `smoke-test.sql` runs in CI. | Incremental history; the 111 original rules keep being checked. | [ADR-0013](adr/0013-laravel-migrations-are-the-source-of-truth-for-the-schema.md) |
| G1 | Code layout: `app/Actions/<Module>`, `app/Queries/<Module>`, Policies, thin pages in `resources/views/pages/<module>`, `routes/modules/<module>.php`, `lang/es/<module>.php`. | Five people work in parallel with few conflicts. | [.ai/rules/all.md](../.ai/rules/all.md) |
| G1 | Libraries: dompdf (PDF), spatie/simple-excel (XLSX), spatie/laravel-permission (roles). | Mature, small, documented. | `composer.json` |
| G1 | Security: optional 2FA, no passkeys, passwords ≥ 12 (+ breach check in production), 120-minute sessions, no self-registration, invitations. | Institutional data; accounts are created by the Superusuario. | [SECURITY.md](../SECURITY.md) |
| G3 | In-app charts use Chart.js 4 through one `<x-chart>` component. | Flux free has no charts; smallest documented option. | RF_80 issue |
| G3 | Power BI: secure embed for a few licensed users, new permission `dashboards.powerbi`; connectivity test first in Sprint 3; `.pbix` by Alejandro (backup Sebastián). | The client provides licensed accounts; refresh needs a network path or a gateway. | [PLANNING.md § Power BI](PLANNING.md#power-bi-rf_61-rf_73) |
| G3 | Appearance follows the user's system setting, with explicit light and dark palettes in `resources/css/theme.css`; no color inversion. | Palettes must be definable and modifiable over time. | [.ai/rules/frontend.md](../.ai/rules/frontend.md) |
| B | The ★ rule applies to every holder of a space-scoped permission except the Superusuario (`User::canInSpace`). | Custom roles cannot escape space scoping by accident. | `app/Policies`, `SpaceScopeTest` |
| B | Lists and their reports share one Query class per module over `reporting.*` views. | A filter means the same on screen and in the exported file. | `app/Queries`, [.ai/rules/reports.md](../.ai/rules/reports.md) |
| B | Spanish URLs, English route names; `/inicio` sends each role to its first module. | Users read URLs; code reads names. | `routes/` |
| B | Every authenticated route has a `can:` check unless it only touches the user's own account; a test enforces it. | RF_89 cannot regress silently. | `RouteAuthorizationTest` |

## Process

| # | Decision | Why | Enforced in |
|---|---|---|---|
| G1 | Private repo on AleQuesada2012 (`SETI-Management-System`); GitHub Issues is the only tracker; milestones per sprint; labels `module:` and `type:`. | One place to look. | `scripts/github/bootstrap.sh` |
| G1 | GitHub Flow, squash merge only; PR title `type(module): summary (RF_xx)`; branches `type/<issue#>-<slug>`; tags `sprint-N`. | Readable history, one commit per change. | [CONTRIBUTING.md](../CONTRIBUTING.md), `pr-title.yml` |
| G1 | `main` protected: 1 approval, CI green, conversations resolved, stale approvals dismissed; CODEOWNERS only on platform paths. | Review without bottlenecks. | branch protection, `.github/CODEOWNERS` |
| G1 | Platform owners: @AleQuesada2012, @Rubiocai. Buddy reviewers: assets ← Diego, spaces ← Jose, reservations ← Sebastián, users ← Alejandro, security/reports ← David. | Everyone reviews a neighbour's module. | [CONTRIBUTING.md](../CONTRIBUTING.md) |
| G3 | Work in another person's module is reviewed by that module's owner; the issue keeps the module label of the code. | The owner knows the invariants of their module. | `requirements.json` reviewer field |
| G1 | English for code, commits, PRs, issues and agent docs; Spanish for README, manuals and UI; RF text quoted in Spanish. | Agents and tooling work in English; users read Spanish. | [CONTRIBUTING.md § Language](../CONTRIBUTING.md#language) |
| G1 | Definition of Done: happy path + unauthorized tests in Sprint 1; from Sprint 2 also validation, audit and database-rule tests. | Quality grows with the codebase. | [CONTRIBUTING.md](../CONTRIBUTING.md#definition-of-done) |
| G3 | User manuals: a few lines and a screenshot per PR, polished at the end of Sprint 3. | Facts are captured while fresh at a low cost per PR. | [CONTRIBUTING.md](../CONTRIBUTING.md#definition-of-done) |
| G1 | AI agents: one committed `AGENTS.md` + `.ai/rules`; Laravel Boost; agents never push to `main`, merge, approve, add dependencies or edit merged migrations; AI use is declared in the PR. | The rules must hold whoever (or whatever) writes the code. | [AGENTS.md](../AGENTS.md), PR template |
| G3 | No AI co-author trailers (`Co-Authored-By: <AI>`) in commits until the team decides otherwise. | Pending team decision on crediting AI tools. | [AGENTS.md](../AGENTS.md) |

## Open

- Whether AI tools may appear as commit co-authors (team decision pending).
- The outcome of the Power BI connectivity test (start of Sprint 3).
