# SETI · Gestión de Activos y Espacios

Sistema web de la Oficina de Servicios Especiales de Tecnologías de la Información (SETI) del TEC, Centro Académico
de Alajuela, para llevar el inventario de activos, los espacios que los contienen y las reservas de esos espacios.
Funciona en computadora, tableta y teléfono.

**Tecnología:** PHP 8.5 · Laravel 13 · Livewire 4 + Flux · Tailwind CSS 4 · PostgreSQL 18 · Docker Compose.

| Documento | Para qué |
|---|---|
| [CONTRIBUTING.md](CONTRIBUTING.md) | Cómo trabajamos: ramas, commits, pull requests, revisiones, Definición de Terminado (en inglés) |
| [CONTEXT.md](CONTEXT.md) | Glosario del dominio (español ↔ inglés) |
| [docs/Agent-DB.md](docs/Agent-DB.md) | Modelo de datos y por qué tiene esa forma |
| [docs/requirements/](docs/requirements/README.md) | Los 105 requerimientos funcionales, con responsable y sprint |
| [docs/PLANNING.md](docs/PLANNING.md) | Plan de sprints, reasignaciones y orden de recorte |
| [docs/decisions.md](docs/decisions.md) | Registro de decisiones del equipo y sus razones |
| [docs/adr/](docs/adr) | Decisiones de arquitectura |
| [docs/manuales/](docs/manuales/README.md) | Manuales de usuario |
| [AGENTS.md](AGENTS.md) · [.ai/workflow.md](.ai/workflow.md) | Instrucciones y flujo de trabajo para agentes de IA (Claude Code, Codex, ChatGPT…) |

---

## Instalación con Docker (recomendada)

Es la forma de referencia: el mismo entorno en macOS, Windows y Linux, con PostgreSQL, correo de prueba y
WebSockets incluidos. No necesitas instalar PHP ni PostgreSQL en tu computadora.

### 1. Requisitos

