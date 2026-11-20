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
| G1 | Public repo on AleQuesada2012 (`SETI-Management-System`); GitHub Issues is the only tracker; milestones per sprint; labels `module:` and `type:`. | One place to look. | `scripts/github/bootstrap.sh` |
| G1 | GitHub Flow, squash merge only; PR title `type(module): summary (RF_xx)`; branches `type/<issue#>-<slug>`; tags `sprint-N`. | Readable history, one commit per change. | [CONTRIBUTING.md](../CONTRIBUTING.md), `pr-title.yml` |
| G1 | `main` protected: 1 approval, CI green, conversations resolved, stale approvals dismissed; CODEOWNERS only on platform paths. | Review without bottlenecks. | branch protection, `.github/CODEOWNERS` |
| G1 | Platform owners: @AleQuesada2012, @Rubiocai. Buddy reviewers: assets ← Diego, spaces ← Jose, reservations ← Sebastián, users ← Alejandro, security/reports ← David. | Everyone reviews a neighbour's module. | [CONTRIBUTING.md](../CONTRIBUTING.md) |
| G3 | Work in another person's module is reviewed by that module's owner; the issue keeps the module label of the code. | The owner knows the invariants of their module. | `requirements.json` reviewer field |
| G1 | English for code, commits, PRs, issues and agent docs; Spanish for README, manuals and UI; RF text quoted in Spanish. | Agents and tooling work in English; users read Spanish. | [CONTRIBUTING.md § Language](../CONTRIBUTING.md#language) |
| G1 | Definition of Done: happy path + unauthorized tests in Sprint 1; from Sprint 2 also validation, audit and database-rule tests. | Quality grows with the codebase. | [CONTRIBUTING.md](../CONTRIBUTING.md#definition-of-done) |
| G3 | User manuals: a few lines and a screenshot per PR, polished at the end of Sprint 3. | Facts are captured while fresh at a low cost per PR. | [CONTRIBUTING.md](../CONTRIBUTING.md#definition-of-done) |
| G1 | AI agents: one committed `AGENTS.md` + `.ai/rules`; Laravel Boost; agents never push to `main`, merge, approve, add dependencies or edit merged migrations; AI use is declared in the PR. | The rules must hold whoever (or whatever) writes the code. | [AGENTS.md](../AGENTS.md), PR template |
| G3 | No AI co-author trailers (`Co-Authored-By: <AI>`) in commits until the team decides otherwise. | Pending team decision on crediting AI tools. | [AGENTS.md](../AGENTS.md) |

## Reports contract agreement (2026-09-29)

Approved by Alejandro (@AleQuesada2012), acting as PM and architect, after the filter-contract interview. These
entries record the agreed target; Query filtering and report integration are still pending implementation.

| Decision | Why | Contract |
|---|---|---|
| Inventory and spaces define the first shared filter schemas; each module owns validation, normalization and choices beside its Query, reused by lists and reports. | Filter meaning and invalid-input handling must agree across screens and exports. | [.ai/rules/reports.md § Shared filter contract](../.ai/rules/reports.md#shared-filter-contract) |
| Inventory supports both physical condition and lifecycle status as distinct filters and report columns; all three physical color groups are selectable. | ADR-0004's distinction must remain visible and useful. | [Inventory schema](../.ai/rules/reports.md#inventory-schema) |
| Space filters include type, status and building; later report schemas follow their prescribed RF criteria. | Implement the approved location intent without treating TODOs as requirements. | [Spaces schema](../.ai/rules/reports.md#spaces-schema) |
| Criteria use AND; alternatives, including explicit missing values, use OR. Invalid filters block preview/export; choices remain stable and preserve historical references. | Users must receive the records their selections describe. | [Matching](../.ai/rules/reports.md#input-and-matching), [validation and choices](../.ai/rules/reports.md#validation-normalization-and-choices) |
| RF_60 hides inaccessible reports and unavailable types; future-sprint types may appear as "Próximamente" only when authorized. | Availability and permission determine what users can discover. | [Menu agreement](../.ai/rules/reports.md#report-menu-agreement) |

Implementation sequence and module handoffs: [PLANNING.md § Reports opening sequence](PLANNING.md#reports-opening-sequence-contract-agreed-2026-09-29).

## Remaining-work rebalance (2026-10-06)

Provisionally accepted by Alejandro (@AleQuesada2012), acting as PM and architect, after a planning interview.
The Sprint 2 planning meeting has not taken place. These dated entries supersede the earlier planning defaults
where stated; historical G1/G3/B entries above remain unchanged. Tracking:
[issue #146](https://github.com/AleQuesada2012/SETI-Management-System/issues/146).

| Decision | Why | Enforced in |
|---|---|---|
| Balance effort remaining from October 6; counts are secondary, and the earlier eight-RF target is no longer the balancing metric. | Most planned Sprint 1 carry-over is complete; shared infrastructure and partially implemented RFs have unequal remaining effort. | [PLANNING.md § Provisional remaining-effort estimate](PLANNING.md#provisional-remaining-effort-estimate) |
| Keep owners, reviewers, acceptance criteria and the existing cut order. Allow milestone moves in either direction and replace the strict write/read split with dependency order. | Improve timing without reallocating module responsibility or changing approved scope. | `requirements/requirements.json`, [PLANNING.md](PLANNING.md) |
| Move RF_02/12/31, RF_20/21, RF_49/50 and RF_92/96/97/101 from Sprint 3 to Sprint 2; keep all other RF milestones. | Components/deletion have existing foundations; attachments reuse RF_41; reservation changes reuse notifications; consultations/filter completion reuse existing assignment and user data. | [PLANNING.md § Milestone changes](PLANNING.md#milestone-changes-accepted-on-2026-10-06), GitHub milestones |
| Complete the four outstanding Sprint 1 RFs first and count their remaining integration against current capacity, retaining their Sprint 1 milestones in this revision. | Preserve the original delivery backlog/history while accounting for unfinished work; this is a scoped exception to retrospective automatic carry-over. | [PLANNING.md § Plan by person](PLANNING.md#plan-by-person), RF_63/64/91/100 issues |
| Assume roughly one working day per person per week, assisted by coding agents, with short review windows throughout the week. | Faster implementation still needs testing, manuals, human review and timely dependency integration. | [PLANNING.md § Sprints](PLANNING.md#sprints), existing reviewer ownership |
| Use explained provisional package estimates; do not wait for teammate estimates before recording this revision. | Provide a concrete plan now while exposing uncertainty and accounting for existing PRs and reusable code. | [PLANNING.md § Provisional remaining-effort estimate](PLANNING.md#provisional-remaining-effort-estimate) |
| Prepare AWS/Power BI prerequisites in Sprint 2; keep deployment and post-deployment connectivity testing early in Sprint 3. Budget most AWS implementation to Alejandro, with Sebastián supporting. | Discover external access/connectivity blockers early without changing the task assignee or deployment architecture. | [PLANNING.md § Execution and dependency order](PLANNING.md#execution-and-dependency-order), tasks #111/#112 |
| Target feature completion by October 25; protect October 26–28 for integration, fixes, manuals and demonstration preparation. | Final delivery needs reserved time beyond feature implementation; the October 28 18:30 deadline remains. | [PLANNING.md § Sprints](PLANNING.md#sprints) |
| Record the plan in a planning PR and synchronize the eleven issue milestones; no additional October 14 feature commitments were identified. | Keep canonical requirement metadata, generated summaries and the live tracker aligned with the PM's adopted provisional distribution. | Issue #146, planning PR, GitHub RF issues |

## Deployment (2026-11-10)

Made by Alejandro (@AleQuesada2012) while deploying (issue #111). Supersedes the G1 entry "PostgreSQL as a container"
for production only; development and CI keep the container.

| Decision | Why | Enforced in |
|---|---|---|
| Production uses the course's Amazon RDS PostgreSQL instance, with its own `seti` database and user (never the master user), over TLS. | The course provides it; RDS backs it up and patches it. | [ADR-0012 amendment](adr/0012-docker-compose-on-serversideup-php-instead-of-sail.md), `database/sql/ops/create-app-database.sql`, `compose.prod.yaml`, [deployment.md](deployment.md) |

## Power BI feed (2026-11-11)

Made by Alejandro (@AleQuesada2012) for issue #112, the connectivity test. Supersedes the refresh plan of G3 and of
PLANNING.md § Power BI (direct connection, then a gateway on a SETI PC).

| Decision | Why | Enforced in |
|---|---|---|
| Power BI refreshes from a CSV feed that the app serves over its own HTTPS (`/powerbi/<dataset>.csv`), one dataset per `reporting` view, behind an HTTP Basic password from `.env`. No direct connection to PostgreSQL and no gateway. | The RDS instance only accepts connections from inside the VPC, so Power BI Service cannot reach it. A gateway would tie every refresh to a PC that must stay on and to one Microsoft tenant, and the stakeholder will publish the report under their own `itcr.ac.cr` account. The feed needs only the site, which is already public. | `routes/powerbi.php`, `App\Reports\PowerBiFeed`, [deployment.md § 11](deployment.md#11-power-bi) |
| The feed reads through the read-only `powerbi` login (`powerbi_reader`) when it is configured, and leaves out emails, free-text notes and the audit log. Names may appear in the dashboards (confirmed by Alejandro with the stakeholder). | ADR-0010 still holds: Power BI sees only the `reporting` views. The dashboards are shared with many viewers. | `config/database.php` (`powerbi`), `PowerBiFeed::DATASETS` |
| Refresh three times a day in Power BI Service. The app records when Power BI last read the feed and the dashboards page warns when that is older than 13 hours (RF_73, criterion 3). | Pro allows 8 refreshes a day; without an Azure app registration the app cannot ask Power BI whether a refresh failed, but it sees the feed being read. | `pages::reports.powerbi`, `reports.powerbi_feed.stale_after_hours` |

## Open

- Whether AI tools may appear as commit co-authors (team decision pending).
