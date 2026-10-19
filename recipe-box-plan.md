# Sarah's Recipe Box — Build Plan

## Context

Sarah needs a personal web app for keeping her recipes. She wants to:
- sort them into categories
- share them, with a share link made for every recipe automatically and an option to make a recipe public
- scale a recipe (half, double, or any number of servings) and convert units
- upload photos of herself making the dishes
- have automatic backups and a restore script
- use light and dark themes

This is a new project with no existing code. **Reference project:** `C:\Users\joshe\Documents\tv-tracking`. Look at its `docker-compose.yml` and `docker/php/` (`Dockerfile`, `entrypoint.sh`, `app-bootstrap.sh`, `zz-app.ini`, `zzz-laravel.conf`). Use it as a source of patterns, not a template to copy line for line. Reuse what fits and leave out what's specific to that app (TMDB, VAPID, mail relay, nginx).

**Decisions (confirmed by the user):**
- **Single owner account (Sarah).** Public registration is turned off. Visitors see only public recipes and recipes opened through a share link.
- **Blade + Livewire 3 + Alpine + Tailwind v4.** Start from the official Laravel Livewire starter kit (it includes auth). Use the current stable Laravel that `laravel new` installs.

---

## 1. Infrastructure

### Repository layout
Follow tv-tracking:
- The Laravel app lives in `./src` and is bind-mounted at `/var/www/html`.
- The Docker files live in `./docker/`.
- The database data is in `./db_data` (gitignored).
- The backups are in `./backups` (gitignored).

### Services (`docker-compose.yml`)
| Service | Image | Purpose |
|---|---|---|
| `app` | built from `docker/php/Dockerfile` | PHP-FPM on port 9000. Runs the entrypoint, then php-fpm. |
| `queue` | same image (worker service) | `php artisan queue:work --sleep=3 --tries=3 --max-time=3600`. Processes photos. |
| `scheduler` | same image (worker service) | `php artisan schedule:work`. Runs the nightly backup. |
| `caddy` | `caddy:2-alpine` | Serves `public/` and passes PHP requests to `app:9000` |
| `db` | `mariadb:11.8` | Database. Uses tv-tracking's healthcheck: `healthcheck.sh --connect --innodb_initialized`. |

Patterns to reuse from tv-tracking's `docker-compose.yml`:
- **`x-app-env` anchor:** one shared block of environment variables for app, queue and scheduler, so their settings can't drift apart. Set these in it:
  - `DB_CONNECTION=mariadb` and `DB_HOST=<db container>`
  - `SESSION_DRIVER`, `QUEUE_CONNECTION` and `CACHE_STORE`, all `database`
  - `TRUSTED_PROXIES`
  - `BACKUP_KEEP`
  - the `APP_*` and `DB_*` values
- **`x-app-service` anchor:** the shared build, the `./src` mount, `restart: always`, and the network.
- **`x-worker-service`:** queue and scheduler run as `www-data` and wait for the `storage/framework/migrated` marker before starting.
- **`app` healthcheck:** reports healthy once the `migrated` marker file exists. Queue and scheduler use `depends_on: app: condition: service_healthy`.
- Use `container_name` prefixes such as `recipes-app` and `recipes-db`.

Mounts and ports:
- Caddy mounts `./src:/var/www/html:ro` at the **same path** as the PHP containers, so the `public/storage` symlink resolves. It also mounts `./docker/caddy/Caddyfile`.
- `./backups` is bind-mounted at `/backups` in `app` and `scheduler`.
- Caddy publishes `${APP_PORT:-8086}:80`. That port is the upstream target for the user's existing Apache reverse proxy. Pick a port that doesn't clash with tv-tracking's 8085. **Do not configure Apache.**

