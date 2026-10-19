# Backup and restore

Both scripts run **inside the `app` container**, because they need `mariadb-dump` and the `DB_*` settings.

## Backup

```bash
docker compose exec app bash scripts/backup.sh
```

Writes `backups/recipes-YYYYmmdd-HHMMSS.tar.gz` on the host. Each archive holds:

- `database.sql`: a full MariaDB dump
- `photos/`: every uploaded photo (the processed WebP files)
- `manifest.json`: time, git SHA, latest migration, and recipe/photo counts

The scheduler container runs this automatically at 03:00 every night. The newest `BACKUP_KEEP` archives
(default 14) are kept and older ones are deleted.

On Linux the `backups/` folder on the host must be writable by the container user (`www-data`, uid 33):

```bash
sudo chown 33:33 backups && sudo chmod 775 backups
```

## Restore

```bash
docker compose exec app bash scripts/restore.sh /backups/recipes-YYYYmmdd-HHMMSS.tar.gz
```

The script shows the archive manifest, asks you to type `RESTORE`, takes a safety backup of the current state
(`pre-restore-*.tar.gz`), puts the site in maintenance mode, replaces the database and photos, runs
migrations, and brings the site back up. Use `--yes` before the path to skip the prompt.

## Off-machine copies

A backup that lives only on the same machine is not a full backup. Copy `backups/` somewhere else on a schedule,
for example with `rclone`, `rsync` or Syncthing. This project does not set that up for you.
