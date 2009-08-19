<?php
/**
 * General locale settings - Spanish
 * @package Raxan
 */

// site & language info
$locale['php.locale']           = 'es_ES';  // see setlocale()
$locale['lang.dir']             = 'ltr';
$locale['site.title']           = 'Mi Sitio Web';

// date & time (strtime format)
$locale['date.short']           = 'd/m/Y';
$locale['date.long']            = 'l, d \d\e F \d\e Y';
$locale['date.time']            = 'h:n AM';

// numbers & currency
$locale['decimal.separator']    = ',';
$locale['thousand.separator']   = ' ';
$locale['currency.symbol']      = '$';
$locale['currency.location']    = 'lt';     // lt - left, rt - right
$locale['money.format']         = '';       // overrides above currency settings. See money_format()

$locale['days.short']           = array('Domingo','Lunes','Martes','Miércoles','Jue','Viernes','Sábado');
$locale['days.full']            = array('Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado');
$locale['months.short']         = array('Enero','Febrero','Mar','Abril','Mayo','Junio','Julio','Agosto','Sep','Octubre','Noviembre','Diciembre');
$locale['months.full']          = array('Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre');

// error messages
$locale['unauth_access']        = 'El acceso no autorizado';
$locale['file_notfound']        = 'No se encuentra el archivo';

// commonly used words
$locale['error']                = 'Error';
$locale['yes']                  = 'Sí';
$locale['no']                   = 'No';
$locale['cancel']               = 'Cancelar';
$locale['save']                 = 'Guardar';
$locale['send']                 = 'Enviar';
$locale['submit']               = 'Enviar';
$locale['delete']               = 'Eliminar';
$locale['close']                = 'Cerrar';
$locale['next']                 = 'Siguiente';
$locale['prev']                 = 'Anterior';
$locale['page']                 = 'Página';
$locale['click']                = 'Haga clic en';
$locale['sort']                 = 'Ordenar';
$locale['drag']                 = 'Arrastre';
$locale['help']                 = 'Ayuda';
$locale['first']                = 'Primero';
$locale['last']                 = 'Último';

?>