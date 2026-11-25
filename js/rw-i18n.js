/* Rewind - strings (en/de/it). Keys must exist in all languages (checked by tests/frontend). */
(function (root) {
  'use strict';
  var S = {
    en: {
      title: 'Rewind', shield_ok: 'Protected', shield_watch: 'Watching closely', shield_alert: 'Attack detected', shield_frozen: 'Frozen to keep your files safe', shield_off: 'Shield is off',
      shield_ok_sub: 'Every change is recorded. Mass encryption is stopped within seconds.', shield_off_sub: 'An administrator switched the shield off.', unavailable: 'Rewind needs the core apps "Versions" and "Deleted files".',
      back_to: 'You can go back to {date}', history_new: 'History starts now, older changes are covered by file versions and the trash bin', events24: '{n} changes in the last 24 h',
      incident_title: 'Suspicious activity', incident_frozen: 'Frozen', incident_watch: 'Under observation', incident_text: '{n} changes from {device}, score {score}/100', incident_when: 'started {when}',
      rewind_before: 'Go back to before the attack', unfreeze: 'Unfreeze account', false_alarm: 'False alarm', unfreeze_confirm: 'Unfreeze this account? Only do this when you are sure the device is clean.',
      machine_title: 'Time machine', machine_sub: 'Pick a folder and a moment. You see everything that will change before it happens.', folder: 'Folder', all_files: 'All files', choose_folder: 'Choose', use_folder: 'Use this folder', up: 'Up',
      range_6h: '6 hours', range_24h: '24 hours', range_7d: '7 days', range_30d: '30 days', quick_hour: '1 hour ago', quick_yesterday: 'Yesterday', quick_week: 'Last week', quick_attack: 'Before the attack',
      go_to: 'Going back to', ago: '{x} ago', activity_hint: 'Drag along the timeline. Red bars are suspicious.', no_activity: 'No recorded changes in this period.',
      preview: 'Show what will change', previewing: 'Looking…', nothing: 'Nothing to change: the folder is already like it was then.',
      p_rollback: 'files go back to an older version', p_restore: 'deleted files come back', p_trash: 'newer files go to the trash', p_rename: 'renames are undone',
      problems: '{n} items cannot be restored (no older version or not in the trash any more).', rewind_now: 'Rewind', rewind_running: 'Rewinding…', cancel: 'Cancel',
      done_title: 'Back in time', done_text: '{n} changes undone.', done_partial: '{n} changes undone, {m} could not be restored.', undo: 'Undo this rewind', undone: 'Rewind undone.',
      activity_title: 'Activity', act_create: 'created', act_write: 'changed', act_rename: 'renamed', act_delete: 'deleted', act_copy: 'copied', files_n: '{n} files', rewinding_tag: 'rewind', flagged: 'suspicious',
      history_title: 'Recent rewinds', st_done: 'done', st_partial: 'partly', st_undone: 'undone', st_failed: 'failed', footer: 'Rewind restores what Nextcloud keeps (file versions and the trash bin). It is not a replacement for backups.',
      err: 'Something went wrong.', loading: 'Loading…', today: 'today', yesterday: 'yesterday',
      a_title: 'Rewind', a_intro: 'Time machine and ransomware shield. Only what you change here applies to all accounts.', a_enabled: 'Shield and journal active', a_mode: 'When an attack is detected',
      a_mode_observe: 'Only warn (recommended for the first days)', a_mode_client: 'Freeze the attacking device (read only)', a_mode_user: 'Freeze the whole account (read only)',
      a_observe: 'Warn from score', a_freeze: 'Act from score', a_window: 'Window (seconds)', a_min: 'Minimum changes in a window', a_retention: 'Keep the journal (days)', a_email: 'Alert email (optional)', a_excluded: 'Accounts not watched (comma separated)',
      a_save: 'Save', a_saved: 'Saved', a_test: 'Send test email', a_checks: 'Requirements', a_ok: 'ready', a_missing: 'missing', a_frozen: 'Frozen now', a_incidents: 'Latest incidents', a_none: 'Nothing.', a_release: 'Release',
      a_c_versions: 'App "Versions"', a_c_trashbin: 'App "Deleted files"', a_hint_help: 'A higher score needs more evidence. 40 and 70 are good defaults.',
      reason_volume: 'unusually many changes', reason_ransom_names: 'ransom file names', reason_ransom_note: 'ransom note', reason_encrypted_content: 'encrypted content', reason_replace_pattern: 'delete-and-replace pattern'
    },
    de: {
      title: 'Rewind', shield_ok: 'Geschützt', shield_watch: 'Wird genau beobachtet', shield_alert: 'Angriff erkannt', shield_frozen: 'Eingefroren, damit deine Dateien sicher bleiben', shield_off: 'Schutzschild ist aus',
      shield_ok_sub: 'Jede Änderung wird aufgezeichnet. Massenverschlüsselung wird in Sekunden gestoppt.', shield_off_sub: 'Ein Administrator hat den Schutzschild ausgeschaltet.', unavailable: 'Rewind braucht die Kern-Apps „Versionen“ und „Gelöschte Dateien“.',
      back_to: 'Du kannst zurückgehen bis {date}', history_new: 'Der Verlauf beginnt jetzt, ältere Änderungen decken Dateiversionen und Papierkorb ab', events24: '{n} Änderungen in den letzten 24 h',
      incident_title: 'Verdächtige Aktivität', incident_frozen: 'Eingefroren', incident_watch: 'Wird beobachtet', incident_text: '{n} Änderungen von {device}, Wert {score}/100', incident_when: 'begann {when}',
      rewind_before: 'Zurück auf den Stand vor dem Angriff', unfreeze: 'Konto entsperren', false_alarm: 'Fehlalarm', unfreeze_confirm: 'Dieses Konto entsperren? Nur tun, wenn du sicher bist, dass das Gerät sauber ist.',
      machine_title: 'Zeitmaschine', machine_sub: 'Wähle einen Ordner und einen Zeitpunkt. Du siehst vorher genau, was sich ändert.', folder: 'Ordner', all_files: 'Alle Dateien', choose_folder: 'Wählen', use_folder: 'Diesen Ordner nehmen', up: 'Nach oben',
      range_6h: '6 Stunden', range_24h: '24 Stunden', range_7d: '7 Tage', range_30d: '30 Tage', quick_hour: 'Vor 1 Stunde', quick_yesterday: 'Gestern', quick_week: 'Letzte Woche', quick_attack: 'Vor dem Angriff',
      go_to: 'Zurück auf', ago: 'vor {x}', activity_hint: 'Zieh auf der Zeitleiste. Rote Balken sind verdächtig.', no_activity: 'Keine aufgezeichneten Änderungen in diesem Zeitraum.',
      preview: 'Zeigen, was sich ändert', previewing: 'Ich schaue nach…', nothing: 'Nichts zu ändern: Der Ordner ist schon so wie damals.',
      p_rollback: 'Dateien gehen auf eine ältere Version zurück', p_restore: 'gelöschte Dateien kommen zurück', p_trash: 'neuere Dateien kommen in den Papierkorb', p_rename: 'Umbenennungen werden rückgängig gemacht',
      problems: '{n} Elemente lassen sich nicht wiederherstellen (keine ältere Version oder nicht mehr im Papierkorb).', rewind_now: 'Zurückspulen', rewind_running: 'Ich spule zurück…', cancel: 'Abbrechen',
      done_title: 'Zurück in der Zeit', done_text: '{n} Änderungen rückgängig gemacht.', done_partial: '{n} Änderungen rückgängig gemacht, {m} ließen sich nicht wiederherstellen.', undo: 'Dieses Zurückspulen widerrufen', undone: 'Zurückspulen widerrufen.',
      activity_title: 'Aktivität', act_create: 'erstellt', act_write: 'geändert', act_rename: 'umbenannt', act_delete: 'gelöscht', act_copy: 'kopiert', files_n: '{n} Dateien', rewinding_tag: 'Zurückspulen', flagged: 'verdächtig',
      history_title: 'Letzte Rückspulungen', st_done: 'fertig', st_partial: 'teilweise', st_undone: 'widerrufen', st_failed: 'fehlgeschlagen', footer: 'Rewind stellt wieder her, was Nextcloud aufbewahrt (Dateiversionen und Papierkorb). Es ersetzt kein Backup.',
      err: 'Etwas ist schiefgegangen.', loading: 'Lädt…', today: 'heute', yesterday: 'gestern',
      a_title: 'Rewind', a_intro: 'Zeitmaschine und Ransomware-Schutz. Was du hier änderst, gilt für alle Konten.', a_enabled: 'Schutzschild und Journal aktiv', a_mode: 'Wenn ein Angriff erkannt wird',
      a_mode_observe: 'Nur warnen (für die ersten Tage empfohlen)', a_mode_client: 'Das angreifende Gerät einfrieren (nur lesen)', a_mode_user: 'Das ganze Konto einfrieren (nur lesen)',
      a_observe: 'Warnen ab Wert', a_freeze: 'Handeln ab Wert', a_window: 'Zeitfenster (Sekunden)', a_min: 'Mindestzahl Änderungen im Fenster', a_retention: 'Journal aufbewahren (Tage)', a_email: 'Alarm-E-Mail (optional)', a_excluded: 'Nicht überwachte Konten (Komma getrennt)',
      a_save: 'Speichern', a_saved: 'Gespeichert', a_test: 'Test-E-Mail senden', a_checks: 'Voraussetzungen', a_ok: 'bereit', a_missing: 'fehlt', a_frozen: 'Gerade eingefroren', a_incidents: 'Letzte Vorfälle', a_none: 'Nichts.', a_release: 'Freigeben',
      a_c_versions: 'App „Versionen“', a_c_trashbin: 'App „Gelöschte Dateien“', a_hint_help: 'Ein höherer Wert braucht mehr Belege. 40 und 70 sind gute Voreinstellungen.',
      reason_volume: 'ungewöhnlich viele Änderungen', reason_ransom_names: 'Erpresser-Dateinamen', reason_ransom_note: 'Erpresserbrief', reason_encrypted_content: 'verschlüsselter Inhalt', reason_replace_pattern: 'Muster „löschen und ersetzen“'
    },
    it: {
      title: 'Rewind', shield_ok: 'Protetto', shield_watch: 'Sotto stretta osservazione', shield_alert: 'Attacco rilevato', shield_frozen: 'Bloccato per tenere al sicuro i tuoi file', shield_off: 'Lo scudo è spento',
      shield_ok_sub: 'Ogni modifica viene registrata. La cifratura di massa viene fermata in pochi secondi.', shield_off_sub: "Un amministratore ha spento lo scudo.", unavailable: 'Rewind richiede le app di base «Versioni» e «File eliminati».',
      back_to: 'Puoi tornare indietro fino a {date}', history_new: 'La cronologia parte adesso, le modifiche più vecchie sono coperte da versioni e cestino', events24: '{n} modifiche nelle ultime 24 ore',
      incident_title: 'Attività sospetta', incident_frozen: 'Bloccato', incident_watch: 'Sotto osservazione', incident_text: '{n} modifiche da {device}, punteggio {score}/100', incident_when: 'iniziata {when}',
      rewind_before: "Torna a prima dell'attacco", unfreeze: "Sblocca l'account", false_alarm: 'Falso allarme', unfreeze_confirm: "Sbloccare questo account? Fallo solo se sei sicuro che il dispositivo sia pulito.",
      machine_title: 'Macchina del tempo', machine_sub: 'Scegli una cartella e un momento. Vedi tutto quello che cambierà prima che succeda.', folder: 'Cartella', all_files: 'Tutti i file', choose_folder: 'Scegli', use_folder: 'Usa questa cartella', up: 'Su',
      range_6h: '6 ore', range_24h: '24 ore', range_7d: '7 giorni', range_30d: '30 giorni', quick_hour: '1 ora fa', quick_yesterday: 'Ieri', quick_week: 'Settimana scorsa', quick_attack: "Prima dell'attacco",
      go_to: 'Si torna a', ago: '{x} fa', activity_hint: 'Trascina sulla linea del tempo. Le barre rosse sono sospette.', no_activity: 'Nessuna modifica registrata in questo periodo.',
      preview: 'Mostra cosa cambierà', previewing: 'Controllo…', nothing: 'Niente da cambiare: la cartella è già come allora.',
      p_rollback: 'file tornano a una versione più vecchia', p_restore: 'file eliminati tornano', p_trash: 'file più recenti vanno nel cestino', p_rename: 'rinomine annullate',
      problems: '{n} elementi non si possono ripristinare (nessuna versione precedente o non più nel cestino).', rewind_now: 'Riavvolgi', rewind_running: 'Riavvolgo…', cancel: 'Annulla',
      done_title: 'Tornati indietro nel tempo', done_text: '{n} modifiche annullate.', done_partial: '{n} modifiche annullate, {m} non ripristinabili.', undo: 'Annulla questo riavvolgimento', undone: 'Riavvolgimento annullato.',
      activity_title: 'Attività', act_create: 'creato', act_write: 'modificato', act_rename: 'rinominato', act_delete: 'eliminato', act_copy: 'copiato', files_n: '{n} file', rewinding_tag: 'riavvolgimento', flagged: 'sospetto',
      history_title: 'Ultimi riavvolgimenti', st_done: 'fatto', st_partial: 'parziale', st_undone: 'annullato', st_failed: 'fallito', footer: 'Rewind ripristina ciò che Nextcloud conserva (versioni dei file e cestino). Non sostituisce i backup.',
      err: 'Qualcosa è andato storto.', loading: 'Carico…', today: 'oggi', yesterday: 'ieri',
      a_title: 'Rewind', a_intro: 'Macchina del tempo e scudo anti-ransomware. Quello che cambi qui vale per tutti gli account.', a_enabled: 'Scudo e registro attivi', a_mode: 'Quando viene rilevato un attacco',
      a_mode_observe: 'Solo avvisa (consigliato nei primi giorni)', a_mode_client: 'Blocca il dispositivo che attacca (sola lettura)', a_mode_user: "Blocca tutto l'account (sola lettura)",
      a_observe: 'Avvisa da punteggio', a_freeze: 'Agisci da punteggio', a_window: 'Finestra (secondi)', a_min: 'Modifiche minime in una finestra', a_retention: 'Conserva il registro (giorni)', a_email: 'E-mail di allarme (facoltativa)', a_excluded: 'Account non controllati (separati da virgola)',
      a_save: 'Salva', a_saved: 'Salvato', a_test: 'Invia e-mail di prova', a_checks: 'Requisiti', a_ok: 'pronto', a_missing: 'manca', a_frozen: 'Bloccati ora', a_incidents: 'Ultimi incidenti', a_none: 'Niente.', a_release: 'Rilascia',
      a_c_versions: 'App «Versioni»', a_c_trashbin: 'App «File eliminati»', a_hint_help: 'Un punteggio più alto richiede più prove. 40 e 70 sono buoni valori.',
      reason_volume: 'moltissime modifiche', reason_ransom_names: 'nomi file da riscatto', reason_ransom_note: 'nota di riscatto', reason_encrypted_content: 'contenuto cifrato', reason_replace_pattern: 'schema elimina e sostituisci'
    }
  };
  root.RW_I18N = S;
  if (typeof module !== 'undefined') { module.exports = S; }
}(typeof window !== 'undefined' ? window : globalThis));
