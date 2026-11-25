# Rewind

**A time machine for your Nextcloud files, with a ransomware shield.**

Pick a folder and a moment in the past, see exactly what will change, and bring the folder back. If ransomware starts encrypting
your files, Rewind notices within seconds, freezes the attacking device and lets you go back to the moment before the attack with one click.

| | |
| --- | --- |
| **Rewind** | A timeline with an animated dial. Drag to a moment, preview (files coming back, older versions, newer files to the trash, renames undone), press *Rewind*. Every rewind can be undone. |
| **Ransomware shield** | Watches every change: unusual volume, ransom file names (`.locked`, `.[id].lockbit`, …), ransom notes, and **encrypted content** (entropy and wrong magic bytes). On an attack it freezes the device or the account (read only), notifies the user and the admins and can send an email. |
| **Activity** | What changed, when and from which device. An attack shows up as a red spike. |
| **Admin** | Switch on/off, mode (warn only / freeze device / freeze account), thresholds, retention, alert email, accounts not watched, list of frozen accounts and incidents. |

Everything runs inside your Nextcloud: no external service, no telemetry. English, German, Italian.

## What Rewind can and cannot do

Rewind restores what Nextcloud keeps: **file versions** and the **trash bin** (core apps *Versions* and *Deleted files*, required).
If a file never had a version or was removed from the trash, that file cannot come back; the preview lists such files before you press the button.
**Rewind is not a replacement for backups.** Keep an offline backup as well.

The journal starts when you install the app. Older changes are only covered as far as versions and trash reach.

## Storage and caches

* **Local disk, NFS, SMB:** works.
* **S3 / object storage as primary storage:** works (tested with an S3 service: rewind, undo, attack detection, freeze). Versions and the trash bin live in the bucket like any other file, so a rewind of thousands of files copies objects and can take minutes; the rewind keeps running even if the browser or a proxy gives up.
* **External storage** (Files external: S3, SMB, SFTP, ...) and shared or group folders: the shield freezes writes there too. Rewind restores from versions and trash exactly as for normal files; the preview lists every item that cannot be restored.
* **Redis / APCu / no cache:** Rewind keeps all its counters in the database, so it works the same with and without Redis (tested with Redis as cache and file locking).

## Install

```bash
cd /var/www/html/custom_apps            # or your apps path
tar xzf rewind-<version>.tar.gz
chown -R www-data:www-data rewind
sudo -u www-data php occ app:enable rewind
```

Nextcloud 31 to 35, PHP 8.1+. Background jobs through cron are recommended (a safety net runs every 5 minutes).
After enabling, open **Administration settings → Rewind**. The default mode is **warn only**: use it for the first days, then switch to *freeze the attacking device*.

The app declares the `filesystem` app type, which is what lets it freeze a device before a file operation runs.

## How the shield decides

Per account and device, in a sliding window (default 5 minutes):

* volume of changes compared with what the account normally does (and a minimum, default 50),
* new ransom-style names and ransom notes,
* encrypted content: the first 4 KB of new files are checked (high entropy on plain types, missing magic bytes on jpg/pdf/zip/docx, …),
* delete-and-replace patterns.

Score ≥ 40 raises an incident (warning), score ≥ 70 freezes in *freeze* modes. Big reorganisations, first syncs of a desktop client and backup archives
(compressed but with correct magic bytes) are not treated as attacks; this is covered by the tests and by `bin/simulate-normal.py`.

## Develop and test

```bash
php tests/run.php              # domain tests: detector, signals, entropy, rewind planner
node tests/frontend/run.js     # strings in 3 languages, helpers, syntax
bin/pack.sh                    # runs the tests and builds dist/rewind-<version>.tar.gz
```

End to end on a **test instance** (never on real data):

```bash
bin/deploy-local.sh                       # copy into a local Docker Nextcloud (container nc-rewind)
python3 bin/scenario-rewind.py            # change files, rewind, compare checksums, undo, compare again
python3 bin/simulate-attack.py            # AES-encrypting ransomware over WebDAV: detect, freeze, rewind
python3 bin/simulate-normal.py            # heavy normal use: must not raise an incident
```

## License

AGPL-3.0-or-later.
