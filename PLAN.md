# Rewind – piano di esecuzione

App Nextcloud **Rewind**: macchina del tempo per file e cartelle + scudo anti-ransomware.
Questo file è il piano completo da eseguire (es. con Claude Sonnet) fase per fase.
Ogni fase ha un criterio di "fatto": non passare alla successiva finché non è soddisfatto.

---

## 0. Regole per chi esegue

- Lingua di dialogo con l'utente: **italiano**, breve e diretto. Codice, commenti, commit, README: **inglese**.
- Costruire, far girare, verificare. Mai dire "fatto" senza test verdi e prova sull'istanza di test.
- Prendere come modello le app esistenti dell'utente (stessa struttura, stessi script):
  - `~/healthcheck` → `bin/pack.sh`, `bin/deploy.sh`, `tests/run.php` (test runner senza dipendenze), `lib/Domain/*` (logica pura testabile), `lib/Service/*`, admin settings (`lib/Settings/Admin.php`, `AdminSection.php`, `templates/admin.php`, `js/hc-admin.js`), i18n en/de/it.
  - `~/timeguardian-publish` → pubblicazione App Store.
- **Igiene**: nessun nome personale, host, email o dato reale nel pacchetto (copiare il controllo "forbidden reference" di `healthcheck/bin/pack.sh`, con l'unica eccezione dell'URL del repo GitHub).
- Commit senza riferimenti personali; terminare i messaggi con la riga Co-Authored-By indicata dall'ambiente.
- Dopo ogni versione: commit, push, GitHub Release con `rewind-<version>.tar.gz` (regola dell'utente: fix → push → Release).
- Server di produzione dell'utente: Nextcloud 35 AIO, container `nextcloud-aio-nextcloud`, deploy con `bin/deploy.sh root@h3073392.stratoserver.net` (stesso schema di healthcheck). **Prima** sempre l'istanza di test locale (fase 1). Non installare sul server di produzione finché la fase 8 non è passata.
- Azioni che toccano la produzione in modo distruttivo (blocco utenti, ripristini) **solo** su utenti/cartelle di test creati apposta.

## 1. Obiettivo e promessa al cliente

> "Il tuo cloud non si può più rovinare."

1. **Rewind (macchina del tempo)** – per qualsiasi cartella (anche la radice) un cursore temporale: scegli data/ora, vedi
   l'anteprima di cosa cambierà (file da riportare indietro, da ripristinare dal cestino, da rimuovere, rinomine da annullare),
   confermi, e la cartella torna esattamente com'era. Annullabile (il rewind stesso è un evento registrato e reversibile).
2. **Scudo anti-ransomware** – rileva in tempo reale una cifratura/distruzione di massa, **congela** la fonte (dispositivo/
   sessione/token o l'intero utente), avvisa utente e admin, e propone il rewind con un clic al momento prima dell'attacco.
3. **Registro** – cronologia leggibile di cosa è cambiato, quando, da quale dispositivo/IP ("cosa è successo martedì?").

Pubblico: privati, famiglie, studi professionali, PMI (argomento NIS2/assicurazione cyber). Licenza AGPL, gratis sull'App Store.

## 2. Vincoli tecnici (verificati)

- App id **`rewind`** è libero sull'App Store (verificato: `apps.nextcloud.com/apps/rewind` → 404). Repo GitHub `Mikweb2025-design/rewind` non esiste ancora.
- Esiste `ransomware_protection` (vecchia app, ferma, solo blacklist estensioni). Nessuna app fa rewind punto-nel-tempo.
- Compatibilità: `<nextcloud min-version="31" max-version="35"/>`, PHP ≥ 8.1.
- Dipende dalle app core **files_versions** e **files_trashbin** (devono essere attive: lo mostra la pagina admin e il controllo di stato).
- API pubbliche da usare (verificare firma esatta sulla versione 31 prima di scrivere codice, nel sorgente server `lib/public/` e `apps/files_versions/lib`, `apps/files_trashbin/lib`):
  - Eventi file: `OCP\Files\Events\Node\NodeCreatedEvent`, `NodeWrittenEvent`, `BeforeNodeWrittenEvent`, `NodeRenamedEvent`, `NodeDeletedEvent`, `BeforeNodeDeletedEvent`, `NodeCopiedEvent` (registrati in `Application::register` con `$context->registerEventListener`).
  - Versioni: `OCA\Files_Versions\Versions\IVersionManager` (`getVersionsForFile`, `rollback`), versioni con timestamp (`IVersion::getTimestamp`).
  - Cestino: `OCA\Files_Trashbin\Trash\ITrashManager` (`listTrashRoot`, `listTrashFolder`, `restoreItem`), `ITrashItem::getDeletedTime`, `getOriginalLocation`.
  - Blocco scritture: storage wrapper `OC\Files\Filesystem::addStorageWrapper` registrato su hook `OC_Filesystem::preSetup` (stessa tecnica di ransomware_protection). Il wrapper lancia `OCP\Files\ForbiddenException` sulle scritture quando l'utente/sessione è congelato.
  - Invalidare token: `OCP\Authentication\Token\IProvider::invalidateTokensOfUser($uid, $clientName)` (NC ≥ 28; su 31+ ok). Per un singolo token: cercare l'API pubblica disponibile; in mancanza congelare a livello utente.
  - Notifiche: `OCP\Notification\IManager` + `INotifier`; email: `OCP\Mail\IMailer`; cache contatori: `OCP\ICacheFactory::createDistributed('rewind')` (fallback `createLocal`).
  - Job: `OCP\BackgroundJob\TimedJob` / `QueuedJob`.
- **Limite onesto da documentare**: Rewind può ripristinare solo ciò che Nextcloud conserva (versioni + cestino). Se le versioni sono state eliminate dalla scadenza automatica o il file non aveva versioni, quel file non torna. La pagina admin mostra la "copertura" (quanto indietro si può tornare) e consiglia le impostazioni di conservazione. Rewind **non** sostituisce il backup: lo dice chiaramente.

## 3. Architettura

```
rewind/
  appinfo/info.xml, routes.php
  lib/AppInfo/Application.php        registra listener, wrapper, notifier, settings
  lib/Listener/FileEventListener.php  scrive nel journal (veloce: 1 insert) + aggiorna contatori
  lib/Storage/FreezeWrapper.php       blocca scritture se utente/sessione congelati
  lib/Domain/                         logica pura, 100% testata senza Nextcloud
    Detector.php        punteggio di attacco da segnali (finestra temporale)
    Entropy.php         entropia di Shannon, controllo "magic bytes" vs estensione
    Signals.php         estensioni sospette, nomi di note di riscatto, pattern rinomina
    RewindPlanner.php   dato journal + versioni + cestino + istante T → piano di azioni
    Coverage.php        quanto indietro si può tornare per cartella
  lib/Service/
    Journal.php         accesso DB journal
    FreezeService.php   congela/scongela utente/token, stato
    RewindService.php   esegue un piano (dry-run / reale), registra l'operazione, annullabile
    AlertService.php    notifiche + email utente/admin
    ScanService.php     analisi contenuto asincrona (entropia/magic) dei file scritti
  lib/BackgroundJob/    AnalyzeQueuedJob (contenuto), CleanupJob (retention journal), ScoreJob (rete di sicurezza ogni 5 min)
  lib/Controller/       PageController, ApiController (utente), AdminController
  lib/Notification/Notifier.php
  lib/Settings/Admin.php, AdminSection.php
  lib/Migration/Version0001...php
  templates/main.php, admin.php
  js/rw-app.js, rw-admin.js, rw-i18n.js  (vanilla JS come healthcheck, niente build step)
  css/, img/ (icona app + icona sezione admin, chiara/scura)
  tests/ (runner come healthcheck), bin/pack.sh, bin/deploy.sh, bin/simulate-attack.sh
  README.md, CHANGELOG.md, docs/ (INSTALL, HOW-IT-WORKS, LIMITS), screenshots/
```

### 3.1 Tabelle (migration)

`rw_journal` – un record per evento file
- id (bigint pk), uid (string 64), file_id (bigint, null), path (string 4000), path_hash (string 40, indice), old_path (4000, null),
  action (string 16: create|write|rename|delete|copy|restore|rewind), size (bigint null), mtime (int),
  ts (int, indice con uid), client (string 64: hash del token/sessione), ua (string 255), ip (string 45),
  op_id (bigint null: riferimento a un'operazione di rewind), flags (int: bit sospetto/analizzato).
- Indici: (uid, ts), (file_id, ts), (path_hash).

`rw_freeze` – blocchi attivi e storici
- id, uid, client (null = utente intero), reason (string 64), score (int), started (int), ended (int null), by (string 64: auto|admin uid).

`rw_incident` – incidenti rilevati
- id, uid, client, started, last_seen, score, files_affected, sample_paths (text JSON max 20), status (open|frozen|rewound|dismissed), rewind_to (int null), op_id (null).

`rw_operation` – ogni rewind eseguito (per annullarlo e per audit)
- id, uid, folder_path, target_ts, created, by, plan (text JSON compresso), result (text JSON), status (dry|done|partial|undone).

### 3.2 Rilevamento (Detector)

Segnali per utente+client in una finestra scorrevole (default 5 min), contatori in cache distribuita:
1. **Volume**: n. di write/rename/delete in finestra rispetto alla norma dell'utente (mediana mobile dal journal, minimo assoluto configurabile, default 50).
2. **Rinomine verso estensioni strane**: `.locked .encrypted .crypt .enc .crypted .lock .[id].[ext]` ecc. (lista in `Signals.php`, aggiornabile) o estensione nuova non vista prima per quell'utente su >N file.
3. **Note di riscatto**: creazione di file come `README_DECRYPT*`, `HOW_TO_RECOVER*`, `*RESTORE_FILES*`, `!!!READ_ME!!!*` in più cartelle.
4. **Contenuto** (async, campione max 64 KB dall'inizio + 4 KB metà): entropia > 7.9 bit/byte su file la cui estensione è di tipo testo/immagine non compressa, oppure "magic bytes" non coerenti (`.jpg` senza `FF D8 FF`, `.pdf` senza `%PDF`, `.docx/.xlsx` senza `PK`). Formati già compressi (zip, mp4, jpg…) si valutano solo con magic bytes, non con l'entropia.
5. **Cancellazioni massive** + creazioni di file con nome simile (pattern "cancella originale, crea copia cifrata").

Punteggio 0–100 = somma pesata (pesi in `Detector.php`, testati). Soglie configurabili:
- ≥ 40 → incidente "da osservare", notifica admin silenziosa.
- ≥ 70 → **congela automaticamente** (se l'admin ha attivato l'azione automatica; default: congela il client/token, non l'utente intero) + notifica + email.
- Falsi positivi tipici da gestire nei test: riorganizzazione di grandi cartelle (rinomine verso estensioni normali), sincronizzazione iniziale del client (create, non write/rename), backup che scrivono zip/7z (già compressi → nessun segnale entropia), Collabora/OnlyOffice salvataggi frequenti (singolo file).
- Whitelist admin: client/utenti/cartelle esclusi; modalità "solo osserva" per i primi 7 giorni consigliata (default attiva alla prima installazione).

Performance: il listener fa solo 1 insert + incremento contatore; l'analisi del contenuto avviene in `QueuedJob` limitata (max N file per utente per finestra, campionamento). Obiettivo misurato: overhead < 5 ms per evento (fase 8).

### 3.3 Congelamento (FreezeWrapper + FreezeService)

- Livello client: invalida il token/app-password del dispositivo sospetto (il client desktop si disconnette e smette di caricare).
- Livello utente: il wrapper rende lo storage dell'utente in sola lettura (lettura e download funzionano, scritture/cancellazioni → 403), banner nell'interfaccia "Account in protezione".
- Sblocco: dall'utente (dopo conferma con password) o dall'admin; tutto registrato in `rw_freeze`.
- Mai bloccare l'admin che sblocca (escludere la sessione dell'admin dalla sua stessa azione).

### 3.4 Rewind (RewindPlanner + RewindService)

Input: utente, cartella, istante T. Il planner legge dal journal gli eventi in cartella con ts > T e produce, per ogni percorso, lo **stato a T**:
- file modificato dopo T → rollback alla versione con timestamp ≤ T (la più recente). Se nessuna versione: "non recuperabile" (mostrato).
- file creato dopo T → spostato nel cestino (non cancellato definitivamente; così il rewind è reversibile).
- file cancellato dopo T → ripristinato dal cestino (match per percorso originale + tempo di cancellazione); se ricreato poi, prima si sposta la versione nuova nel cestino.
- rinominato/spostato dopo T → rinomina inversa (seguire le catene di rinomine in ordine inverso).
- cartelle create/cancellate gestite come sopra, prima le cartelle poi i file.
Ordine di esecuzione: inverso cronologico. Ogni azione è idempotente e registrata (`op_id` nel journal). Risultato: done / partial (con elenco di cosa non è tornato e perché).
Dry-run obbligatorio prima dell'esecuzione: l'interfaccia mostra il conteggio per tipo e un elenco sfogliabile.
"Annulla rewind": esegue il piano inverso salvato in `rw_operation`.
Senza journal (eventi prima dell'installazione): fallback su versioni+cestino soltanto (modificati e cancellati recuperabili, creati/rinominati no) – dichiararlo nell'anteprima.

## 4. Interfaccia

**App (navigazione Nextcloud "Rewind")**, vanilla JS, stile Nextcloud (variabili CSS del tema, chiaro/scuro), responsive:
1. **Stato**: scudo verde/giallo/rosso, "Puoi tornare indietro fino al …" (copertura), ultimi incidenti, pulsante "Torna indietro".
2. **Rewind**: scelta cartella (file picker Nextcloud `OC.dialogs.filepicker` o `@nextcloud/dialogs` se disponibile senza build), cursore temporale con istogramma dell'attività (barre per ora/giorno dal journal: l'attacco si vede come un picco), anteprima, conferma, avanzamento, risultato, annulla.
3. **Attività**: registro filtrabile (cartella, tipo, dispositivo, periodo), raggruppato ("142 file modificati da Desktop-PC alle 14:03").
4. **Incidente** (pagina dedicata dal link della notifica): cosa è successo, file colpiti (campione), dispositivo/IP, pulsanti "Torna a prima dell'attacco", "Sblocca", "È un falso allarme".
Integrazione Files: voce nel menu azioni della cartella "Riporta indietro nel tempo…" (via `OCA.Files` file actions API del NC31+, se troppo complesso: link alla pagina Rewind con la cartella precompilata).

**Admin (Impostazioni → Amministrazione → Rewind)**: on/off scudo, modalità (solo osserva / congela dispositivo / congela utente), soglie, finestra, esclusioni, retention journal (default 90 giorni), email destinatari, stato prerequisiti (versions/trashbin attivi, cron attivo, retention versioni consigliata), incidenti di tutti gli utenti, pulsante "test allarme".

Lingue: en, de, it (stessa tecnica `rw-i18n.js` + test di parità chiavi come healthcheck).

## 5. Fasi di lavoro

### Fase 1 – Ambiente di test locale
- Docker locale: `docker run -d --name nc-rewind -p 8080:80 nextcloud:31` (poi ripetere con l'ultima versione stabile ≤ 35), utente admin di test + 2 utenti di test (`alice`, `bob`). Cron: `docker exec -u www-data nc-rewind php cron.php` in un loop o `occ background:cron`.
- Repo `~/rewind` (git init, branch main), scheletro app, `bin/deploy-local.sh` che copia in `custom_apps` del container e fa `occ app:enable rewind`.
- **Fatto quando**: app vuota abilitata senza errori nel log (`occ log:tail` / `data/nextcloud.log`).

### Fase 2 – Journal
- Migration + listener + Journal service + retention job.
- Test domain (formattazione record, rinomine, percorsi utente vs. assoluti `/<uid>/files/...`), test di integrazione via script: carica/modifica/rinomina/cancella con WebDAV (`curl -u alice:... -X PUT/MOVE/DELETE`), poi verificare righe nel DB.
- **Fatto quando**: ogni operazione WebDAV e da interfaccia produce esattamente un record corretto; nessun record per file in `appdata`, anteprime, versioni o cestino.

### Fase 3 – Rewind
- RewindPlanner (puro, molti test: modifiche, creazioni, cancellazioni, rinomine a catena, cartelle annidate, file ricreato dopo cancellazione, file senza versioni), RewindService, API dry-run/esegui/annulla.
- Script `bin/scenario-rewind.sh`: crea 30 file in `alice/Documents`, attende, ne modifica 10, rinomina 5, cancella 5, crea 5 nuovi; rewind al momento intermedio; confronta checksum (sha256) con lo stato salvato → identici. Poi "annulla" → identico allo stato finale.
- **Fatto quando**: lo scenario passa al 100% su NC31 e sull'ultima versione; i file non recuperabili sono elencati correttamente.

### Fase 4 – Rilevamento
- Entropy/Signals/Detector puri con test (file di testo, jpg, docx, zip, file cifrati con `openssl enc -aes-256-cbc`).
- `bin/simulate-attack.sh <user> <folder>`: simula un ransomware via WebDAV sui file di un utente di test: scarica ogni file, lo cifra con openssl, lo ricarica come `<nome>.locked`, cancella l'originale, lascia `README_DECRYPT.txt`. Velocità configurabile.
- `bin/simulate-normal.sh`: carichi normali (riorganizzazione cartelle, salvataggi ripetuti, upload foto, upload zip) per misurare i falsi positivi.
- **Fatto quando**: attacco simulato rilevato con punteggio ≥ 70 entro i primi 20 file; i carichi normali restano < 40.

### Fase 5 – Congelamento e allarmi
- FreezeWrapper, FreezeService, Notifier, email, banner.
- **Fatto quando**: durante `simulate-attack.sh` con azione automatica attiva le scritture si fermano (lo script riceve 401/403), al massimo ~20–30 file colpiti, notifica + email arrivano, sblocco funziona, l'admin non viene mai bloccato.

### Fase 6 – Interfaccia utente e admin
- Pagine della sezione 4, i18n en/de/it, temi chiaro/scuro, mobile.
- Verifica con il browser (Claude Browser o Chrome dell'utente) sull'istanza locale: screenshot di ogni pagina, flusso completo "attacco → notifica → pagina incidente → torna indietro → file a posto".
- **Fatto quando**: flusso completo eseguibile solo con clic, senza console; nessun errore JS in console.

### Fase 7 – Documentazione e materiale App Store
- README (inglese): cosa fa, screenshot, come funziona, limiti onesti (sezione 2), FAQ "non è un backup".
- `info.xml`: name, summary, description (en + de + it), categorie `security` e `files`, screenshot URL (raw GitHub), website/bugs/repository, `<dependencies>`, `<settings>`, `<background-jobs>`.
- Icone `img/app.svg` e `img/app-dark.svg` (orologio che gira all'indietro + scudo).
- CHANGELOG, LICENSE (AGPL).

### Fase 8 – Prova di carico e sicurezza
- 20.000 file in un utente, misurare tempo medio aggiunto per upload con e senza app (obiettivo < 5 ms per evento), rewind di 2.000 file (obiettivo < 2 min, in job con avanzamento se più lungo).
- Revisione sicurezza: controlli di permesso (un utente non può fare rewind di cartelle altrui; cartelle condivise → il rewind agisce solo con i permessi dell'utente; group folders documentati come limite se non supportati), CSRF sulle API, brute force non rilevante, nessun dato sensibile nei log.
- Eseguire `/code-review` (livello high) sul repository e correggere.
- **Fatto quando**: numeri raggiunti e misurati, review senza problemi aperti.

### Fase 9 – Pubblicazione
1. Repo GitHub pubblico `Mikweb2025-design/rewind` (`gh repo create`), push, Release `v0.1.0` con `rewind-0.1.0.tar.gz` (cartella di primo livello `rewind/`).
2. Certificato App Store (**passo umano**): generare `~/.nextcloud/certificates/rewind.key` + `rewind.csr` (`openssl req -nodes -newkey rsa:4096 -keyout rewind.key -out rewind.csr -subj "/CN=rewind"`), preparare lo script per la PR come `~/.nextcloud/certificates/submit-cert-pr-healthcheck.sh`; **l'utente** lo lancia e spunta DCO/AI nella PR su `nextcloud/app-certificate-requests`. Attesa tipica: alcuni giorni.
3. Quando arriva `rewind.crt` (commit nel repo dei certificati): registrare l'app e caricare la release come fatto per healthcheck/timeguardian (firma: `openssl dgst -sha512 -sign ~/.nextcloud/certificates/rewind.key rewind-<v>.tar.gz | openssl base64 -A`; registrazione: firma di `printf %s rewind`; form su apps.nextcloud.com con il Chrome dell'utente già loggato).
4. Installare sul server di produzione dell'utente in modalità "solo osserva" per 7 giorni, poi controllare incidenti/falsi positivi.

### Fase 10 – Lancio (opzionale, chiedere prima all'utente)
- Post nel forum Nextcloud (categoria apps), articolo sul sito dell'utente + infografica animata DE (regola dell'utente per ogni articolo), video demo 60 s: attacco simulato → blocco → rewind.

## 6. Roadmap dopo la 0.1

- 0.2: supporto group folders e storage esterni (solo dove versioni/cestino esistono), report PDF di incidente per assicurazione/NIS2.
- 0.3: "snapshot nominati" (salva il punto "prima del trasloco cartelle"), rewind pianificati di prova (verifica mensile automatica che il ripristino funzioni).
- 0.4: integrazione Talk (allarme in chat), webhook (n8n), API per SIEM.

## 7. Checklist finale prima di dire "fatto"

- [ ] `php tests/run.php` e test frontend verdi; `bin/pack.sh` passa il controllo igiene.
- [ ] Scenario rewind (fase 3) e attacco simulato (fasi 4–5) passano su NC31 e sull'ultima versione supportata.
- [ ] Screenshot reali in `screenshots/` e nel README.
- [ ] Release GitHub con tar.gz; versione in `info.xml` = tag = CHANGELOG.
- [ ] Limiti dichiarati in README e nell'interfaccia.
- [ ] Riepilogo all'utente in italiano: cosa è stato fatto, dove, come si usa, cosa resta (certificato = passo umano).
