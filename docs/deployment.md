# Deploying SETI to AWS

How to take a fresh EC2 instance to a working SETI at its public HTTPS address, then keep it backed up and up to
date. Decisions behind it: [ADR-0012](adr/0012-docker-compose-on-serversideup-php-instead-of-sail.md) (as amended)
and [ADR-0007](adr/0007-files-live-outside-the-database-and-are-linked-with-foreign-keys.md). Tracking: issue #111.

| Piece | Where it runs |
|---|---|
| App (PHP + NGINX), queue worker, scheduler, Reverb (WebSockets), Caddy (HTTPS) | Docker containers on the EC2 instance, from [`compose.prod.yaml`](../compose.prod.yaml) |
| PostgreSQL | The course's **Amazon RDS** instance, **not** the EC2 instance: its own `seti` database and `seti` user. Nothing of PostgreSQL runs or is installed on the instance except the `psql` client. |
| Uploaded files and exported reports | The `seti-prod_storage` Docker volume on the instance's disk |
| Secrets | `.env` on the instance only, never in Git |

The instance this guide was written for runs **Ubuntu 26.04.1 LTS (Resolute Raccoon)**, kernel `7.0.0-1017-aws`, and
its default user is `ubuntu`. Deploy from `main`. Commands below run **on the instance**, in an SSH session, unless a
step says otherwise.

## 1. Before you start

The team has SSH access to the instance but not the AWS console. Whatever can be checked from the instance is
checked there; the rest is a request to whoever administers the AWS account.

- **Ask the AWS administrators** to confirm or set up:
  1. that the public IP **3.129.66.37** is an **Elastic IP**. A plain public IP changes when the instance is stopped
     and started, and the site's name and its built JavaScript (which contains the domain) would have to change with
     it;
  2. the instance's security group inbound rules: 80/tcp, 443/tcp and 443/udp (HTTP/3) from anywhere, 22/tcp only
     from the team's IPs, nothing else;
  3. RDS automated backups on, with a retention of at least 7 days;
  4. optionally, an S3 bucket the instance can write to, or a daily EBS snapshot policy, for copies off the instance
     (step 9).

  Only item 2 blocks the first deployment.
- **Domain.** An A record for the site's name (`APP_DOMAIN`) pointing to 3.129.66.37. Without an institutional
  domain, use **`3-129-66-37.sslip.io`**, which resolves to that IP. Let's Encrypt does not issue for
  `*.amazonaws.com` names. Caddy asks Let's Encrypt for the certificate on its first start, so the name must already
  resolve: `getent hosts <domain>` must print 3.129.66.37 before step 6.
- **Ports 80 and 443 reachable from the internet.** Without the console, test them: on the instance run
  `sudo timeout 60 python3 -m http.server 80`, and within that minute, from a laptop, `curl -sI http://3.129.66.37`
  must answer `HTTP/1.0 200 OK`. Repeat with `443` in both commands (`curl -sI http://3.129.66.37:443`). A timeout
  means the security group blocks the port (item 2 above).
- **RDS reachable from the instance.** `psql -h <rds-endpoint> -U postgres -d postgres` connects from the instance;
  then the RDS security group already allows it. The database should not be publicly accessible; Power BI's access is
  decided in issue #112.
- **SMTP.** The institution's relay host, port, user and password, and the sender address (invitations and password
  resets depend on it).
- **Memory.** Building the image runs Composer and `npm run build` on the instance, and Ubuntu's EC2 images have no
  swap. Check with `free -h`; with 2 GB of RAM or less, add swap first:

  ```bash
  sudo dd if=/dev/zero of=/swapfile bs=1M count=2048 && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
  ```

## 2. Install Docker and Git

Docker's own apt repository supports Ubuntu 26.04 (`resolute`) and ships the engine with the Compose and buildx
plugins that `compose.prod.yaml` needs. Do not use Ubuntu's `docker.io` package or the snap: on a fresh instance
`dpkg -l | grep -E 'docker|containerd|runc'` lists nothing; if it lists something, remove it first.

```bash
sudo apt-get update && sudo apt-get install -y ca-certificates curl git postgresql-client
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc && sudo chmod a+r /etc/apt/keyrings/docker.asc
```

