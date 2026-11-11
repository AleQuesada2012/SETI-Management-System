# Deploying SETI to AWS

How to take a fresh EC2 instance to a working SETI at its public HTTPS address, then keep it backed up and up to
date. Decisions behind it: [ADR-0012](adr/0012-docker-compose-on-serversideup-php-instead-of-sail.md) (as amended)
and [ADR-0007](adr/0007-files-live-outside-the-database-and-are-linked-with-foreign-keys.md). Tracking: issue #111.

| Piece | Where it runs |
|---|---|
| App (PHP + NGINX), queue worker, scheduler, Reverb (WebSockets), Caddy (HTTPS) | Docker containers on the EC2 instance, from [`compose.prod.yaml`](../compose.prod.yaml) |
| PostgreSQL | The course's **Amazon RDS** instance: its own `seti` database and `seti` user |
| Uploaded files and exported reports | The `seti-prod_storage` Docker volume on the instance's disk |
| Secrets | `.env` on the instance only, never in Git |

Commands below run **on the instance**, in an SSH session, unless a step says "AWS console".

## 1. Before you start (AWS console and DNS)

- **Domain.** An A record for the site's name (`APP_DOMAIN`) pointing to the instance's **Elastic IP** (a plain
  public IP changes when the instance stops). Caddy asks Let's Encrypt for the certificate on its first start, so the
  name must already resolve: check with `dig +short <domain>` before step 6. Let's Encrypt does not issue for
  `*.amazonaws.com` names; without an institutional domain yet, `<elastic-ip-with-dashes>.sslip.io` (for example
  `3-14-15-92.sslip.io`) resolves to that IP and can be used meanwhile.
- **EC2 security group (inbound).** 80/tcp and 443/tcp from anywhere, 443/udp from anywhere (HTTP/3), 22/tcp only
  from the team's IPs. Nothing else.
- **RDS security group (inbound).** 5432/tcp only from the EC2 instance's security group. If `psql` from the
  instance already connects, this is in place. The database should not be publicly accessible; Power BI's access is
  decided in issue #112.
- **RDS backups.** RDS console → the instance → *Maintenance & backups*: automated backups on, with a retention of at
  least 7 days.
- **SMTP.** The institution's relay host, port, user and password, and the sender address (invitations and password
  resets depend on it).
- **Memory.** Building the image runs Composer and `npm run build` on the instance. With 2 GB of RAM or less, add
  swap first:

  ```bash
  sudo dd if=/dev/zero of=/swapfile bs=1M count=2048 && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
  ```

## 2. Install Docker and Git

Check the operating system with `cat /etc/os-release`.

**Amazon Linux 2023** (the EC2 default). Its `docker` package has no Compose plugin, so it is added by hand:

```bash
sudo dnf install -y docker git
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
sudo mkdir -p /usr/local/lib/docker/cli-plugins
sudo curl -fsSL -o /usr/local/lib/docker/cli-plugins/docker-compose "https://github.com/docker/compose/releases/latest/download/docker-compose-linux-$(uname -m)"
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-compose
```

Sign out and back in (for the `docker` group), then check `docker compose version` and `docker buildx version`.
Compose builds need buildx 0.17 or later; if it is missing or older:

```bash
ARCH=$(uname -m | sed 's/x86_64/amd64/;s/aarch64/arm64/')
V=$(curl -fsSL https://api.github.com/repos/docker/buildx/releases/latest | grep -m1 '"tag_name"' | cut -d'"' -f4)
sudo curl -fsSL -o /usr/local/lib/docker/cli-plugins/docker-buildx "https://github.com/docker/buildx/releases/download/$V/buildx-$V.linux-$ARCH"
sudo chmod +x /usr/local/lib/docker/cli-plugins/docker-buildx
```

**Ubuntu.** Docker's own script installs the engine with the Compose and buildx plugins:

```bash
curl -fsSL https://get.docker.com | sudo sh && sudo usermod -aG docker "$USER"
```

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
| `MAIL_*` | The institution's SMTP relay. |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | Random values, for example `openssl rand -hex 16` each. |
| `POWERBI_EMBED_URL` | The Power BI secure-embed URL, or empty until the dashboards are published. |

Keep `TWO_FACTOR_REQUIRED=true`, `CSP_ENFORCE=false` (see step 8) and `SESSION_COOKIE=__Host-seti-session`.

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

```bash
docker compose -f compose.prod.yaml exec app php artisan users:create-first-superuser <email> "<full name>"
```

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

**Database.** RDS automated backups (step 1). To restore: RDS console → the instance → *Actions → Restore to point
in time*. That creates a **new** instance: give it the same security group, put its endpoint in `DB_HOST`, and run
`docker compose -f compose.prod.yaml up -d`.

**Files.** The `storage` volume is not in RDS. A daily copy, kept 14 days (on Amazon Linux 2023, install cron
first: `sudo dnf install -y cronie && sudo systemctl enable --now crond`):

```bash
sudo mkdir -p /var/backups/seti && (crontab -l 2>/dev/null; echo "15 3 * * * docker run --rm -v seti-prod_storage:/data:ro -v /var/backups/seti:/backup alpine:3.22 tar czf /backup/storage-\$(date +\%F).tgz -C /data . && find /var/backups/seti -name 'storage-*.tgz' -mtime +14 -delete") | crontab -
```

Those copies are on the same disk, so they do not survive losing the instance. Also copy them off the instance: an
S3 bucket (`aws s3 sync /var/backups/seti s3://<bucket>/seti/` from cron, with an instance role that can write to
it), or a daily snapshot of the instance's EBS volume (EC2 console → *Lifecycle Manager*).

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

## 11. Power BI (after issue #112)

The dashboards read only the `reporting` views, through a read-only role. Once the connection path is decided, as
the master user:

```bash
psql -v ON_ERROR_STOP=1 -h <rds-endpoint> -U postgres -d seti -f database/sql/ops/create-powerbi-reader.sql
```

Then create the login Power BI uses, a member of that role (`\password` asks for its password):

```bash
psql -h <rds-endpoint> -U postgres -d seti -c 'CREATE ROLE powerbi LOGIN IN ROLE powerbi_reader; GRANT CONNECT ON DATABASE seti TO powerbi;' -c '\password powerbi'
```

Run `create-powerbi-reader.sql` again whenever a migration adds a reporting view.

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

5. Delete the test-era copies: `rm -f /var/backups/seti/*`. If Power BI was connected, run step 11 again.
