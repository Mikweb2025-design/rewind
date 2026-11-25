# Changelog

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