```bash
printf 'Types: deb\nURIs: https://download.docker.com/linux/ubuntu\nSuites: %s\nComponents: stable\nSigned-By: /etc/apt/keyrings/docker.asc\n' "$(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}")" | sudo tee /etc/apt/sources.list.d/docker.sources
```

```bash
sudo apt-get update && sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo usermod -aG docker "$USER"
```

Sign out and back in (so the `docker` group applies), then check:

```bash
docker version --format '{{.Server.Version}}' && docker compose version && docker buildx version
```

On Ubuntu 26.04.1 (2026-11-10) these steps installed Docker Engine 29.9, Compose 5.7, buildx 0.38 and `psql` 18,
and `/etc/apt/sources.list.d/docker.sources` says `Suites: resolute`.

## 3. Get the code

The repository is private, so the instance gets a **read-only deploy key**:

```bash
ssh-keygen -t ed25519 -N "" -f ~/.ssh/seti_deploy -C "seti-ec2"
printf 'Host github.com\n  IdentityFile ~/.ssh/seti_deploy\n  IdentitiesOnly yes\n' >> ~/.ssh/config
cat ~/.ssh/seti_deploy.pub
```

A repository admin adds that public key in GitHub → the repository → *Settings → Deploy keys* (leave *Allow write
access* off). Then:

```bash
git clone git@github.com:AleQuesada2012/SETI-Management-System.git seti && cd seti
```

Deploy from `main`; note the commit (`git log -1 --oneline`) so you know what is running.

## 4. Create the database (once)

The application never uses the RDS master user (`postgres`) or the `postgres` database. One script, run as the
master user, creates a `seti` user that owns only a new `seti` database, with the same collation as development
(ICU `es-CR`) and the three extensions the schema needs.

1. Check the server version. The schema needs **PostgreSQL 16 or later** (development and CI use 18):

   ```bash
   psql -h <rds-endpoint> -U postgres -d postgres -Atc 'show server_version'
   ```

2. From the repository folder (`~/seti`), run the script. It asks for the master password, then twice for the new
   `seti` user's password: generate one with `openssl rand -hex 32` and keep it for `DB_PASSWORD` in step 5.

   ```bash
   psql -v ON_ERROR_STOP=1 -h <rds-endpoint> -U postgres -d postgres -f database/sql/ops/create-app-database.sql
   ```

The tables are created later by the app itself (migrations run when the `app` container starts).

## 5. Fill in `.env`

```bash
cp .env.production.example .env && chmod 600 .env
```

Every empty value must be filled; the comments in the file explain each one. In particular:

| Key | Value |
|---|---|
| `APP_KEY` | `echo "base64:$(openssl rand -base64 32)"`. Generate it once and keep it: changing it signs everyone out and makes encrypted values (2FA secrets) unreadable. |
| `APP_DOMAIN` | The name from step 1, without `https://`. |
| `DB_HOST` | The RDS endpoint. `DB_DATABASE` and `DB_USERNAME` stay `seti`; `DB_PASSWORD` is the one from step 4. |
| `MAIL_*` | The institution's SMTP relay, or meanwhile a Gmail account (below). |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Random values, for example `openssl rand -hex 16` each. |
| `POWERBI_FEED_*`, `POWERBI_DB_*` | The Power BI feed's password and its read-only login: step 11. Empty until then. |
| `POWERBI_EMBED_URL` | The Power BI secure-embed URL (*File → Embed report → Website or portal*: `https://app.powerbi.com/reportEmbed?reportId=…&autoAuth=true&ctid=…`), or empty until the dashboards are published. |

Keep `TWO_FACTOR_REQUIRED=true`, `CSP_ENFORCE=false` (see step 8) and `SESSION_COOKIE=__Host-seti-session`.

**Mail until the institution's relay is available.** Use a Gmail account created only for SETI, not a person's
address. A personal `@estudiantec.cr` address is a poor fit: that domain is on Microsoft 365, where password sign-in
for SMTP is being retired and is often turned off for students, and the person's password would sit in `.env`.
For the Gmail account:

