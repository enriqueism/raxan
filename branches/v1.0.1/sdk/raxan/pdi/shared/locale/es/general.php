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

$locale['days.short']           = array('Dom','Lun','Mar','Mié','Jue','Vie','Sáb');
$locale['days.full']            = array('Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado');
$locale['months.short']         = array('Enero','Feb','Marzo','Abr','Mayo','Jun','Jul','Agosto','Sept','Oct','Nov','Dic');
$locale['months.full']          = array('Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre');

// error messages
$locale['unauth_access']        = 'El acceso no autorizado';
$locale['file_notfound']        = 'No se encuentra el archivo';
$locale['view_not_found']       = 'Vista de página o archivo no encontrado';

// client-side error message
$locale['pdi-ajax-err-msg']     = "Error al conectarse al servidor. Por favor, inténtelo de nuevo o informar de ello al administrador. Ver la Consola de errores para obtener más información.";

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

// validation messages
$locale['valueMissing'] = 'Valor que falta';
$locale['patternMismatch'] = 'Patrón no coincidentes. El valor no coincide con la sintaxis necesaria';
$locale['tooLong'] = 'El valor introducido excede la longitud permitida la entrada';
$locale['typeMismatch'] = 'Tipo no coincidente. El valor indicado no está en la sintaxis correcta';
$locale['rangeUnderflow'] = 'El valor introducido es inferior al valor mínimo permitido';
$locale['rangeOverflow'] = 'El valor introducido es mayor que el valor máximo permitido';
$locale['customError'] = 'El valor indicado no es válido'; // default custom message

?>