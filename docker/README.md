# Running the site with Docker

One command starts everything: the website, the admin, the database, the
asset builder and the scheduler. No XAMPP, Herd, PHP or Node needed on the
machine, only [Docker Desktop](https://www.docker.com/products/docker-desktop/).

```bash
docker compose up -d
```

Then open:

| | |
|---|---|
| Shop | http://localhost:8080 |
| Admin | http://localhost:8080/admin |
| Database (HeidiSQL, TablePlus…) | `127.0.0.1`, port `3307`, user `customtailor`, password `customtailor` |

The first start takes a few minutes (it downloads images and installs
dependencies). After that it is up in seconds.

## Everyday commands

| To… | Run |
|---|---|
| Start | `docker compose up -d` |
| Stop (data is kept) | `docker compose down` |
| See what is running | `docker compose ps` |
| Follow the logs | `docker compose logs -f app` |
| Run an artisan command | `docker compose exec app php artisan <command>` |
| Run the tests | `docker compose exec app php vendor/bin/pest` |
| Rebuild after changing anything in `docker/` | `docker compose up -d --build` |

Code changes show immediately; the CSS and JavaScript rebuild themselves when
you save (the `assets` container).

## What runs

| Container | Does |
|---|---|
| `app` | PHP 8.4 + nginx, serving the project folder. On each start: installs PHP packages if missing, runs migrations. |
| `db` | MariaDB 10.11. Data lives in a Docker volume and survives restarts. |
| `assets` | Node 22, rebuilding `public/build` whenever a file changes. |
| `scheduler` | Laravel's scheduler: the hourly RankYak sync and the nightly image check. |

Settings in `compose.yaml` (database host, app URL) override `.env`, so the
same `.env` keeps working outside Docker too.

## Your data

On the very **first** start, any `.sql` file in `docker/initdb/` is imported
into the database. A copy of the XAMPP database was put there when this was
set up. The file holds customer data, so it is git-ignored and never committed.

To start over from that file (this **erases** the Docker database):

```bash
docker compose down
docker volume rm hockerty-premium_db
docker compose up -d
```

To refresh it from another database, replace `docker/initdb/customtailor.sql`
with a new dump first.

## If something goes wrong

- **Port 8080 or 3307 is taken:** pick others in a `.env` line, for example
  `APP_PORT=8090` or `DB_PORT_HOST=3308`, then `docker compose up -d`.
- **Cloudflare or Stripe calls fail with certificate errors:** an antivirus is
  inspecting HTTPS. Export its root certificate as a `.crt` file into
  `docker/certs/` and run `docker compose up -d --build`.
- **Pages are slow:** they should take well under a second. `vendor/` and
  compiled views live in Docker's own storage for speed; if it was changed,
  check `compose.yaml` still mounts the `vendor` volume.