1. Turn on 2-Step Verification, then create an app password (*Google Account → Security → App passwords*).
2. Fill in:

   ```
   MAIL_MAILER=smtp
   MAIL_SCHEME=smtps
   MAIL_HOST=smtp.gmail.com
   MAIL_PORT=465
   MAIL_USERNAME=<account>@gmail.com
   MAIL_PASSWORD=<the 16-character app password, without spaces>
   MAIL_FROM_ADDRESS=<account>@gmail.com
   ```

`MAIL_FROM_ADDRESS` must be the account itself: Gmail replaces any other sender. Gmail allows about 500 messages a
day, far more than invitations and resets need; the first messages may land in spam. When the relay is available,
change only the `MAIL_*` lines and run `docker compose -f compose.prod.yaml up -d` (no rebuild).

## 6. Build and start

```bash
docker compose -f compose.prod.yaml up -d --build
```

The first build takes several minutes. When the `app` container starts it runs the migrations against RDS. Check that
every service is `healthy` and that the site answers:

```bash
docker compose -f compose.prod.yaml ps
```

```bash
curl -sI "https://$(grep '^APP_DOMAIN=' .env | cut -d= -f2)/up"
```

Seen on the first start of a fresh stack: the `queue` worker may restart once because it started before the
migrations finished (harmless), and `up` failed once with `mkdir …/storage/_data/framework: file exists` because
several services prepared the empty `storage` volume at the same time; running the same `up` again fixed it. Logs:
`docker compose -f compose.prod.yaml logs -f app` (or `caddy`, `queue`, `reverb`).

## 7. First data and the first Superusuario

```bash
docker compose -f compose.prod.yaml exec app php artisan db:seed --force
```

This loads only the catalogs, report types, roles and permissions; demo users load only in a local environment.

Check that mail works **before** creating the Superusuario: the next command sends the invitation on the spot and
cannot be run a second time.

```bash
docker compose -f compose.prod.yaml exec app php artisan tinker --execute 'Mail::raw("Prueba de correo de SETI.", fn ($m) => $m->to("<your address>")->subject("Prueba de SETI"));'
```

The message must arrive (check spam). An error here is the `MAIL_*` values: fix `.env`, run
`docker compose -f compose.prod.yaml up -d`, and try again.

```bash
docker compose -f compose.prod.yaml exec app php artisan users:create-first-superuser <email> "<full name>"
```

If the command saved the Superusuario but failed to send the invitation, fix mail as above and use
"¿Olvidaste tu contraseña?" on the sign-in page with that email: the reset link sets the password just as the
invitation would.

The command works only while no Superusuario exists. It emails a 48-hour invitation: open it, set the password,
and set up two-factor authentication, which the app requires before anything else. **Store the recovery codes
somewhere safe**: nobody can reset the 2FA of the only Superusuario. Then register the rest of the team from
*Usuarios y roles*.

## 8. Check it works

In a browser, as the Superusuario:

