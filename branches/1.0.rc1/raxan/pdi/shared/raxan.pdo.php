<?php
/**
 * Raxan PHP Data Objects
 * Extends standard PDO class to provide additional functionality
 * @package Raxan
 */
class RaxanPDO extends PDO {

    /***
     * Retrieve records from a database table
     * @returns Array or false on error
     */
    public function table($name,$filterClause = null, $filterValues = null) {
        // get field names
        $fields = '*';
        if (($p = strpos($name,' '))!==false) {
            $fields = substr($name,$p);
            $name = substr($name,0,$p);
        }
        $sql = 'select '.$fields.' from '.$name;
        if ($filterClause==null) $ds = $this->query($sql);
        else {
            $sql.= ' where '.$filterClause;
            $ds = $this->prepare($sql);
            if ($filterValues!==null && !is_array($filterValues))  
                $filterValues = array_slice(func_get_args(),2); // use arguments as input
            $ds->execute($filterValues);
        }

        if ($ds===false) return false;
        else return $ds->fetchAll(PDO::FETCH_ASSOC);
    }

}

?>