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

$string['email:newuser_subject'] = '[{$a}] Accesso account Moodle';
$string['error:csvheaderduplicate'] = 'Intestazione CSV duplicata: {$a}.';
$string['error:csvheaderinvalid'] = 'Intestazione CSV non valida alla colonna {$a}.';
$string['error:csvheadernotfound'] = 'Intestazione CSV non trovata.';
$string['error:csvpathnotfound'] = 'Il percorso CSV non esiste: {$a}';
$string['error:csvpathnotreadable'] = 'Il percorso CSV non e leggibile: {$a}';
$string['error:csvpathrequired'] = 'Il percorso CSV e obbligatorio.';
$string['error:csvsourceconflict'] = 'Errore configurazione: imposta solo "Percorso file CSV" oppure "Upload file CSV", non entrambi.';
$string['error:csvsourcemissing'] = 'Errore configurazione: imposta "Percorso file CSV" oppure carica un file CSV.';
$string['error:csvuploadnotfound'] = 'File CSV caricato non trovato nello storage Moodle. Carica di nuovo il file nelle impostazioni del plugin.';
$string['error:delimiterinvalid'] = 'Il delimitatore deve contenere esattamente un carattere.';
$string['error:emailsendfailed'] = 'Invio email accesso account fallito per utente "{$a}".';
$string['error:enrolfailed'] = 'Sincronizzazione iscrizione fallita: {$a}';
$string['error:enrolheaderspair'] = 'Le intestazioni iscrizione CSV devono includere sia "course_shortname" sia "role_shortname".';
$string['error:enrolmissingrole'] = 'Valore role_shortname mancante per l iscrizione.';
$string['error:forbiddenemail'] = 'Dominio email non consentito: {$a}';
$string['error:invalidauthfallback'] = 'Metodo auth "{$a->auth}" non valido. Fallback a "{$a->fallback}".';
$string['error:invaliddate'] = 'Formato data non valido per "{$a->field}" con valore "{$a->value}".';
$string['error:invaliddateorder'] = 'La data fine iscrizione non puo essere precedente alla data inizio.';
$string['error:invalidemail'] = 'Email non valida: {$a}';
$string['error:invalidflag'] = 'Valore non valido per "{$a->field}": "{$a->value}". Valori ammessi: 0 o 1.';
$string['error:invalidusername'] = 'Username non valido: {$a}';
$string['error:manualenrolmissing'] = 'Istanza iscrizione manuale mancante per il corso "{$a}".';
$string['error:manualenrolmissingglobal'] = 'Plugin di iscrizione manuale non disponibile.';
$string['error:missingrequiredheaders'] = 'Intestazioni CSV obbligatorie mancanti: {$a}';
$string['error:unknowncourse'] = 'Corso non trovato per shortname: {$a}';
$string['error:unknownrole'] = 'Ruolo non trovato per shortname: {$a}';
$string['error:usercreate'] = 'Creazione utente fallita: {$a}';
$string['error:usercreatedemailfailed'] = 'Utente "{$a->username}" creato, ma invio email credenziali fallito: {$a->message}';
$string['error:usermissingmandatoryfields'] = 'Campi utente obbligatori mancanti. Richiesti: username, firstname, lastname, email.';
$string['error:usermissingmandatoryfieldsrow'] = 'Valori utente obbligatori mancanti nella riga. Campi vuoti: {$a}.';
$string['error:userupdate'] = 'Aggiornamento utente fallito: {$a}';
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
$string['log:userunchanged'] = 'Utente "{$a}" non modificato.';
$string['log:userupdate_dryrun'] = 'Dry-run: l utente "{$a}" verrebbe aggiornato.';
$string['log:userupdated'] = 'Utente "{$a}" aggiornato.';
$string['pluginname'] = 'Sincronizzazione utenti CSV';