### PHP image
Base it on tv-tracking's `docker/php/Dockerfile`: `php:8.5-fpm`, `mariadb-client`, Node copied from `node:*-slim`, Composer from `composer:latest`, the `zz-app.ini` and `zzz-laravel.conf` files. Add:
- GD with JPEG and WebP support: install `libjpeg62-turbo-dev libwebp-dev libfreetype6-dev`, then run `docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype` before `docker-php-ext-install`. Photo processing needs this.
- `mariadb-client`, `tar` and `gzip`, which the backup scripts need.

### Entrypoint and bootstrap
Reuse tv-tracking's two-script pattern:
- **`entrypoint.sh`:**
  1. Waits for the database with `mysqladmin ping`.
  2. Deletes the `migrated` marker.
  3. Runs `app-bootstrap --boot`.
  4. Touches the marker.
  5. Runs `exec php-fpm`.
- **`app-bootstrap.sh`:** safe to run again. It:
  - runs composer install (no dev dependencies in production)
  - migrates
  - builds the assets beside the live ones and swaps them in, so the site never runs without a manifest. If the build fails during boot, it keeps serving the previous build.
  - runs `optimize` in production and `optimize:clear` in other environments
  - fixes ownership with `chown www-data` on `storage` and `bootstrap/cache`

  **Add** `php artisan storage:link` before the permission fix.

### `docker/caddy/Caddyfile`
```
{
    auto_https off
    servers {
        trusted_proxies static private_ranges
    }
}
:80 {
    root * /var/www/html/public
    encode zstd gzip
    request_body {
        max_size 30MB
    }
    php_fastcgi app:9000
    file_server
}
```
- Apache terminates TLS, so Caddy serves plain HTTP only.
- `trusted_proxies` makes Caddy pass Apache's `X-Forwarded-*` headers through to PHP.

### PHP and Laravel proxy settings
- `zz-app.ini`: `upload_max_filesize=25M`, `post_max_size=30M`, `memory_limit=256M`.
- Configure trusted proxies from `TRUSTED_PROXIES` in `bootstrap/app.php`, the same way tv-tracking does. Set `APP_URL=https://<real domain>` so that generated URLs, share links and OG tags use https.
- Use the database driver for queue, session and cache. Do not add Redis.

---

## 2. Data model (migrations, Eloquent models, factories)

**recipes**
- `id`, `user_id`
- `title`, `slug` (unique), `description` (text)
- `servings` (decimal), `yield_label` (for example "cookies" or "loaves")
- `prep_minutes`, `cook_minutes`, `total_minutes` (nullable; computed if not entered)
- `source_name`, `source_url`, `notes` (text)
- `visibility`: enum `private | unlisted | public`, default `unlisted`
- `share_token` (char 32, unique)
- `is_favorite` (bool), `rating` (tinyint 0–5, nullable)
- `cover_photo_id` (nullable)
- timestamps, softDeletes
- FULLTEXT index on `title` and `description`

**categories**: `id`, `name`, `slug`, `color` (Tailwind color name), `sort_order`
**category_recipe**: pivot table, many-to-many

**ingredient_groups**: `id`, `recipe_id`, `name` (for example "For the sauce"), `position`

**ingredients**
- `id`, `recipe_id`, `ingredient_group_id` (nullable), `position`
- `quantity` decimal(10,4) nullable, `quantity_max` decimal nullable (for ranges like "2–3")
- `unit` (normalized key such as `tsp`, `cup`, `g`, `clove`; nullable)
- `name`, `note` (for example "finely chopped"), `original_text`
- When `quantity` is null (for example "salt to taste"), the ingredient is never scaled.

**steps**: `id`, `recipe_id`, `ingredient_group_id` (nullable), `position`, `body` (text), `timer_minutes` (nullable)

**photos**
- `id`, `recipe_id`, `cook_log_id` (nullable), `disk_path`, `thumb_path`, `medium_path`
- `width`, `height`, `caption`, `position`, `taken_at`

**cook_logs** ("I made this"): `id`, `recipe_id`, `cooked_on` (date), `notes`, `rating`, `scale_used` (decimal). Photos can belong to a cook log. This is the "Sarah making the recipe" gallery.

