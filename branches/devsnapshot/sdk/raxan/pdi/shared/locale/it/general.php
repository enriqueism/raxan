<?php
/**
* General locale settings - Italian
* @package Raxan
*/

// site & language info
$locale['php.locale']           = 'it';  // see setlocale()
$locale['lang.dir']             = 'ltr';
$locale['site.title']           = 'Il mio sito';

// date & time (strtime format)
$locale['date.short']           = 'd/m/Y';
$locale['date.long']            = 'l j F Y';
$locale['date.time']            = 'H:i';

// numbers & currency
$locale['decimal.separator']    = ',';
$locale['thousand.separator']   = '.';
$locale['currency.symbol']      = '&euro;';
$locale['currency.location']    = 'lt';     // lt - left, rt - right
$locale['money.format']         = '';       // overrides above currency settings. See money_format()

$locale['days.short']           = array('Dom','Lun','Mar','Mer','Gio','Ven','Sab');
$locale['days.full']            = array('Domenica','Luned&igrave;','Marted&igrave;','Mercoled&igrave;','Gioved&igrave;','Venerd&igrave;','Sabato');
$locale['months.short']         = array('Gen','Feb','Mar','Apr','Mag','Giu','Lug','Ago','Set','Ott','Nov','Dic');
$locale['months.full']          = array('Gennaio','Febbraio','Marzo','Aprile','Maggio','Giugno','Luglio','Agosto','Settembre','Ottobre','Novembre','Dicembre');

// error messages
$locale['unauth_access']        = 'Accesso Non Autorizzato';
$locale['file_notfound']        = 'File Non Trovato';
$locale['view_not_found']       = 'Vista pagina o file non trovato';

// client-side error message
$locale['pdi-ajax-err-msg']     = "Errore durante la connessione al server. Riprovare o per segnalare il problema all'amministratore. Vedere la console degli errori per ulteriori informazioni.";

// commonly used words
$locale['error']                = 'Errore';
$locale['yes']                  = 'Si';
$locale['no']                   = 'No';
$locale['cancel']               = 'Annulla';
$locale['save']                 = 'Salva';
$locale['send']                 = 'Invia';
$locale['submit']               = 'Invia';
$locale['delete']               = 'Elimina';
$locale['close']                = 'Chiudi';
$locale['next']                 = 'Successiva';
$locale['prev']                 = 'Precedente';
$locale['page']                 = 'Pagina';
$locale['click']                = 'Clic';
$locale['sort']                 = 'Ordina';
$locale['drag']                 = 'Trascina';
$locale['help']                 = 'Aiuto';
$locale['first']                = 'Prima';
$locale['last']                 = 'Ultima';

// validation messages
$locale['valueMissing'] = 'Valore mancante';
$locale['patternMismatch'] = 'Pattern non corrispondenti. Il valore non coincide con la sintassi richiesta';
$locale['tooLong'] = 'Il valore immesso supera la lunghezza consentita di ingresso';
$locale['typeMismatch'] = 'Tipo non corrispondenti. Il valore immesso non è nella sintassi corretta';
$locale['rangeUnderflow'] = 'Il valore digitato è inferiore al valore minimo consentito';
$locale['rangeOverflow'] = 'Il valore immesso è maggiore del valore massimo consentito';
$locale['customError'] = 'Il valore inserito non è valido'; // default custom message

?>
