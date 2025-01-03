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
 * Strings for the quizaccess_videocapture plugin.
 *
 * @package    quizaccess_videocapture
 * @copyright  2022 onwards Abaco Technology  {@link https://www.abacotechnology.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

$string['abacoprivacy'] = 'Durante il controllo di riconoscimento facciale, le seguenti operazioni sono eseguite dal plugin:<ul>
<li>Viene scattata una foto dell\'utente davanti allo schermo con la webcam del dispositivo;</li>
<li>La foto, insieme all\'immagine associata al profilo dell\'utente registrato, viene inviata all\'API Abaco Technology per essere verificata;</li>
<li>Nessun dato personale viene inviato con le due immagini;</li>
<li>L\'API Abaco Technology verifica se i volti nelle due immagini corrispondono, restituendo vero/falso;</li>
<li>Dopo aver eseguito il controllo, entrambe le immagini vengono eliminate;</li>
<li>Nessun dato personale viene salvato durante l\'intero processo.</li>
</ul>';
$string['abacoprivacyextlink'] = 'Abaco Technology privacy policy';
$string['abacoprivacytitle'] = 'Riconoscimento facciale e privacy';
$string['api_password'] = 'Biometrics API password';
$string['api_password_desc'] = 'Biometric API password';
$string['api_url'] = 'Biometric API url';
$string['api_url_desc'] = 'Biometrics API url';
$string['api_user'] = 'Biometrics API username';
$string['api_user_desc'] = 'Biometrics API username';
$string['bioerr000'] = 'Impossibile connettersi al systema di riconoscimento biometrico';
$string['bioerr001'] = 'Questo non è un documento di identità valido';
$string['bioerr002'] = 'Nell\'immagine ci deve essere una (e una sola) faccia';
$string['bioerr003'] = 'Impossibile rilevare una faccia nell\'immagine';
$string['biogenerr'] = 'Il file scelto non è valido';
$string['captured_pic'] = 'Nuova immagine del profilo';
$string['checkduringquiz'] = 'Attiva il riconoscimento durante il tentativo';
$string['checkinterval'] = 'Frequenza di controllo durante il tentativo (secondi)';
$string['continuetoquiz'] = 'Puoi ora accedere al quiz cliccando sul pulsante <em>Avvia il tentativo</em>.';
$string['facevideocaptureenabled'] = 'Riconoscimento facciale';
$string['facevideocaptureenabled_help'] = 'Se selezionato, verrà attivato il riconoscimento facciale dello studente.';
$string['loginfailed'] = 'Login fallito: l\'immagine catturata non corrisponde alla nuova immagine del profilo. Posizionati di fronte alla webcam e prova di nuovo.';
$string['maxfailedchecks'] = 'Numero massimo permesso di controlli falliti consecutivi drante il tentativo';
$string['noimage00optionshoot'] = 'Voglio scattare una foto ora con la fotocamera del pc.';
$string['noimage00optionupload'] = 'Voglio caricare un\'immagine esistente.';
$string['noimage00title'] = 'Preferisci caricare un\'mmagine esistente o scattare una foto ora con la fotocamera del pc?';
$string['noimage01title'] = 'Posizionati di fronte alla fotocamera e clicca sul pulsante <em>Scatta foto</em>.';
$string['noimage01uploadtitle'] = 'Carica una foto utilizzando il pulsante sottostante.';
$string['pictureacquisition'] = 'Acquisizione immagine del profilo';
$string['pluginname'] = 'Face recognition quiz access rule';
$string['privacy:metadata'] = 'Face recognition quiz access rule plugin does not store any personal data.';
$string['recogloader'] = 'Attendere prego, riconoscimento in corso ...';
$string['recogloaderaccessonly'] = 'Attendere, riconoscimento in corso ...';
$string['recogloadercheck'] = 'Attendere, il controllo dell\'immagine catturata è in corso ...';
$string['recogloadersave'] = 'Attendere, salvataggio dell\'immagine e accesso in corso ...';
$string['recogrequired'] = 'Questo quiz richiede il riconoscimento facciale dell\'utente. Clicca sul bottone sottostante per attivare la video camera e completare il processo.';
$string['recogrequirednoimage'] = 'Per accedere a questo quiz è necessario il riconoscimento facciale, ma nessuna immagine è ancora associata al tuo profilo.<br>Per avviare la procedura di acquisizione dell\'immagine, clicca sul pulsante <em>Avvia acquisizione</em>.';
$string['rek_api_error'] = 'Errore di connessione alle API di riconoscimento biometrico';
$string['rek_exit_quiz'] = 'Abbandona il quiz';
$string['rek_modal_captured_pic'] = 'Immagine catturata';
$string['rek_modal_message'] = 'L\'immagine catturata non corrisponde a quella del profilo. Posizionati di fronte alla telecamera e riprova cliccando sul pulsante <em>Avvia riconoscimento</em>.';
$string['rek_modal_message_attempt'] = 'L\'immagine catturata non corrisponde a quella del profilo. Posizionati di fronte alla telecamera e riprova cliccando sul pulsante <em>Prova di nuovo</em>.';
$string['rek_modal_message_locked'] = 'È stato raggiunto il numero massimo di riconoscimenti falliti. È necessario uscire dal quiz.';
$string['rek_modal_message_success'] = 'Sei stato riconosciuto in modo corretto. Puoi ora iniziare il tentativo utilizzando il pulsante qui sotto.';
$string['rek_modal_title'] = 'Riconoscimento utente';
$string['rek_modal_user_pic'] = 'Immagine del profilo';
$string['rek_try_again'] = 'Prova di nuovo';
$string['saveandlogin'] = 'Salva e accedi';
$string['saveandloginhelp'] = 'Ora puoi concludere la procedura cliccando il pulsante <em>Salva e accedi</em> oppure caricare un\'altra immagine cliccando su <em>Carica un\'altra immagine</em>.';
$string['saveandloginshoothelp'] = 'Ora puoi concludere la procedura cliccando il pulsante <em>Salva e accedi</em> oppure scattare un\'altra immagine cliccando su <em>Scatta foto</em>.';
$string['settingfromidonly'] = 'Permetti solo foto prese da documenti di indentità';
$string['settingrelay'] = 'Use relay';
$string['settingrelay_desc'] = 'Use relay';
$string['settingstrongdetection'] = 'Abilita il riconoscimento avanzato all\'accesso al quiz';
$string['startacquisition'] = 'Avvia acquisizione';
$string['startvideocap'] = 'Attiva il riconoscimento';
$string['takepicture'] = 'Scatta foto';
$string['uploadanotherpic'] = 'Carica un\'altra immagine.';
$string['videocaptureintro'] = 'In questo quiz è attivato il riconoscimento facciale. La faccia dello studente verrà confrontata con l\'immagine del profilo.';
