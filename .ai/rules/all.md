---
paths:
  - '**'
---

# Rules for every change

- **Words.** Use the glossary in `CONTEXT.md`: English term in code, Spanish term in the UI. "Space manager", never "owner of the lab"; "decommission" (baja), never "delete".
- **Data model.** `docs/Agent-DB.md` is the source of truth for tables, constraints and their reasons; §16 lists the rules every change keeps. Read the section of the tables you touch.
- **Where code goes.**
  - Business operations: one class per operation in `app/Actions/<Module>` with a `handle()` method. It authorizes, runs in one `DB::transaction()`, writes its audit entries, and dispatches notifications after commit.
  - Lists and reports: `app/Queries/<Module>` over `reporting.*` views, so a page and its report share one definition of each filter.
  - Permissions: `app/Policies`, checked with `$this->authorize()` in every Livewire action and `can:` on every route.
  - Pages: thin Livewire single-file components in `resources/views/pages/<module>`; reusable components in `resources/views/components/<module>`.
  - Routes: `routes/modules/<module>.php`. Strings: `lang/es/<module>.php`.
- **Stay in your module.** Change another module's files only through its owner (named at the top of that module's rule file); ask them in the issue or PR. The planned exceptions of planning round 3 are listed in each rule file and in `docs/PLANNING.md`. Platform files need a platform owner's review (CODEOWNERS).
- **Human decisions.** Adding a dependency, editing a merged migration, pushing to `main`, merging or approving a PR are decisions for a person. Propose them; a person does them.
- **Done** means: `composer test` passes (Pint, Larastan, Pest), the feature works in Spanish at phone width, and the PR follows `CONTRIBUTING.md`.