$string['privacy:metadata:core_files'] = 'Il plugin salva opzionalmente il file CSV sorgente nello storage file di Moodle quando viene caricato dalle impostazioni.';
$string['privacy:metadata:local_csv_user_sync_log'] = 'Memorizza i log di esecuzione della sincronizzazione CSV.';
$string['privacy:metadata:local_csv_user_sync_log:level'] = 'Livello severita del log.';
$string['privacy:metadata:local_csv_user_sync_log:message'] = 'Testo del messaggio di log.';
$string['privacy:metadata:local_csv_user_sync_log:rownum'] = 'Numero riga CSV collegato al log.';
$string['privacy:metadata:local_csv_user_sync_log:runid'] = 'Identificativo della run di sincronizzazione.';
$string['privacy:metadata:local_csv_user_sync_log:timecreated'] = 'Timestamp di creazione del log.';
$string['privacy:metadata:local_csv_user_sync_log:userid'] = 'ID utente referenziato dal log.';
$string['privacy:metadata:local_csv_user_sync_log:username'] = 'Username referenziato dal log.';
$string['settings:authsection'] = 'Autenticazione';
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
$string['settings:detailedlog_desc'] = 'Se abilitato, include informazioni di debug in output task e tabella log del plugin.';
$string['settings:dryrun'] = 'Modalita dry-run';
$string['settings:dryrun_desc'] = 'Se abilitato, Moodle non salva dati. Le operazioni vengono solo simulate e loggate.';
$string['settings:emailsection'] = 'Email nuovi utenti';
$string['settings:emailtemplate'] = 'Template email';
$string['settings:emailtemplate_default'] = "Ciao {{firstname}},\n\nsu {{sitename}} e stato creato un account per te.\n\nUsername: {{username}}\nURL imposta password: {{setpasswordurl}}\nURL login: {{loginurl}}\n\nPer motivi di sicurezza, questo link e monouso e scade automaticamente.";
$string['settings:emailtemplate_desc'] = 'Placeholder disponibili: {{firstname}}, {{lastname}}, {{username}}, {{setpasswordurl}}, {{sitename}}, {{loginurl}}. Il vecchio placeholder {{password}} e supportato come alias di {{setpasswordurl}}.';
$string['settings:encoding'] = 'Encoding file';
$string['settings:encoding:auto'] = 'Rilevamento automatico encoding';
$string['settings:encoding_desc'] = 'Encoding usato dal file CSV.';
$string['settings:filesection'] = 'File CSV';
$string['settings:schedulehint'] = 'Frequenza task';
$string['settings:schedulehint_desc'] = 'La frequenza si configura in Scheduled tasks: {$a}';
$string['settings:sendemail'] = 'Invia email credenziali';
$string['settings:sendemail_desc'] = 'Invia username e link monouso per impostare la password solo agli utenti appena creati.';
$string['settings:syncsection'] = 'Comportamento sync';
$string['settings:templatedesc'] = 'Scarica un file CSV di esempio con tutti i campi supportati (inclusi i campi profilo personalizzati): {$a}';
$string['settings:templatedownload'] = 'Scarica modello CSV';
$string['settings:templateheading'] = 'Modello CSV';
$string['settings:updateonlychanged'] = 'Aggiorna solo se cambiato';
$string['settings:updateonlychanged_desc'] = 'Se abilitato, l utente viene aggiornato solo quando almeno un valore e cambiato.';
$string['task:dryrunskipenrol'] = 'Dry-run: iscrizione saltata per il nuovo utente "{$a}" perche l utente non esiste ancora.';
$string['task:fatal'] = 'Sincronizzazione interrotta per errore inatteso: {$a}';
$string['task:lockfailed'] = 'Task saltato perche e gia in esecuzione un altra sincronizzazione.';
$string['task:releaselockfailed'] = 'Impossibile rilasciare il lock del task: {$a}';
$string['task:rowfatal'] = 'Riga {$a->row} saltata per errore inatteso: {$a->message}';
$string['task:start'] = 'Sincronizzazione avviata. Dry-run: {$a}.';
$string['task:summary'] = 'Righe elaborate: {$a->rows}. Utenti creati: {$a->userscreated}. Utenti aggiornati: {$a->usersupdated}. Iscrizioni create: {$a->enrolmentscreated}. Iscrizioni aggiornate: {$a->enrolmentsupdated}. Errori: {$a->errors}.';
$string['task:syncusers'] = 'Sincronizza utenti da CSV';
$string['task:unknownerror'] = 'Errore sconosciuto';
$string['template:commentrow'] = 'Riga istruzioni: rimuovere questa riga prima dell importazione dati.';
$string['template:coursehint'] = 'Shortname corso';
$string['template:datehintoptional'] = 'Data opzionale: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY o timestamp UNIX';
$string['template:datehintrequired'] = 'Data obbligatoria: YYYY-MM-DD, DD.MM.YYYY, DD/MM/YYYY o timestamp UNIX';
$string['template:deletedhint'] = 'Elimina iscrizione: 0 = no, 1 = si';
$string['template:rolehint'] = 'Shortname ruolo (esempio: student)';
$string['template:suspendedhint'] = 'Sospendi iscrizione: 0 = no, 1 = si';