- **Git**.
- **Docker**:
  - macOS: [Docker Desktop](https://www.docker.com/products/docker-desktop/) (Apple Silicon o Intel).
  - Windows 10/11: Docker Desktop con el backend **WSL 2**. Clona y trabaja **dentro de WSL** (por ejemplo en
    `~/proyectos` de Ubuntu), no en `C:\`: es mucho más rápido y evita problemas de permisos.
  - Linux: Docker Engine y el plugin `docker compose`; agrega tu usuario al grupo `docker`.

Comprueba: `docker compose version` debe mostrar la versión 2.x o superior.

### 2. Primera vez

```bash
git clone https://github.com/AleQuesada2012/SETI-Management-System.git seti
cd seti
cp .env.example .env
```

Solo en **Linux y WSL 2**: escribe en `.env` tu usuario y grupo, para que los archivos creados por los contenedores
sigan siendo tuyos (`id -u` y `id -g` en la terminal):

```dotenv
UID=1000
GID=1000
```

Luego construye la imagen, instala dependencias y prepara la base de datos:

```bash
docker compose build
docker compose run --rm --no-deps app composer install
docker compose up -d
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
```

`migrate --seed` crea las tablas, los catálogos, los roles y, en desarrollo, **datos de demostración**.

### 3. Usar la aplicación

| Servicio | Dirección |
|---|---|
| Aplicación | <http://localhost:8000> |
| Correos enviados (Mailpit) | <http://localhost:8025> |
| PostgreSQL (DBeaver, pgAdmin…) | `localhost:5433` · base `seti` · usuario `seti` · contraseña `secret` |

Usuarios de demostración (contraseña `password`): `superusuario@seti.test`, `admin@seti.test`, `admin2@seti.test`,
`reportes@seti.test`, `visualizador@seti.test`, `reservante@seti.test` y `profesora@seti.test`
(Visualizador + Reservante).

Los cambios en Blade, CSS y JavaScript se ven al instante: el servicio `vite` recarga el navegador.

### 4. Comandos del día a día

Todos los comandos de PHP se ejecutan **dentro** del contenedor `app`:

```bash
docker compose up -d                                  # encender todo
docker compose down                                   # apagar (los datos se conservan)
docker compose exec app php artisan migrate           # aplicar migraciones nuevas
docker compose exec app composer test                 # Pint + Larastan + pruebas: debe pasar antes de cada PR
docker compose exec app php artisan test --compact    # solo las pruebas
docker compose exec app vendor/bin/pint --dirty       # dar formato a lo que cambiaste
docker compose exec app php artisan migrate:fresh --seed   # empezar de cero con datos de demostración
docker compose logs -f app                            # ver errores del servidor
```

Si un puerto ya está ocupado en tu computadora, cámbialo en `.env` (`APP_PORT`, `FORWARD_DB_PORT`, `VITE_PORT`,
`REVERB_FORWARD_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT`, `APP_HTTPS_PORT`) y ejecuta `docker compose up -d` de nuevo.

### 5. Abrir el sistema desde otra computadora (HTTPS)

Los navegadores solo tratan `http://localhost` como un origen seguro. Desde otra computadora hace falta HTTPS: sin él,
por ejemplo, el botón de inicio de sesión de los tableros de Power BI no hace nada.

En `.env`, usa la IP de tu computadora en la red (cámbiala si te conectas a otra red):

```dotenv
LOCAL_HTTPS_HOST=192.168.1.50
VITE_REVERB_HOST=192.168.1.50
VITE_REVERB_PORT=8443
VITE_REVERB_SCHEME=https
```

Luego detén Vite, compila los recursos e inicia Caddy:

```bash
docker compose stop vite
docker compose exec app rm -f public/hot
docker compose run --rm vite npm run build
docker compose --profile https up -d caddy
```

El sistema queda en `https://<tu-IP>:8443`. El certificado es local, así que el navegador muestra una advertencia la
primera vez: elige continuar. Para volver al modo normal, restaura esos valores de `.env`, ejecuta
`docker compose stop caddy` y luego `docker compose up -d`.

---

## Instalación nativa de PHP, Composer y Laravel (opcional)

Útil si prefieres ejecutar PHP directamente en tu sistema (por ejemplo, para el depurador de tu editor). La base de
datos, el correo y los WebSockets siguen corriendo en Docker.

### macOS

```bash
/bin/bash -c "$(curl -fsSL https://php.new/install/mac/8.5)"
```

### Windows (PowerShell como administrador)

```powershell
Set-ExecutionPolicy Bypass -Scope Process -Force; [System.Net.ServicePointManager]::SecurityProtocol = [System.Net.ServicePointManager]::SecurityProtocol -bor 3072; iex ((New-Object System.Net.WebClient).DownloadString('https://php.new/install/windows/8.5'))
```

### Linux

```bash
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.5)"
```

Cada instalador deja listos **PHP 8.5**, **Composer** y el instalador de **Laravel** (`laravel`). Cierra y abre la
terminal, y comprueba:

```bash
php -v          # PHP 8.5.x
composer -V     # Composer 2.x
php -m | grep -i pgsql   # debe mostrar pdo_pgsql
```

Laravel no se instala aparte: es una dependencia del proyecto y llega con `composer install`. Necesitas además
**Node.js 24** ([nodejs.org](https://nodejs.org/) o `nvm install 24`).

### Ejecutar el proyecto con PHP nativo

```bash
docker compose up -d pgsql mailpit          # solo la base de datos y el correo
cp .env.example .env
```

En `.env`, apunta a los servicios publicados en tu computadora:

```dotenv
DB_HOST=127.0.0.1
DB_PORT=5433
MAIL_HOST=127.0.0.1
MAIL_PORT=1025
BROADCAST_CONNECTION=log
```

Y luego:

```bash
composer install
php artisan key:generate
php artisan migrate --seed
npm install
composer run dev        # servidor web, cola, logs y Vite en una sola terminal
```

La aplicación queda en <http://localhost:8000>. Las pruebas usan la base `seti_testing`, que Docker crea
automáticamente: `php artisan test`.

---

## Producción

Una máquina virtual en AWS ejecuta `compose.prod.yaml`: la aplicación, los procesos en segundo plano, PostgreSQL y
Caddy (HTTPS automático). Instrucciones en el encabezado de [`compose.prod.yaml`](compose.prod.yaml) y valores en
[`.env.production.example`](.env.production.example).

## Problemas frecuentes

| Síntoma | Solución |
|---|---|
| `Vite manifest not found` | El servicio `vite` no está corriendo: `docker compose up -d vite`, o `docker compose run --rm --no-deps vite npm run build`. |
| `Permission denied` en `storage/` (Linux/WSL) | Revisa `UID`/`GID` en `.env`, luego `docker compose build app && docker compose up -d`. |
| `could not translate host name "pgsql"` | Ejecutaste `php artisan` fuera de Docker con el `.env` de Docker: usa `docker compose exec app php artisan …` o la configuración nativa de arriba. |
| `filemtime(): stat failed` en `storage/framework/views` | Usaste PHP nativo y Docker con la misma carpeta: limpia la caché compilada con `docker compose exec app php artisan optimize:clear` (o `php artisan optimize:clear` en nativo). |
| La página dice *419 La sesión expiró* | La sesión dura 120 minutos; vuelve a iniciar sesión. |