**Share tokens**
- A model `creating` hook fills `share_token` with `Str::random(32)` automatically.
- A "Regenerate link" action replaces the token, which revokes the old link.

---

## 3. Features: Phase 1 (core)

### Auth and ownership
- Use the starter kit login and remove the registration routes.
- Add `php artisan recipes:create-owner` to create Sarah's account interactively. The seeder can also read the credentials from `.env`.
- Add a `RecipePolicy`: the owner can do everything, and guests can only view, following the visibility rules below.

### Recipe CRUD (Livewire)
- One form for create and edit covering all the fields above.
- **Ingredient entry:** Sarah pastes or types free-text lines. An `IngredientParser` service turns each line into quantity, unit and name. It understands:
  - mixed fractions: `1 1/2 cups flour`
  - unicode fractions: `½`
  - decimals and ranges: `2-3`
  - unit aliases: `T`, `tbsp`, `tablespoons`, `c.`, `oz`, `g`, `ml`
  - trailing notes: `, chopped` or `(optional)`

  The parsed rows are shown and can be edited before saving.
- Ingredients, steps and groups can be reordered by drag and drop. Use `@alpinejs/sort` or SortableJS.
- "Duplicate recipe" makes a copy to use for variations.

### Categories
- Pages to create, edit, delete and reorder categories.
- A recipe can have several categories.
- The index page can be filtered by one or more category chips.

### Visibility and sharing
| Visibility | Owner | Share link `/s/{token}` | Public index `/browse` |
|---|---|---|---|
| private | yes | 404 | hidden |
| unlisted (default) | yes | yes | hidden |
| public | yes | yes | listed |

- Every recipe page has a "Copy share link" button, which uses the clipboard API. On mobile it also offers the native share sheet through `navigator.share`.
- The share page is read-only. It includes the scaler, the photos and the print button.
- Share pages have Open Graph and Twitter meta tags: the title, the description and the cover photo at medium size, so links look good in text messages.
- Unlisted pages include `<meta name="robots" content="noindex">`.
- Public pages include schema.org `Recipe` JSON-LD.

### Measurement scaler and unit converter (the key feature: build it carefully and test it)
- **Pure PHP services** with full unit tests:
  - `App\Support\Quantity` (fraction parsing and formatting)
  - `App\Support\UnitConverter`
  - `App\Support\RecipeScaler`
- **UI** (a Livewire component that also works for guests):
  - scale buttons **½× · 1× · 2× · 3×**
  - a servings stepper for a custom target
  - a unit-system toggle: **Original · US · Metric**
