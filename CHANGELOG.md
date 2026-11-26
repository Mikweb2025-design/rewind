# Changelog

## 0.1.5

* App Store listing: screenshot links now use the `github.com/.../raw/` form so the App Store image proxy can load them.

## 0.1.4

* **Fix:** the admin switch stored its value under `enabled`, the key Nextcloud itself uses for the app state; saving it could make the whole instance answer HTTP 500. It is now stored as `protection`.
* Headings in the dark panels were painted dark by Nextcloud's global `h2/h3` colours (title and "Time machine" unreadable on live instances). Colours are pinned; the page now uses the full content width.

## 0.1.3

* Readability on Nextcloud wallpapers: activity, recent rewinds and footer now sit on solid cards; preview/hint text uses the light ink of the dark panels. Checked on light and dark backgrounds.

## 0.1.2

* Screenshots in the repository and in the App Store listing (`bin/make-screenshots.sh` renders them from the real interface).
* Dial: the time no longer sits under the hands; folder button readable in every theme.

## 0.1.1

* The shield also freezes external storage, shared and group folders (every mount below the account), not only the home storage.
* A rewind keeps running when the browser or a proxy disconnects (large rewinds on S3).
* Tested with S3 primary storage and Redis (cache and locking).

## 0.1.0

First release.

* Time machine: folder picker, timeline with activity histogram, animated dial, preview before running, undo of every rewind.
* Ransomware shield: volume, ransom names and notes, encrypted-content detection; freezes the attacking device or the account (read only).
* Incidents with one-click "go back to before the attack", notifications for user and admins, optional alert email.
* Activity feed, history of rewinds, admin page, journal retention, background safety-net job.
* English, German, Italian.
