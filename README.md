# Sarah's Recipe Box

A private recipe app: categories and tags, a built-in scaler (½×, 2×, any number of servings) with US/metric conversion,
automatic share links, photo uploads, "I made this" cook logs, a cook mode with timers, a shopping list,
light/dark themes, and nightly backups with a restore script.

Laravel 12 · Livewire 4 + Flux · Tailwind 4 · MariaDB 11.8 · PHP 8.5-FPM behind Caddy, all in Docker.

## First run

```bash
cp .env.example .env
# set APP_KEY (see below), passwords, APP_URL, OWNER_EMAIL / OWNER_PASSWORD
docker compose up -d --build
docker compose exec app php artisan recipes:create-owner
```

Open <http://localhost:8086> (change `APP_PORT` in `.env` to use another port) and log in.

Generate an `APP_KEY` once:

```bash
echo "base64:$(openssl rand -base64 32)"
```

`recipes:create-owner` reads `OWNER_NAME`, `OWNER_EMAIL` and `OWNER_PASSWORD` from `.env`, or asks for them. Run it again to
change Sarah's email or password. There is no public sign-up and no emailed password reset.

## How it is put together

| Container | What it does |
|---|---|
| `recipes-caddy` | Serves `src/public` and hands PHP to `app:9000`. Plain HTTP only. |
| `recipes-app` | PHP-FPM. On every start it installs Composer packages, migrates, builds the CSS/JS and fixes permissions (`docker/php/app-bootstrap.sh`). |
| `recipes-queue` | Processes uploaded photos (resizes, strips location data). |
| `recipes-scheduler` | Runs the nightly backup at 03:00. |
| `recipes-db` | MariaDB. Data lives in `./db_data`. |

The Laravel app is in `src/`. Docker files are in `docker/`. Backups land in `backups/`.

### Behind your Apache proxy

Apache should send everything to Caddy and pass the original scheme and host along, for example:

```apache
ProxyPreserveHost On
RequestHeader set X-Forwarded-Proto "https"
ProxyPass / http://127.0.0.1:8086/
ProxyPassReverse / http://127.0.0.1:8086/
LimitRequestBody 31457280
```

In `.env` set `APP_URL=https://your.domain` and `TRUSTED_PROXIES=*`, then `docker compose up -d`. Share links and
link previews are built from `APP_URL`. Photo uploads are limited to 20 MB each (PHP: 25 MB, Caddy: 30 MB).

## Everyday commands

```bash
docker compose exec app php artisan test           # whole test suite (in-memory SQLite, never touches real data)
docker compose exec app app-bootstrap              # after pulling changes: composer, migrate, rebuild assets
docker compose logs -f queue                       # watch photo processing
docker compose exec app bash scripts/backup.sh     # back up now
```

### Backup and restore

See [`src/scripts/README.md`](src/scripts/README.md). In short: `backup.sh` writes `backups/recipes-<time>.tar.gz`
(database + photos), the newest 14 are kept (`BACKUP_KEEP`), and
`docker compose exec app bash scripts/restore.sh /backups/<file>.tar.gz` puts one back. Copy `backups/` to
another machine too; a backup on the same disk is not enough.

On Linux, make the folder writable by the container user: `sudo chown 33:33 backups`.

## What it does

- **Recipes**: ingredients typed one per line (`1 1/2 cups flour, sifted`) are understood automatically, with
  `# Heading` lines for groups. Steps can have timers. Photos, notes, source link, rating, favourites.
- **Sharing**: every recipe has a private share link made for it. Each recipe is *Private* (only Sarah),
  *Anyone with the link* (default, hidden from search engines) or *Public* (listed on Browse, with search-engine data).
  "Make a new link" cancels the old one.
- **Scaler**: ½×, 1×, 2×, 3×, a custom amount or a servings stepper; Original/US/Metric units. The choice is kept in
  the page address, so a scaled view can be shared. Volume is never converted to weight.
- **Cook mode**: big text, one step at a time, tick-off ingredients, step timers with an alarm, screen stays on.
- **Shopping list**: tick recipes, set a scale for each, and get one combined list.
- **Import**: paste a recipe link and review the filled-in form before saving.
- **Export**: JSON or Markdown of every recipe, from the account menu.
- **Install on a phone**: "Add to Home Screen" works (web app manifest and icons).

## Notes

- Photos are re-encoded as WebP (full size capped at 2400 px, plus 1200 px and 400 px copies). The upload is
  deleted afterwards so location data in the original never stays on the server.
- Search matches title, description and ingredient names with a plain `LIKE`, which is plenty for a personal collection.
- Reordering steps uses the arrow buttons, and ingredients are reordered by moving their lines.
