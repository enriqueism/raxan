<?php
/**
 * Converts CSV to array. For use with RichAPI
 *
 */
function csv_to_array($csv, $delimiter = ',', $enclosure = '"', $escape = '\\', $terminator = "\n") {
    $r = array();
    $rows = explode($terminator,trim($csv));
    $names = array_shift($rows);
    $names = str_getcsv($names,$delimiter,$enclosure,$escape);
    foreach ($rows as $row) {
        $values = str_getcsv($row,$delimiter,$enclosure,$escape);
        $r[] = array_combine($names,$values);
    }
    return $r;
}

// str_getcsv - based on code from daniel dot oconnor at gmail dot com
// Source: http://us2.php.net/manual/en/function.str-getcsv.php#88311
// @param $escape is not used
if (!function_exists('str_getcsv')) {
    function str_getcsv($input, $delimiter = ",", $enclosure = '"', $escape = "\\") {
        $f = fopen("php://memory", 'r+');
        fputs($f, $input); rewind($f);
        $data = fgetcsv($f, 0, $delimiter, $enclosure);
        fclose($f);
        return $data;
    }
}

?>