<?php
/**
 * Converts CSV to array. Used internally by the Raxan class
 * Copyright (c) 2011 Raymond Irving (http://raxanpdi.com)
 *
 */
function raxan_csv_to_array($csv, $delimiter = ',', $enclosure = '"', $escape = '\\', $terminator = "\n") {
    $r = array();
    $rows = explode($terminator,trim($csv));
    $names = array_shift($rows);
    $names = str_getcsv($names,$delimiter,$enclosure,$escape);
    $nc = count($names);
    foreach ($rows as $row) {
        if (trim($row)) {
            $values = str_getcsv($row,$delimiter,$enclosure,$escape);
            if (!$values) $values = array_fill(0,$nc,null);
            $r[] = array_combine($names,$values);
        }
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