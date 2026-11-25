# How Rewind was tested

Everything below was run on throw-away Nextcloud instances in Docker, never on real data. All scripts are in `bin/` so you can repeat every test.

## 1. Unit tests (no Nextcloud needed)

`php tests/run.php`: 20 tests, 76 checks.

* **Signals**: ransom file names (`.locked`, `.[id-…].[mail].lockbit`, `.id-XXXX.txt`, `.docx.enc`) and ransom notes (`README_DECRYPT.txt`, `HOW_TO_RECOVER_FILES.html`, `!!!READ_ME!!!.txt`) are detected; harmless names (`Main.java`, `game.play`, `backup.enc`, `readme.txt`, `README.md`) are not.
* **Entropy and magic bytes**: random bytes are flagged as encrypted for plain types (txt) and for jpg/pdf/docx without the right magic bytes; a real jpeg/zip/docx/odg (high entropy but correct header) is not; files under 1 KB are never judged.
* **Detector**: quiet use scores 0; 400 renames or 600 creates (reorganising, first desktop sync) stay below the action level; an attack crosses the freeze level after a handful of files; the baseline raises the limit for heavy users.
* **Rewind planner**: modified file rolls back once; deleted file is restored; created file goes to the trash; created-then-deleted is a no-op; rename chains run newest first; ransomware patterns (encrypted copy + deleted original, in-place overwrite) give the right steps.

`node tests/frontend/run.js`: the 107 strings exist in en, de and it with identical placeholders, helpers behave, every script parses.

## 2. End-to-end on a real Nextcloud

Helper scripts talk to the instance over WebDAV and the app's API.

| Script | What it does | Result |
| --- | --- | --- |
| `bin/scenario-rewind.py` | 30 files in a folder with a subfolder; remember checksums at moment T; then overwrite 10, rename 5, delete 5, add 5 new, move 1; preview; rewind to T; compare every checksum; undo; compare again | Preview `rename 6, restore 5, trash 5, rollback 10`, no problems. State at T restored exactly, undo restores the state before the rewind exactly. Repeated 5 times, stable. |
| `bin/simulate-attack.py` | Ransomware over WebDAV: download each file, encrypt it with AES, upload `<name>.locked`, delete the original, drop a ransom note; then rewind to before the attack | Stopped after 2 to 4 of 60 files, 1.7 to 3.6 seconds after the first encryption (HTTP 403 on the next write). All 60 files restored with identical checksums. Account can write again after unfreezing. |
| `bin/simulate-normal.py` | Heavy normal use: 120 uploads (real-looking jpg and zip headers, text), 60 renames, 40 saves of the same document | 0 incidents, not frozen. |

Modes `observe` (only warn), `client` (freeze the device) and `user` (freeze the account) were each run: observe raises an incident and does not block, client and user block and restore.

## 3. Environments

* Nextcloud 32 (Docker, SQLite): all of the above, plus the web interface in a browser (desktop and phone width): shield states, incident banner, timeline, preview, rewind animation, result, unfreeze, admin page.
* Nextcloud 31 (minimum supported version), clean install from the packed `rewind-<version>.tar.gz`: scenario, normal use and attack in all modes.
* Nextcloud 32 with **S3 as primary storage** (`adobe/s3mock`) and **Redis** as distributed cache and file locking: scenario (rewind and undo), normal use and attack all pass without code changes.
* **External storage** (Files external, local type) and shares: freeze blocks writes and deletes (HTTP 403) while reading stays possible; the rewind preview works there.

## 4. Bugs the tests found (and fixed)

* Writes went through while frozen: the app must declare the `filesystem` type so it loads before the file system is set up.
* After unfreezing, old events in the sliding window froze the account again: the detector now counts only from the last release.
* The "go back to before the attack" time was taken from an older incident: incidents are now closed per burst and start at the first suspicious event of the current burst.
* Files of every new account (`Templates/*.odg`, zip containers) were flagged as encrypted: container formats are judged by magic bytes only.
* Undo missed changes made through the versions and trash backends, which fire no file events: the rewind now writes them to the journal.
* The result animation disappeared when the page reloaded behind it, and the page did not scroll inside the Nextcloud layout.

## 5. Not tested yet

* A real S3 bucket (AWS, IONOS, MinIO) and S3 as an external storage type; the S3 service used here is a simulator.
* Nextcloud 33 to 35, group folders, end-to-end encryption, very large rewinds (tens of thousands of files).
* Real ransomware samples: the simulator encrypts and renames like common families, but detection of every family cannot be promised.
* Real Nextcloud desktop and mobile clients (the tests use WebDAV like those clients do).

## Repeat it

```bash
bin/deploy-local.sh                  # needs a Docker container named nc-rewind (nextcloud:31 or newer) with users alice and bob
python3 bin/scenario-rewind.py
python3 bin/simulate-normal.py
python3 bin/simulate-attack.py --files 60 --mode client
```

PHP's opcache keeps old code in the Apache process: restart the container after deploying.
