---
paths:
  - 'resources/views/**'
  - 'resources/css/**'
  - 'resources/js/**'
  - 'lang/**'
---

# Frontend: Livewire, Flux, Spanish UI · platform

- **Livewire 4 single-file components.** Full pages in `resources/views/pages/<module>/<page>.blade.php`, routed with `Route::livewire('url', 'pages::<module>.<page>')`. Reusable components in `resources/views/components/<module>/`, used as `<livewire:<module>.<name> />`. Create them with `php artisan make:livewire pages::<module>.<page>` or `php artisan make:livewire <module>.<name>`.
- `resources/views/pages/dashboard.blade.php` is the reference page: `#[Title('<module>.<page>.title')]`, computed properties reading `reporting.*`, strings from `lang/es`.
- **Flux free components only** (`vendor/livewire/flux/stubs/resources/views/flux`): button, input, select, table, card, badge, callout, modal, dropdown… Flux Pro components are not installed.
- **Every string** the user reads is Spanish and lives in `lang/es/<module>.php`; use `__('assets.index.title')`. Dates as `d/m/Y`, times 24 h, America/Costa_Rica.
- **Colors come only from the tokens in `resources/css/theme.css`**: `bg-canvas`, `bg-surface`, `border-line`, `text-ink`, `text-ink-muted`, `bg-brand`/`text-brand-foreground`, `text-brand-ink`, `bg-sidebar`, `bg-backdrop`, `bg-status-good|warning|bad`, `bg-chart-1…5`. Each token has an explicit light and dark value there, so a view needs no `dark:` color classes. A missing color is a new token in both palettes (platform review), never a raw class like `bg-blue-600`. Flux components already follow the palette.
- **Mobile first.** Every page works at 360 px wide: stack filters, let tables scroll horizontally (`overflow-x-auto`), keep tap targets large.
- **Actions** authorize (`$this->authorize()`), validate, call an `app/Actions` class, and confirm with `Flux::toast()`. Navigation links use `wire:navigate`.
- The layout (`layouts/app/sidebar.blade.php`) shows each module only to users who may open it; add a module link there with the same ability as its route.
- `<x-module-placeholder>` marks an unfinished page: remove it when you implement the requirements it lists.
