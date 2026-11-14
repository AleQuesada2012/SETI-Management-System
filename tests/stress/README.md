# Stress tests (Apache JMeter 5.6)

The JMeter plans of the test plan's "Pruebas de estrés" section. They run against the **local** Docker environment
only, never the deployed site: the EC2 instance is small and the RDS database is shared.

| Plan | Scenarios (threads × iterations) |
|---|---|
| `general.jmx` | Main screens: dashboard, assets, spaces, reservations, reports, statistics (20 × 1) |
| `seguridad-reportes.jmx` | Sign-in (10 × 5); lockout after 5 wrong passwords (1 × 10); report preview (20 × 5); statistics (20 × 5); download of a generated report (20 × 5) |
| `reservas.jmx` | Reservation calendar, history and occupancy (20 × 5) |
| `activos.jmx` | Asset inventory, inventory with a search and a filter, asset page and its history (20 × 5) |
| `usuarios.jmx` | Users list, list with search and filters, a user's page, their activity, and roles (20 × 5) |

The thread groups of a plan run one after the other, so each scenario is measured on its own.

`reservas-concurrencia.sh` is not a JMeter plan: it sends 10 simultaneous bookings of the same space and slot, first
through the booking action and then as bare inserts, and checks that exactly one reservation is created each time. Run
it from the repository root with `tests/stress/reservas-concurrencia.sh`; it needs no stress accounts.

## Before the first run

1. `docker compose up -d` and `docker compose exec app php artisan migrate:fresh --seed` on an up-to-date `main`.
2. Create the stress accounts and one exported report:

   ```bash
   docker compose exec app php artisan tinker --execute="require 'tests/stress/create-stress-accounts.php';"
   ```

   It creates the custom role "Pruebas de estrés" (read-only permissions of every module, plus export) and the accounts
   `estres01@seti.test` to `estres31@seti.test`, password `password`. A custom role signs in without two-factor
   authentication. It prints the id of the report the download scenario uses (`-JgenerationId=…`). Running it again
   reuses what exists.

Sign-in is limited to 5 attempts a minute per account, so every thread uses its own account:

- `cuentas.csv` (estres01–20): the page scenarios. Each thread signs in once and keeps its session.
- `cuentas-login.csv` (estres21–30): the sign-in scenario. Each account signs in 5 times.
- estres31: the lockout scenario. It ends locked for a minute; that is the expected result.

Wait one minute between two runs of the same plan, or the sign-in scenario hits the limit.

## Run

```bash
jmeter -n -t tests/stress/seguridad-reportes.jmx -JgenerationId=1 -l tests/stress/resultados/out.jtl -e -o tests/stress/resultados/reporte/
```

```bash
jmeter -n -t tests/stress/general.jmx -l tests/stress/resultados/out.jtl -e -o tests/stress/resultados/reporte/
```

The base URL defaults to `http://localhost:8000`; change it with `-Jprotocol=…`, `-Jhost=…` and `-Jport=…`. Open
`tests/stress/resultados/reporte/index.html` (ignored by git; delete the folder before the next run) and copy the average, the maximum and the error percentage of each scenario into the test plan's
table, with a screenshot. Every page sampler asserts a 200; the lockout scenario asserts 302 for attempts 1 to 5 and
429 for attempts 6 to 10. `POST /login-0`, `-1`, `-2` in the report are the redirects after signing in.

## Adding a module's plan

Copy a page thread group from `general.jmx` (cookie manager, `cuentas.csv`, sign-in once, then the GETs of your
screens) into `tests/stress/<module>.jmx`. The "Pruebas de estrés" role can already read every module.