- **Scaling** multiplies `quantity` and `quantity_max`.
- **US output:**
  - round to readable fractions (1/8, 1/4, 1/3, 1/2, 2/3, 3/4)
  - move to a bigger or smaller unit when it reads better (for example 48 tsp → 1 cup, 0.5 cup → 8 tbsp only if that's clearer). Volume steps go tsp ↔ tbsp ↔ cup; weight goes oz ↔ lb.
- **Metric output:**
  - ml → l above 1000; g → kg above 1000
  - round sensibly: whole numbers below 10; nearest 5 above 50
- **Count units** (eggs, cloves, cans, "pinch") are scaled but never converted. Fractional eggs show as a friendly "1½ eggs".
- Never convert between volume and weight; that would need a density table.
- When the scale isn't 1×, show a notice: "Scaled 2× — quantities written inside the steps and the pan size or cook times may need adjusting."
- The scale and unit choice are kept in the query string (`?scale=2&units=metric`) so a scaled view can be shared.

### Photos
- Livewire `WithFileUploads`, with several files at once.
- Validation: `image|mimes:jpeg,png,webp|max:20480`.
  - iOS Safari normally converts HEIC to JPEG when a file is picked through `<input accept="image/*">`.
  - If HEIC still arrives, show a friendly error.
- A queued `ProcessPhoto` job uses `intervention/image` v3 with the GD driver to:
  - auto-orient the image from EXIF data
  - strip EXIF data (including GPS)
  - create WebP versions: `thumb` at 400w and `medium` at 1200w, and keep the original
- Files are stored on the `public` disk under `photos/{recipe_id}/{uuid}.webp`. The random UUID filenames prevent guessing.
- Gallery on each recipe page:
  - set the cover photo
  - add a caption
  - reorder and delete
  - a lightbox using Alpine
- **Cook log:** an "I made this!" button opens a modal with the date, notes, rating, and photos taken while cooking. The recipe page shows a "Times made: N · last made: date" timeline.

### Light and dark mode
- Tailwind v4 with `@custom-variant dark (&:where(.dark, .dark *));`
- A three-way toggle (System / Light / Dark) in the header, saved in `localStorage`.
- A small inline script in `<head>` sets the `.dark` class before the page paints, so there is no flash of the wrong theme.
- Every component must be checked in both themes. Use a warm, food-friendly palette: amber or orange accent, stone neutrals.

### Search and browse
- The recipe index has a search box (MariaDB FULLTEXT on title and description, plus a `LIKE` match on ingredient names).
- Filters: category chips, favorites only, "has photos".
- Sorting: recently added, A–Z, most cooked, highest rated.
- Responsive card grid that shows the cover thumbnail.

### Backup and restore
- **`scripts/backup.sh`** is a bash script that runs **inside the app container**. It uses `mariadb-client` and the `DB_*` environment variables, and it:
  1. Runs `mariadb-dump --single-transaction --routines --triggers` on the database, writing `database.sql`.
  2. Copies `storage/app/public/photos` into the archive as `photos/`.
  3. Writes `manifest.json` containing the timestamp, app git SHA, the latest migration name and the recipe and photo counts.
  4. Packs everything into `/backups/recipes-YYYYmmdd-HHMMSS.tar.gz`.
  5. Keeps the newest `${BACKUP_KEEP:-14}` archives and deletes the older ones.
- **Automatic run:** in `routes/console.php`, add `Schedule::exec('bash /var/www/html/scripts/backup.sh')->dailyAt('03:00')->withoutOverlapping()`. Write a log line when it succeeds or fails.
- **Manual backup from the host:**
  ```
  docker compose exec app bash scripts/backup.sh
  ```
- **`scripts/restore.sh <archive>`**, run as `docker compose exec app bash scripts/restore.sh /backups/recipes-....tar.gz`:
  1. Asks the user to type `RESTORE`. Skip this prompt with `--yes`.
  2. Takes a safety backup of the current state first.
  3. Runs `php artisan down`.
  4. Extracts the archive to a temp directory and checks that the manifest is there.
  5. Imports `database.sql`; the dump includes `DROP TABLE IF EXISTS`.
  6. Replaces the photos directory.
  7. Runs `php artisan migrate --force`, `php artisan optimize:clear` and `php artisan up`.
- `scripts/README.md` documents both scripts. It also suggests copying `./backups` to another machine (for example rclone or Syncthing), but does not set that up.

---

## 4. Features: Phase 2 (suggested extras, build after Phase 1 works)

1. **Cook mode:** a full-screen view with large text and one step at a time. It has tap-to-check ingredients and steps, keeps the screen awake with the Screen Wake Lock API, and shows a countdown timer button on any step that has `timer_minutes`.
2. **Print view:** a clean `@media print` layout with no navigation, the ingredients and steps side by side, and the current scale applied.
3. **Import from URL:** paste a recipe page link. The app fetches it on the server, reads the schema.org `Recipe` JSON-LD, and fills in the create form for Sarah to review before saving. Most recipe sites provide this data. If it's missing, show a clear message. Add timeouts and keep the fetch to http/https only, to avoid SSRF.
4. **Shopping list:** pick recipes, each with its own scale. Ingredients with the same name and a compatible unit are combined. The list can be checked off on a phone and shared as text.
5. **Favorites and ratings:** a heart toggle and 1–5 stars, shown on cards. The data comes from Phase 1.
6. **Installable PWA:** a manifest and icons so Sarah can add the app to her phone's home screen. Offline support isn't needed.
7. **Export:** download all recipes as JSON or Markdown, which is easy to move to another system.
8. **Tags:** in addition to categories, lightweight free-form tags such as "freezer-friendly", "gluten-free" or "kid favorite".

---

## 5. Suggested project layout
The app paths below are relative to `src/`. The scripts live in `src/scripts/` so that the containers can see them at `/var/www/html/scripts`.
```
docker-compose.yml  .env.example  .gitignore (db_data/, backups/)
docker/php/{Dockerfile,entrypoint.sh,app-bootstrap.sh,zz-app.ini,zzz-laravel.conf}
docker/caddy/Caddyfile
src/scripts/{backup.sh,restore.sh,README.md}
app/Support/{Quantity.php,UnitConverter.php,RecipeScaler.php,IngredientParser.php}
app/Livewire/Recipes/{Index,Show,Form,Scaler,PhotoGallery,CookLogModal}.php
app/Livewire/Categories/Manage.php
app/Http/Controllers/SharedRecipeController.php   # /s/{token}, public /browse
app/Jobs/ProcessPhoto.php
app/Policies/RecipePolicy.php
app/Console/Commands/CreateOwner.php
tests/Unit/{QuantityTest,UnitConverterTest,RecipeScalerTest,IngredientParserTest}.php
tests/Feature/{RecipeCrudTest,SharingVisibilityTest,PhotoUploadTest,BackupRestoreTest}.php
```

---

## 6. Build order (stop and verify after each step)
1. Docker stack and a fresh Laravel app with the Livewire starter kit. Login works at `http://localhost:8080`.
2. Migrations, models and factories, plus the `create-owner` command.
3. `Quantity`, `UnitConverter`, `RecipeScaler` and `IngredientParser`, built test-first.
4. Recipe CRUD, categories and the theme toggle.
5. Scaler UI on the recipe page.
6. Sharing: tokens, visibility, `/s/{token}`, `/browse`, OG tags.
7. Photos, the processing job and the cook log.
8. Search, filters and sorting.
9. Backup and restore scripts and the schedule.
10. Phase 2 extras, in the order listed.

---

## 7. Verification
- **Automated:** run `docker compose exec app php artisan test`. All unit and feature tests must pass. At minimum they must cover:
  - scaler and converter edge cases: ½× of 1/3 cup, 2× of 3/4 tsp, 3× of 2 tbsp becoming ⅜ cup, metric rounding, null quantities, ranges
  - the parser on 20 or more real ingredient lines
  - the visibility matrix: a private recipe returns 404 through its share link, unlisted works only through the link, public appears on `/browse`
  - a regenerated token makes the old link return 404
  - photo upload stores the files and dispatches the job (use `Storage::fake` and `Queue::fake`)
- **Manual, end to end:**
  1. `docker compose up -d --build`, then log in as Sarah.
  2. Create a recipe with ingredient groups, two categories and pasted ingredient lines.
  3. Toggle ½×, 2× and Metric and check the quantities are correct and easy to read.
  4. Switch between dark, light and system modes, then reload: there should be no flash of the wrong theme.
  5. Upload 3 phone photos. Check that the thumbnails appear, orientation is correct, and EXIF has been stripped (`exiftool` shows no GPS data).
  6. Log a cook with a photo.
  7. Copy the share link and open it in a private window. The read-only page and scaler should work. Set the recipe to private and confirm the link now returns 404.
  8. Paste the share link into a chat app and check the OG preview.
  9. Run `docker compose exec app bash scripts/backup.sh` and check the archive in `./backups`. Delete a recipe and a photo, run `restore.sh` with that archive, and confirm both are back.
  10. Check that the scheduler logs a backup, by running `php artisan schedule:test` or waiting overnight.
- **Behind Apache:** with the real domain, check that generated links and share URLs use `https://` and that photo uploads of 20 MB or less succeed through both proxies. Apache's own body-size limit may also need raising; that is outside this project.
