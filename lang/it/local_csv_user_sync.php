<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Italian strings for local_csv_user_sync.
 *
 * @package     local_csv_user_sync
 * @category    string
 * @copyright   2026 mdlbox.com
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['csv_user_sync:uploadsource'] = 'Fornire file CSV per la sincronizzazione utenti nelle cartelle sorgente';
$string['email:conflict_body'] = "Buongiorno,\n\nla sincronizzazione utenti CSV su {\$a->sitename} ha saltato {\$a->count} riga/righe perché l'indirizzo email è già usato da un altro account e il sito non consente account con la stessa email (allowaccountssameemail).\n\n{\$a->conflicts}\n\nCorreggere i file CSV o gli account esistenti, poi attendere la sincronizzazione successiva.";
$string['email:conflict_line'] = '{$a->source}, riga {$a->row}: username "{$a->username}", email "{$a->email}" già usata da: {$a->existing}';
$string['email:conflict_more'] = '... e altri {$a}. Vedere il log della sincronizzazione per l\'elenco completo.';
$string['email:conflict_subject'] = '[{$a}] Sincronizzazione utenti CSV: conflitti email';
$string['email:newuser_subject'] = '[{$a}] Accesso account Moodle';
$string['email:userdelete_line'] = '{$a->username} ({$a->email}) - {$a->source}, riga {$a->row}';
$string['email:userdeleteblocked_body'] = "Buongiorno,\n\nla sincronizzazione utenti CSV su {\$a->sitename} ha richiesto {\$a->count} eliminazione/i di account in una sola esecuzione, più del massimo consentito di {\$a->max}. Nessun account è stato eliminato.\n\n{\$a->users}\n\nVerificare i file CSV. Se le eliminazioni sono volute, aumentare \"Numero massimo di eliminazioni per esecuzione\" nelle impostazioni del plugin oppure distribuirle su più esecuzioni.";
$string['email:userdeleteblocked_subject'] = '[{$a}] Sincronizzazione utenti CSV: eliminazioni di account bloccate';
$string['email:userdeleted_body'] = "Buongiorno,\n\nla sincronizzazione utenti CSV su {\$a->sitename} ha eliminato {\$a->count} account:\n\n{\$a->users}\n\nGli account eliminati non possono essere ripristinati dall'interfaccia di Moodle.";
$string['email:userdeleted_subject'] = '[{$a}] Sincronizzazione utenti CSV: account eliminati';
$string['error:conflictnotifyfailed'] = 'Impossibile inviare la notifica dei conflitti email.';
$string['error:courseexcluded'] = 'Il corso "{$a}" non può essere gestito dalle iscrizioni CSV: è la pagina iniziale del sito o il corso che contiene le cartelle sorgente.';
$string['error:csvheaderduplicate'] = 'Intestazione CSV duplicata: {$a}.';
$string['error:csvheaderinvalid'] = 'Intestazione CSV non valida alla colonna {$a}.';
$string['error:csvheadernotfound'] = 'Intestazione CSV non trovata.';
$string['error:csvpathnotfound'] = 'Il percorso CSV non esiste: {$a}';
$string['error:csvpathnotreadable'] = 'Il percorso CSV non e leggibile: {$a}';
$string['error:csvpathrequired'] = 'Il percorso CSV e obbligatorio.';
$string['error:csvsourceconflict'] = 'Errore configurazione: imposta solo "Percorso file CSV" oppure "Upload file CSV", non entrambi.';
$string['error:csvsourcemissing'] = 'Errore configurazione: imposta "Percorso file CSV", carica un file CSV oppure seleziona cartelle sorgente.';
$string['error:csvuploadnotfound'] = 'File CSV caricato non trovato nello storage Moodle. Carica di nuovo il file nelle impostazioni del plugin.';
$string['error:delimiterinvalid'] = 'Il delimitatore deve contenere esattamente un carattere.';
$string['error:emailconflict'] = 'Riga saltata: l\'email per "{$a->username}" è già usata da un altro account ({$a->existing}) e non sono consentiti account con la stessa email.';
$string['error:emailsendfailed'] = 'Invio email accesso account fallito per utente "{$a}".';
$string['error:enrolfailed'] = 'Sincronizzazione iscrizione fallita: {$a}';
$string['error:enrolheaderspair'] = 'Le intestazioni iscrizione CSV devono includere sia "course_shortname" sia "role_shortname".';
$string['error:enrolmissingrole'] = 'Valore role_shortname mancante per l iscrizione.';
$string['error:foldermissing'] = 'La cartella sorgente configurata (ID modulo {$a}) non è più un\'attività Cartella del corso sorgente. Verificare le impostazioni del plugin.';
$string['error:folderuntrustedauthor'] = 'Cartella "{$a->folder}": file "{$a->file}" ignorato perché non è stato caricato da un utente con la capability local/csv_user_sync:uploadsource nel contesto di sistema.';
$string['error:folderuntrustedmanagers'] = 'Cartella "{$a->folder}" saltata: questi utenti possono gestirne i file ma non hanno la capability local/csv_user_sync:uploadsource nel contesto di sistema: {$a->users}. Rimuovere i loro diritti di gestione file nel corso sorgente o assegnare loro la capability.';
$string['error:forbiddenemail'] = 'Dominio email non consentito: {$a}';
$string['error:invalidauthfallback'] = 'Metodo auth "{$a->auth}" non valido. Fallback a "{$a->fallback}".';
$string['error:invaliddate'] = 'Formato data non valido per "{$a->field}" con valore "{$a->value}".';
$string['error:invaliddateorder'] = 'La data fine iscrizione non puo essere precedente alla data inizio.';
$string['error:invalidemail'] = 'Email non valida: {$a}';
$string['error:invalidflag'] = 'Valore non valido per "{$a->field}": "{$a->value}". Valori ammessi: 0 o 1.';
$string['error:invalidusername'] = 'Username non valido: {$a}';
$string['error:manualenroldisabled'] = 'L\'iscrizione manuale è disattivata nel corso "{$a}": attivarla nei metodi di iscrizione del corso oppure rimuovere la riga.';
$string['error:manualenrolmissing'] = 'Istanza iscrizione manuale mancante per il corso "{$a}".';
$string['error:manualenrolmissingglobal'] = 'Plugin di iscrizione manuale non disponibile.';
$string['error:missingrequiredheaders'] = 'Intestazioni CSV obbligatorie mancanti: {$a}';
$string['error:nosuffixavailable'] = 'Nessuno username valido con suffisso numerico disponibile per "{$a}".';
$string['error:protectedaccount'] = 'Riga saltata: l\'account "{$a}" è un amministratore del sito, un account privilegiato (capability di sistema moodle/user:update o local/csv_user_sync:uploadsource), l\'ospite o un account eliminato, e non viene mai modificato dalla sincronizzazione.';
$string['error:rolenotcourselevel'] = 'Il ruolo "{$a}" non è consentito nelle iscrizioni CSV (vedere "Ruoli consentiti nelle iscrizioni CSV") o non può essere assegnato nel contesto corso.';
$string['error:sourcecourseinvalid'] = 'Il corso con ID {$a} non esiste oppure è la pagina iniziale del sito.';
$string['error:sourcecoursemissing'] = 'Sono selezionate cartelle sorgente, ma il corso sorgente non è configurato o non esiste più.';
$string['error:unknowncourse'] = 'Corso non trovato per shortname: {$a}';
$string['error:unknownrole'] = 'Ruolo non trovato per shortname: {$a}';
$string['error:usercreate'] = 'Creazione utente fallita: {$a}';
$string['error:usercreatedemailfailed'] = 'Utente "{$a->username}" creato, ma invio email credenziali fallito: {$a->message}';
$string['error:userdeleteauthor'] = 'Riga saltata: eliminazione richiesta, ma questa sorgente non può richiedere eliminazioni. L\'autore del CSV (e, per le cartelle, ogni utente che può gestirne i file) deve avere moodle/user:delete nel contesto di sistema; un percorso file sul server non ha un autore da verificare e non può mai richiedere eliminazioni.';
$string['error:userdeletedisabled'] = 'Riga saltata: user_delete vale 1 ma l\'eliminazione degli account è disattivata nelle impostazioni del plugin.';
$string['error:userdeletefailed'] = 'Eliminazione dell\'account "{$a}" non riuscita.';
$string['error:userdeleteincompleterun'] = '{$a} eliminazione/i richieste non eseguite: durante l\'esecuzione si è verificato un errore inatteso e un\'esecuzione incompleta non elimina mai account.';
$string['error:userdeletelimit'] = 'Nessun account eliminato: in questa esecuzione sono state richieste {$a->requested} eliminazioni, più del massimo di {$a->max}. Gli amministratori sono stati avvisati.';
$string['error:userdeletemissing'] = 'Riga saltata: per eliminare un account servono username ed email.';
$string['error:userdeletenotifyfailed'] = 'Impossibile inviare l\'email di notifica delle eliminazioni.';
$string['error:usermissingmandatoryfields'] = 'Campi utente obbligatori mancanti. Richiesti: username, firstname, lastname, email.';
$string['error:usermissingmandatoryfieldsrow'] = 'Valori utente obbligatori mancanti nella riga. Campi vuoti: {$a}.';
$string['error:userupdate'] = 'Aggiornamento utente fallito: {$a}';
$string['error:valuetoolong'] = 'Riga saltata: valore troppo lungo per: {$a}.';
$string['event:accountemailsent'] = 'Email di accesso all\'account inviata';
$string['log:conflictnotified'] = 'Notifica conflitti email inviata ({$a->count} conflitto/i, {$a->recipients} destinatario/i).';
$string['log:conflictnotify_dryrun'] = 'Dry-run: verrebbe inviata una notifica dei conflitti email ({$a} conflitto/i).';
$string['log:emailsent'] = 'Email accesso account inviata a "{$a}".';
$string['log:enrolcreated'] = 'Iscrizione creata per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enrolcreated_dryrun'] = 'Dry-run: iscrizione verrebbe creata per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enroldeleteabsent'] = 'Nessuna iscrizione manuale da eliminare per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enroldeleted'] = 'Iscrizione eliminata per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enroldeleted_dryrun'] = 'Dry-run: iscrizione verrebbe eliminata per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enrolsuspended'] = 'Iscrizione sospesa per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enrolsuspended_dryrun'] = 'Dry-run: iscrizione verrebbe sospesa per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enrolunchanged'] = 'Iscrizione non modificata per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enrolupdated'] = 'Iscrizione aggiornata per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:enrolupdated_dryrun'] = 'Dry-run: iscrizione verrebbe aggiornata per utente ID {$a->userid} nel corso "{$a->course}".';
$string['log:errorpersist'] = 'Impossibile salvare una riga log del plugin: {$a}';
$string['log:manualinstancecreated'] = 'Istanza iscrizione manuale creata per il corso "{$a}".';
$string['log:manualinstancecreated_dryrun'] = 'Dry-run: istanza iscrizione manuale verrebbe creata per il corso "{$a}".';
$string['log:usercreate_dryrun'] = 'Dry-run: l utente "{$a}" verrebbe creato.';
$string['log:usercreated'] = 'Utente "{$a}" creato.';
$string['log:userdelete_dryrun'] = 'Dry-run: l\'account "{$a}" verrebbe eliminato.';
$string['log:userdeleted'] = 'Account "{$a}" eliminato.';
$string['log:userdeletenotify_dryrun'] = 'Dry-run: non viene inviata alcuna email di notifica delle eliminazioni.';
$string['log:userdeletequeued'] = 'Richiesta l\'eliminazione dell\'account "{$a}": viene eseguita al termine dell\'esecuzione.';
$string['log:userdeletesummary'] = 'Eliminazioni richieste: {$a->requested}. Account eliminati: {$a->deleted}.';
$string['log:usernamesuffixed'] = 'Lo username "{$a->requested}" appartiene a un account con email diversa: uso "{$a->assigned}".';
$string['log:userreactivate_dryrun'] = 'Dry-run: l\'account "{$a}" verrebbe riattivato.';
$string['log:userreactivated'] = 'Account "{$a}" riattivato.';
$string['log:usersessionsclosed'] = 'Email o metodo di autenticazione di "{$a}" modificati: sessioni aperte chiuse.';
$string['log:usersuspend_dryrun'] = 'Dry-run: l\'account "{$a}" verrebbe sospeso e disconnesso.';
$string['log:usersuspended'] = 'Account "{$a}" sospeso e disconnesso.';
$string['log:userunchanged'] = 'Utente "{$a}" non modificato.';
$string['log:userupdate_dryrun'] = 'Dry-run: l utente "{$a}" verrebbe aggiornato.';
$string['log:userupdated'] = 'Utente "{$a}" aggiornato.';
$string['pluginname'] = 'Sincronizzazione utenti CSV';

