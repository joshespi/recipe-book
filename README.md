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

### Behind your Apache proxy (on another machine)

The containers run on this server; Apache runs on a different machine and forwards to Caddy. The request path is
visitor → Apache (https) → Caddy (`APP_PORT`, plain http) → PHP.

**Apache** (on its own machine) sends everything to this server and passes the original scheme and host along.
Replace `SERVER_ADDRESS` with this server's address:

```apache
ProxyPreserveHost On
RequestHeader set X-Forwarded-Proto "https"
ProxyPass / http://SERVER_ADDRESS:8086/
ProxyPassReverse / http://SERVER_ADDRESS:8086/
LimitRequestBody 115343360
```

**`.env` on this server:**

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your.domain
APP_TIMEZONE=America/New_York      # your own time zone
SESSION_SECURE_COOKIE=true
APP_BIND=0.0.0.0                   # Caddy accepts connections from the network, not just this machine
TRUSTED_PROXIES=192.168.1.20       # Apache's address (see below)
CADDY_TRUSTED_PROXIES=private_ranges
```

**What goes in `TRUSTED_PROXIES`:** the address Apache connects *from*, as this server sees it, for example
`192.168.1.20`, or a range such as `192.168.1.0/24`. Several can be listed, separated by commas. Laravel only believes
"this request was https" and the visitor's real address when the connection comes from one of these. Without it,
every share link and redirect is built with `http://`, and the login throttle sees every visitor as the same address.
To find it, load a page through Apache and run `sudo ss -tn state established '( sport = :8086 )'` on this server:
the "Peer Address" column shows the address to use.

- Use `*` only if the firewall already limits who can reach `APP_PORT`; otherwise anyone who can reach the port could
  forge the headers.
- **`CADDY_TRUSTED_PROXIES`** is the same idea for Caddy, written with spaces between entries. The default,
  `private_ranges`, is right when Apache has a private address (`192.168.x.x`, `10.x.x.x`, `172.16–31.x.x`). If Apache
  connects from a public address, set it to that address instead (for example `203.0.113.10`), or Caddy will ignore
  Apache's headers and tell PHP the request was plain http.

**Keep the port closed to everyone but Apache.** `APP_BIND=0.0.0.0` publishes `APP_PORT` on every network
interface of this server. Note that `ufw` and `firewalld` rules do **not** apply to ports that Docker publishes, so
use one of these instead:

- The simplest: set `APP_BIND` to this server's *private* address (for example `APP_BIND=192.168.1.5`) so the port is
  never exposed on a public interface, and keep your network firewall (router or cloud security group) limited to
  Apache's address.
- Or add a rule to Docker's own chain (it runs before Docker's rules; the rule does not survive a reboot unless you
  save it, for example with `iptables-persistent`). It matches the original port because Docker rewrites it to 80:

  ```bash
  sudo iptables -I DOCKER-USER -p tcp -m conntrack --ctorigdstport 8086 --ctdir ORIGINAL ! -s 192.168.1.20 -j DROP
  ```

Whichever you choose, check from a machine other than Apache that `http://SERVER_ADDRESS:8086` does not answer.
Share links and link previews are built from `APP_URL`. Photos are limited to 20 MB each and 10 per upload (PHP and
Caddy accept up to 110 MB per request; `LimitRequestBody` above matches). Then run `docker compose up -d`.

For local development without Apache, leave `TRUSTED_PROXIES` empty and set `APP_BIND=127.0.0.1`.

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

On Linux, make the folder writable by the container user: `sudo chown 33:33 backups`. Archives are created private
(mode 600) because they contain the database, so run your off-machine copy as root or as that user.

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
  kept on a private disk until then and deleted afterwards, so location data in the original is never served.
- Search matches title, description and ingredient names with a plain `LIKE`, which is plenty for a personal collection.
- Reordering steps uses the arrow buttons, and ingredients are reordered by moving their lines.
