# The UI is server-rendered Livewire 4 single-file components with Flux (free)

The application starts from Laravel's official Livewire starter kit: Livewire 4 single-file components, Flux free components, Tailwind CSS 4 and Fortify for authentication. Each page is one Blade file (`resources/views/pages/<module>/<page>.blade.php`) holding its PHP class and its markup, routed with `Route::livewire()`. The team writes PHP and Blade only; there is no separate JavaScript front end or API to design, version and secure, and authorization stays in Laravel policies next to the data. Sessions and CSRF protection come from the framework (ADR-0001).

## Considered Options

- **Inertia with Vue or React**: a richer client, but two languages and a JSON contract for every page; too much surface for a five-person team in three sprints.
- **Plain Blade with controllers and forms**: simplest, but live search, filters and calendars (RF_07, RF_42, RF_45) would need hand-written JavaScript.
- **Filament**: fast CRUD screens, but its admin conventions fight the mockups, the Spanish domain language and the space-scoped permissions (the ★ rule).

## Consequences

Every Livewire action is an HTTP endpoint that bypasses route middleware, so every action calls `$this->authorize()`. Flux Pro components (charts, calendar, date picker) are not available; charts and the calendar are built with Blade, Tailwind and Alpine, or a small library approved by the team.