$string['privacy:metadata:conflictnotification'] = 'Le email di notifica elencano username ed email delle righe CSV saltate perché l\'email è già usata e degli account eliminati (o la cui eliminazione è stata bloccata) dalla sincronizzazione. Vengono inviate agli amministratori del sito e/o all\'indirizzo di notifica configurato.';
$string['privacy:metadata:conflictnotification:email'] = 'L\'indirizzo email della riga CSV o dell\'account eliminato.';
$string['privacy:metadata:conflictnotification:username'] = 'Lo username della riga CSV o dell\'account eliminato.';
$string['privacy:metadata:core_files'] = 'Il plugin salva opzionalmente il file CSV sorgente nello storage file di Moodle quando viene caricato dalle impostazioni.';
$string['privacy:metadata:local_csv_user_sync_log'] = 'Memorizza i log di esecuzione della sincronizzazione CSV.';
$string['privacy:metadata:local_csv_user_sync_log:level'] = 'Livello severita del log.';
$string['privacy:metadata:local_csv_user_sync_log:message'] = 'Testo del messaggio di log.';
$string['privacy:metadata:local_csv_user_sync_log:rownum'] = 'Numero riga CSV collegato al log.';
$string['privacy:metadata:local_csv_user_sync_log:runid'] = 'Identificativo della run di sincronizzazione.';
$string['privacy:metadata:local_csv_user_sync_log:timecreated'] = 'Timestamp di creazione del log.';
$string['privacy:metadata:local_csv_user_sync_log:userid'] = 'ID utente referenziato dal log.';
$string['privacy:metadata:local_csv_user_sync_log:username'] = 'Username referenziato dal log.';
$string['settings:allowedroles'] = 'Ruoli consentiti nelle iscrizioni CSV';
$string['settings:allowedroles_desc'] = 'Solo questi ruoli possono essere assegnati tramite la colonna role_shortname. Lasciare deselezionati i ruoli privilegiati (ad esempio Manager) salvo stretta necessità.';
$string['settings:authsection'] = 'Autenticazione';
$string['settings:conflictemail'] = 'Indirizzo per le notifiche';
$string['settings:conflictemail_desc'] = 'Indirizzo che riceve le notifiche del plugin al termine di ogni esecuzione. Conflitti email: quando "Consenti account con la stessa email" (allowaccountssameemail) è disattivato, le righe con un\'email già usata da un altro account vengono saltate ed elencate; lasciare vuoto per notificare invece gli amministratori del sito. Eliminazione di account: il riepilogo degli account eliminati e l\'avviso "troppe eliminazioni" vengono sempre inviati agli amministratori del sito, e anche a questo indirizzo se impostato. In modalità dry-run non viene inviata alcuna email.';
$string['settings:csvpath'] = 'Percorso file CSV';
$string['settings:csvpath_desc'] = 'Percorso assoluto del file CSV da elaborare. Usa questo oppure "Upload file CSV", non entrambi.';
$string['settings:csvpathinline_fail'] = 'Percorso non raggiungibile';
$string['settings:csvpathinline_ok'] = 'Percorso raggiungibile';
$string['settings:csvpathstatus'] = 'Stato percorso CSV';
$string['settings:csvpathstatus_empty'] = 'Nessun percorso configurato.';
$string['settings:csvpathstatus_fail'] = 'File non raggiungibile o non leggibile: {$a}';
$string['settings:csvpathstatus_ok'] = 'File raggiungibile e leggibile: {$a}';
$string['settings:csvstoredfile'] = 'Upload file CSV';
$string['settings:csvstoredfile_desc'] = 'Carica il file CSV con drag and drop. Usa questo oppure "Percorso file CSV", non entrambi.';
$string['settings:defaultauth'] = 'Metodo autenticazione di default';
$string['settings:defaultauth_desc'] = 'Usato quando la colonna auth nel CSV e vuota o non valida. Default: manual.';
$string['settings:delimiter'] = 'Delimitatore';
$string['settings:delimiter_desc'] = 'Delimitatore CSV a singolo carattere. Default: punto e virgola (;).';
$string['settings:detailedlog'] = 'Log dettagliato';
$string['settings:detailedlog_desc'] = 'Se abilitato, ogni passaggio della sincronizzazione (sorgenti, file, righe, utenti, iscrizioni, avvisi ed errori) viene incluso nell\'output del task e nella tabella log del plugin. Se disabilitato, vengono registrati solo l\'avvio, i totali, l\'esito e la durata di ogni esecuzione; avvisi ed errori vengono solo contati.';
$string['settings:dryrun'] = 'Modalita dry-run';
$string['settings:dryrun_desc'] = 'Se abilitato, Moodle non salva dati. Le operazioni vengono solo simulate e loggate.';
$string['settings:emailsection'] = 'Email nuovi utenti';
$string['settings:emailtemplate'] = 'Template email';
$string['settings:emailtemplate_default'] = "Ciao {{firstname}},\n\nsu {{sitename}} e stato creato un account per te.\n\nUsername: {{username}}\nURL imposta password: {{setpasswordurl}}\nURL login: {{loginurl}}\n\nPer motivi di sicurezza, questo link e monouso e scade automaticamente.";
$string['settings:emailtemplate_desc'] = 'Placeholder disponibili: {{firstname}}, {{lastname}}, {{username}}, {{setpasswordurl}}, {{sitename}}, {{loginurl}}. Il vecchio placeholder {{password}} e supportato come alias di {{setpasswordurl}}.';
$string['settings:emptyvalues'] = 'Valori CSV vuoti';
$string['settings:emptyvalues_clear'] = 'Cancella il valore esistente';
$string['settings:emptyvalues_desc'] = 'Cosa succede a un utente esistente quando una colonna facoltativa (inclusi i campi profilo personalizzati) è presente ma vuota nel CSV. Le colonne obbligatorie non possono mai essere vuote e la lingua non viene mai cancellata.';
$string['settings:emptyvalues_ignore'] = 'Ignoralo e mantieni il valore esistente';
$string['settings:encoding'] = 'Encoding file';
$string['settings:encoding:auto'] = 'Rilevamento automatico encoding';
$string['settings:encoding_desc'] = 'Encoding usato dal file CSV.';
$string['settings:filesection'] = 'File CSV';
$string['settings:foldersection'] = 'Cartelle sorgente';
$string['settings:foldersection_desc'] = 'I file CSV possono essere letti anche da attività Cartella di un corso dedicato. Ogni cartella selezionata deve contenere un solo file CSV; se ne contiene più di uno, viene usato il file modificato più di recente e gli altri vengono segnalati nel log. Le cartelle vengono elaborate una dopo l\'altra, nell\'ordine della pagina del corso, dopo l\'eventuale file CSV configurato sopra.<br><strong>Sicurezza:</strong> i file CSV contengono dati personali e controllano account e iscrizioni. Nascondere le cartelle sorgente ai partecipanti e assegnare la capability "local/csv_user_sync:uploadsource" (contesto di sistema) solo a utenti fidati: i file caricati da chiunque altro vengono rifiutati.';
$string['settings:lifecyclesection'] = 'Sospensione ed eliminazione degli account';
$string['settings:lifecyclesection_desc'] = 'Colonne CSV facoltative che agiscono sull\'intero account Moodle (non sull\'iscrizione a un corso, che usa le colonne "suspended" e "deleted"). Agiscono solo su account esistenti e non protetti (amministratori del sito, ospite, account privilegiati). Provare ogni modifica prima in modalità dry-run.';
$string['settings:matchmode'] = 'Corrispondenza account';
$string['settings:matchmode_desc'] = 'Come le righe CSV vengono associate agli account esistenti. Con "Username ed email", una riga aggiorna un account solo se username ed email corrispondono entrambi (email senza distinzione tra maiuscole e minuscole). Se lo username appartiene a un account con un\'altra email, la riga viene assegnata a "username1", "username2"...: si aggiorna un account con suffisso già esistente con la stessa email, altrimenti si crea il primo suffisso libero.';
$string['settings:matchmode_username'] = 'Solo username (comportamento precedente)';
$string['settings:matchmode_usernameemail'] = 'Username ed email';
$string['settings:parsersection'] = 'Formato CSV';
$string['settings:schedulehint'] = 'Frequenza task';
$string['settings:schedulehint_desc'] = 'La frequenza si configura in Scheduled tasks: {$a}';
$string['settings:sendemail'] = 'Invia email credenziali';
$string['settings:sendemail_desc'] = 'Invia username e link monouso per impostare la password solo agli utenti appena creati. Ogni email inviata viene registrata nei log di Moodle (evento "Email di accesso all\'account inviata"); gli invii falliti vengono registrati da Moodle come "Email non riuscita" e il link monouso di un\'email non riuscita viene invalidato. Gli account sospesi o con autenticazione nologin non ricevono email.';
$string['settings:sourcecourse'] = 'ID corso sorgente';
$string['settings:sourcecourse_desc'] = 'ID del corso che contiene le attività Cartella sorgente. Dopo averlo modificato, salvare le impostazioni per aggiornare l\'elenco delle cartelle qui sotto.';
$string['settings:sourcecourse_selected'] = 'Corso selezionato: {$a}';
$string['settings:sourcefolders'] = 'Cartelle da sincronizzare';
$string['settings:sourcefolders_desc'] = 'Attività Cartella del corso sorgente da cui leggere i file CSV.';
$string['settings:sourcefolders_none'] = 'Nessuna attività Cartella disponibile. Configurare prima l\'ID del corso sorgente e salvare le impostazioni.';
$string['settings:sourcefolders_option'] = '{$a->name} (ID {$a->cmid})';
$string['settings:syncsection'] = 'Comportamento sync';
$string['settings:templatedesc'] = 'Scarica un file CSV di esempio con tutti i campi supportati (inclusi i campi profilo personalizzati): {$a}';
$string['settings:templatedownload'] = 'Scarica modello CSV';
$string['settings:templateheading'] = 'Modello CSV';
$string['settings:updateonlychanged'] = 'Aggiorna solo se cambiato';
$string['settings:updateonlychanged_desc'] = 'Se abilitato, l utente viene aggiornato solo quando almeno un valore e cambiato.';
$string['settings:userdeleteenabled'] = 'Consenti l\'eliminazione degli account (user_delete)';
$string['settings:userdeleteenabled_desc'] = 'Se attivo, una riga con user_delete = 1 elimina l\'account il cui username e la cui email corrispondono entrambi esattamente alla riga (mai un account con suffisso). L\'eliminazione usa l\'API standard di Moodle ed è irreversibile: vengono rimossi iscrizioni, ruoli, gruppi, coorti e valutazioni. L\'autore del CSV deve avere moodle/user:delete nel contesto di sistema (per le cartelle, anche ogni utente che può gestirne i file); un percorso file sul server non può mai richiedere eliminazioni. Le eliminazioni vengono eseguite al termine dell\'esecuzione, dopo aver letto tutte le sorgenti, e viene inviata un\'email di riepilogo. Nella stessa esecuzione vengono saltate le righe successive di un account richiesto per l\'eliminazione.';
$string['settings:userdeletemax'] = 'Numero massimo di eliminazioni per esecuzione';
$string['settings:userdeletemax_desc'] = 'Se un\'esecuzione richiede più eliminazioni di questo numero, non viene eliminato nessun account e gli amministratori del sito vengono avvisati per email. Con 0 ogni eliminazione è bloccata.';
$string['settings:usersuspendenabled'] = 'Consenti la sospensione degli account (user_suspend)';
$string['settings:usersuspendenabled_desc'] = 'Se attivo, la colonna user_suspend sospende l\'account (1, l\'utente viene anche disconnesso) o lo riattiva (0). Un valore vuoto non modifica l\'account. La sospensione è reversibile e conserva valutazioni e storico.';
$string['task:dryrunskipenrol'] = 'Dry-run: iscrizione saltata per il nuovo utente "{$a}" perche l utente non esiste ancora.';
$string['task:enddetailshint'] = 'Attivare il log dettagliato nelle impostazioni del plugin per vedere i dettagli.';
$string['task:endfailure'] = 'Sincronizzazione terminata con errori in {$a->duration} s. Errori: {$a->errors}. Avvisi: {$a->warnings}.';
$string['task:endsuccess'] = 'Sincronizzazione terminata con successo in {$a->duration} s.';
$string['task:endwarnings'] = 'Sincronizzazione terminata con successo in {$a->duration} s, con {$a->warnings} avviso/i.';
$string['task:fatal'] = 'Sincronizzazione interrotta per errore inatteso: {$a}';
$string['task:lockfailed'] = 'Task saltato perche e gia in esecuzione un altra sincronizzazione.';
$string['task:releaselockfailed'] = 'Impossibile rilasciare il lock del task: {$a}';
$string['task:rowfatal'] = 'Riga {$a->row} saltata per errore inatteso: {$a->message}';
$string['task:sourcefatal'] = 'Sorgente "{$a->source}" interrotta per errore inatteso: {$a->message}. Le sorgenti successive vengono comunque elaborate.';
$string['task:sourcefile'] = 'Lettura del file "{$a}".';
$string['task:sourcefolder'] = 'Cartella "{$a->name}" (ID {$a->cmid})';
$string['task:sourcepath'] = 'Percorso file "{$a}"';
$string['task:sourcesfailed'] = 'Sincronizzazione utenti CSV completata con sorgenti fallite: {$a}';
$string['task:sourcestart'] = 'Elaborazione sorgente {$a->index}/{$a->total}: {$a->source}.';
$string['task:sourcesummary'] = 'Sorgente "{$a->source}" completata in {$a->duration} s. Righe: {$a->rows}. Utenti creati: {$a->userscreated}. Utenti aggiornati: {$a->usersupdated}. Iscrizioni create: {$a->enrolmentscreated}. Iscrizioni aggiornate: {$a->enrolmentsupdated}. Errori: {$a->errors}.';
$string['task:sourceupload'] = 'File caricato "{$a}"';
$string['task:start'] = 'Sincronizzazione avviata. Dry-run: {$a}.';
$string['task:summary'] = 'Sorgenti: {$a->sources}. Righe elaborate: {$a->rows}. Utenti creati: {$a->userscreated}. Utenti aggiornati: {$a->usersupdated}. Iscrizioni create: {$a->enrolmentscreated}. Iscrizioni aggiornate: {$a->enrolmentsupdated}. Errori: {$a->errors}.';
$string['task:syncusers'] = 'Sincronizza utenti da CSV';
$string['task:unknownerror'] = 'Errore sconosciuto';
$string['template:commentrow'] = 'Riga istruzioni: rimuovere questa riga prima dell importazione dati.';
$string['template:coursehint'] = 'Shortname corso';
$string['template:datehintoptional'] = 'Data opzionale: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY, DD-MM-YYYY o timestamp UNIX';
$string['template:datehintrequired'] = 'Data obbligatoria: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY, DD-MM-YYYY o timestamp UNIX';
$string['template:deletedhint'] = 'Elimina iscrizione: 0 = no, 1 = si';
$string['template:rolehint'] = 'Shortname ruolo (esempio: student)';
$string['template:suspendedhint'] = 'Sospendi iscrizione: 0 = no, 1 = si';
$string['template:userdeletehint'] = 'Elimina l\'account (irreversibile): 1 = sì, vuoto = no';
$string['template:usersuspendhint'] = 'Account: 1 = sospendi, 0 = riattiva, vuoto = nessuna modifica';
$string['warning:emaildisabled'] = 'Email di accesso per "{$a}" non inviata: le email sono disattivate su questo sito ($CFG->noemailever).';
$string['warning:folderexposed'] = '{$a} è visibile nella pagina del corso. I file CSV contengono dati personali: nascondere la cartella ai partecipanti.';
$string['warning:foldermultiplecsv'] = 'La cartella "{$a->folder}" contiene più di un file CSV. Uso il più recente "{$a->selected}"; ignorati: {$a->ignored}.';
$string['warning:foldernocsv'] = 'La cartella "{$a}" non contiene alcun file CSV: saltata.';
$string['warning:folderopenaccess'] = '{$a->source}: il corso e la cartella sono visibili e il corso consente {$a->methods}: qualsiasi utente può leggere i file CSV di questa cartella (dati personali). Nascondere la cartella o disattivare questi metodi di iscrizione.';
$string['warning:folderreaders'] = '{$a->source}: {$a->count} utente/i senza la capability local/csv_user_sync:uploadsource possono leggere i file CSV di questa cartella (dati personali): {$a->users}. Limitare l\'accesso alla cartella o al corso sorgente.';
$string['warning:invalidfieldvalue'] = 'Valore "{$a->value}" non valido per il campo "{$a->field}": campo non modificato.';
$string['warning:invalidprofilevalue'] = 'Valore "{$a->value}" non valido per il campo profilo personalizzato "{$a->field}" (ad esempio non è un\'opzione del menu o è troppo lungo): campo non modificato.';
$string['warning:profilefieldambiguous'] = 'Esistono campi profilo personalizzati "{$a}" con shortname che differiscono solo per maiuscole/minuscole: vengono ignorati dalla sincronizzazione.';
$string['warning:profilevaluerejected'] = 'Valore per il campo profilo personalizzato "{$a->field}" rifiutato: {$a->message}';
$string['warning:userdeletechanged'] = 'Account "{$a}" non eliminato: username o email sono cambiati durante l\'esecuzione.';
$string['warning:userdeletenomatch'] = 'Nessun account esistente corrisponde sia allo username "{$a}" sia all\'email della riga: niente da eliminare.';
$string['warning:userdeletepending'] = 'Riga saltata: per l\'account "{$a}" è richiesta l\'eliminazione in questa esecuzione.';
$string['warning:usersuspenddisabled'] = 'Colonna user_suspend ignorata: la sospensione degli account è disattivata nelle impostazioni del plugin.';