- the notification bell updates live (Reverb through Caddy);
- "¿Olvidaste tu contraseña?" from a private window delivers the email (SMTP and the `queue` worker);
- upload a file to an asset and download it again (the `storage` volume);
- export a report;
- the browser console of the main pages shows no Content-Security-Policy reports other than the known ones on
  `wire:navigate` (#223). After a few days with no others, set `CSP_ENFORCE=true` in `.env` and run
  `docker compose -f compose.prod.yaml up -d`.

## 9. Backups and restoring

Two daily copies on the instance, kept 14 days, both from the `ubuntu` user's crontab. They do not depend on the AWS
console. RDS automated backups (step 1, item 3) are a second line for the database, restored by the AWS
administrators (*Restore to point in time* creates a new RDS instance; its endpoint then goes in `DB_HOST`).

```bash
sudo install -d -o "$USER" -m 700 /var/backups/seti
```

**Database.** `pg_dump` reads the `seti` password from `~/.pgpass`. Create it with an editor (`nano ~/.pgpass`), one
line `<rds-endpoint>:5432:*:seti:<DB_PASSWORD>` (`*`: any database, so a restore can use it too), then `chmod 600 ~/.pgpass`. The `pg_dump` from step 2 is version
18; it must be at least the server's major version (step 4).

```bash
(crontab -l 2>/dev/null; echo "0 3 * * * pg_dump 'host=<rds-endpoint> dbname=seti user=seti sslmode=require' -Fc -f /var/backups/seti/db-\$(date +\%F).dump && find /var/backups/seti -name 'db-*.dump' -mtime +14 -delete") | crontab -
```

**Files.** The `storage` volume:

```bash
(crontab -l 2>/dev/null; echo "15 3 * * * docker run --rm -v seti-prod_storage:/data:ro -v /var/backups/seti:/backup alpine:3.22 tar czf /backup/storage-\$(date +\%F).tgz -C /data . && find /var/backups/seti -name 'storage-*.tgz' -mtime +14 -delete") | crontab -
```

Those copies are on the instance's own disk, so they do not survive losing it. Copy them off it regularly: from a
team laptop, `scp -r ubuntu@3.129.66.37:/var/backups/seti ./seti-backups`. If the administrators provide an S3
bucket and an instance role (step 1, item 4), a cron `aws s3 sync /var/backups/seti s3://<bucket>/seti/` replaces
that.

To restore the database from a dump, into a new database so the current one stays until the restore is checked
(as the master user, then as `seti`):

```bash
psql -h <rds-endpoint> -U postgres -d postgres -c "CREATE DATABASE seti_restored OWNER seti TEMPLATE template0 ENCODING 'UTF8' LOCALE_PROVIDER icu ICU_LOCALE 'es-CR'" -c 'REVOKE ALL ON DATABASE seti_restored FROM PUBLIC'
psql -h <rds-endpoint> -U postgres -d seti_restored -c 'CREATE EXTENSION btree_gist' -c 'CREATE EXTENSION pg_trgm' -c 'CREATE EXTENSION unaccent'
pg_restore -l /var/backups/seti/db-<date>.dump | grep -v 'COMMENT - EXTENSION' > /tmp/restore.list
pg_restore --no-owner --exit-on-error -L /tmp/restore.list -d 'host=<rds-endpoint> dbname=seti_restored user=seti sslmode=require' /var/backups/seti/db-<date>.dump
```

The list leaves out the extensions' comments: the master user owns the extensions, so `seti` may not comment on
them, and `--exit-on-error` would stop there.

Then set `DB_DATABASE=seti_restored` in `.env` and run `docker compose -f compose.prod.yaml up -d`.

To restore the files from a copy:

```bash
docker compose -f compose.prod.yaml stop
docker run --rm -v seti-prod_storage:/data -v /var/backups/seti:/backup alpine:3.22 sh -c 'find /data -mindepth 1 -delete && tar xzf /backup/storage-<date>.tgz -C /data'
docker compose -f compose.prod.yaml up -d
```

Try one restore of each kind once, so the first real one is not the first try.

## 10. Updating to a new version

After a PR merges to `main`:

```bash
cd ~/seti && git pull --ff-only && docker compose -f compose.prod.yaml up -d --build
```

The `app` container runs new migrations as it starts; the workers restart with the new image. Seeders do not run
by themselves: when the pull changes `database/seeders/` (new catalog values, such as buildings), also run
`docker compose -f compose.prod.yaml exec app php artisan db:seed --force`, which only adds what is missing. A change
to `.env.production.example` in that pull means a new or changed setting: compare it with `.env` first.

## 11. Power BI

The RDS instance is reachable only from inside the VPC, so Power BI Service cannot connect to PostgreSQL. Instead
the app serves the `reporting` views as CSV over its own HTTPS, protected by a password, and Power BI refreshes from
there on a schedule, with no gateway (`docs/decisions.md` § Power BI feed). The address of each dataset is
`https://<APP_DOMAIN>/powerbi/<dataset>.csv` (`App\Reports\PowerBiFeed` lists them).

**On the instance.**

1. Create the read-only login the feed reads with, as the master user. The first script gives the
   `powerbi_reader` role `SELECT` on the `reporting` views only; the second creates a login in it (`\password` asks
   for its password: generate one with `openssl rand -hex 32`):

   ```bash
   psql -v ON_ERROR_STOP=1 -h <rds-endpoint> -U postgres -d seti -f database/sql/ops/create-powerbi-reader.sql
   ```

   ```bash
   psql -h <rds-endpoint> -U postgres -d seti -c 'CREATE ROLE powerbi LOGIN IN ROLE powerbi_reader; GRANT CONNECT ON DATABASE seti TO powerbi;' -c '\password powerbi'
   ```

   Run `create-powerbi-reader.sql` again whenever a migration adds a reporting view.
2. In `.env`: `POWERBI_FEED_PASSWORD` (generate it with `openssl rand -hex 32`; Power BI signs in as
   `POWERBI_FEED_USERNAME`, `powerbi` by default), `POWERBI_DB_USERNAME=powerbi` and `POWERBI_DB_PASSWORD` from
   step 1. Without `POWERBI_DB_USERNAME` the feed reads with the app's own `seti` user, which works but sees more
   than it needs. Then `docker compose -f compose.prod.yaml up -d` to load them.
3. Check it from any computer: `curl -u powerbi https://<APP_DOMAIN>/powerbi/spaces.csv` asks for the password and
   prints the header and the spaces.

**In Power BI.** The report is `docs/powerbi/seti-dashboards.pbip`.

1. Open it in Power BI Desktop. If the site's address changed, set it in *Transform data → Edit parameters →
   FeedUrl* (it ends in `/powerbi/`). When Desktop asks for the credentials of the feed, choose **Basic**, user
   `powerbi` and the feed password, at the level of `https://<APP_DOMAIN>/powerbi/`. Privacy level: Organizational.
2. *Refresh*, then *Publish* to the workspace of the account that owns the dashboards.
3. In Power BI Service, open the semantic model's *Settings*:
   - *Data source credentials → Edit credentials*: Basic, the same user and password, privacy level Organizational.
     *Skip test connection* stays off.
   - *Refresh → Configure a refresh schedule*: on, time zone *(UTC-06:00) Central America*, three times a day
     (for example 07:00, 12:30 and 18:00; Pro allows eight). Turn on the failure e-mail to the owner.
4. *File → Embed report → Website or portal*, and put the URL in `POWERBI_EMBED_URL` (step 5).

The dashboards page in the app says when Power BI last read the feed, and warns users when that was more than
`POWERBI_FEED_STALE_AFTER_HOURS` (13 by default) ago, which covers one missed refresh of a three-a-day schedule.
Changing the feed password means editing the credentials in Power BI Service too.

## 12. Test data, and emptying the database before the handover

**Temporary test data.** So the team can try every module on the deployed site, `SampleDataSeeder` loads eight
spaces in the campus buildings, about fifty assets placed through real movements (one under repair, one with a
scheduled maintenance) and reservations around the current week. It creates **no accounts**: it uses the active
people already registered (the Superusuario is required; Administradores manage the spaces and hold the assets;
Reservantes make the bookings). Register the team first, then:

```bash
docker compose -f compose.prod.yaml exec app php artisan db:seed --class=SampleDataSeeder --force
```

Its asset tags start with `PRUEBA-` and its spaces and assets carry the note "Dato de prueba". Running it again does
nothing. Never run `DemoSeeder` here: it creates accounts with a known password, and refuses to run in production.

**Emptying the database for the handover.** Test data is not removed row by row: asset history is append-only and
every change has an audit entry. Instead the whole database is emptied and set up again, which also removes the
team's accounts. The application blocks `php artisan migrate:fresh` in production on purpose; this is the
deliberate way, from the repository folder:

1. Take a last backup (step 9) if anything in it should be kept.
2. Stop the stack and remove the uploaded files (the `storage` volume is created again, empty, on the next start):

   ```bash
   docker compose -f compose.prod.yaml down && docker volume rm seti-prod_storage
   ```

3. Close any other connection to the `seti` database (for example a `psql` session), then, as the master user:

   ```bash
   psql -v ON_ERROR_STOP=1 -h <rds-endpoint> -U postgres -d postgres -f database/sql/ops/reset-app-database.sql
   ```

4. Start the stack (it runs the migrations), then repeat step 7: `db:seed --force` and
   `users:create-first-superuser` for the person who receives the system.

   ```bash
   docker compose -f compose.prod.yaml up -d
   ```

5. Delete the test-era copies: `rm -f /var/backups/seti/*`. If Power BI was connected, run `create-powerbi-reader.sql`
   again (step 11, item 1): the `powerbi` login survives the reset, but its access to the new database's views does
   not. The dashboards show the real data from their next refresh; nothing changes in Power BI.
