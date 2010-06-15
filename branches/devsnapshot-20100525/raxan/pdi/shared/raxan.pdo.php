<?php
/**
 * Raxan PHP Data Objects
 * Extends standard PDO class to provide additional functionality
 * @package Raxan
 */
class RaxanPDO extends PDO {

    protected $_lastRowsAffected = 0;
    protected static $_BadFieldNameChars = array('\\','"',"'"," ",";","\r","\n","\x00","\x1a");


    /**
     * Last number of rows affected
     * @return int
     */
    public function getLastRowsAffected() {
        return $this->_lastRowsAffected;
    }

    /**
     * Retrieve records from a database table
     * @example <p>$pdo->table('Customer','f_name=? and l_name=?',array($fname,$lname));</p>
     *          <p>$pdo->table('OrderItem','order_id=? and item_id=?',$ordid,$itmid);</p>
     *          <p>$pdo->table('Order field1, field2, fieldN','field1 = ?',$id);</p>
     * @param string $name Name of table. A comma separated list of field names can be retrieved from the table. Example: Customer fname, lname, address
     * @param string $filterClause Optional SQL where clause. Supports ? and :name parameters
     * @param array $filterValues Optional parameter values
     * @returns mixed Array or false on error
     */
    public function table($name,$filterClause = null, $filterValues = null) {
        // get field names
        $fields = '*'; $name = trim($name);
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

    public function tableInsert($name,$data) {
        if (!$data) return 0;
        else if(!is_array($data)) $data = (array)$data;

        $keys = array_keys($data);
        $keyCnt = count($keys);
        if ($keyCnt==0) return 0;
        $values = array_values($data);
        foreach($keys as $i=>$k)
            $key[$i] = str_replace(self::$_BadFieldNameChars,'',$k);
        $marks = trim(str_repeat(',?',$keyCnt),',');

        $sql = 'insert into '.$name.' ( '.implode(',',$keys).' ) values ( '.$marks.' )';
        $ds = $this->prepare($sql);
        $rt = $ds->execute($values);
        $this->_lastRowsAffected = $ds->rowCount();
        return $rt;
    }

    public function tableUpdate($name,$data,$filterClause = null,$filterValues = null) {
        if (!$data) return 0;
        else if(!is_array($data)) $data = (array)$data;

        $prefix = ':fld'.rand(1,20);
        $keys = ''; $values = array();
        foreach($data as $k=>$v) {
            $key = trim(str_replace(self::$_BadFieldNameChars,'',$k)); // clean field names
            $keys.= $key.'='.$prefix.$key.',';
            $values[$prefix.$key] = $v;
        }
        
        $sql = 'update '.$name.' set ' .trim($keys,',');
        if ($filterClause===null) $filterValues = $values;
        else {
            if ($filterValues!==null && !is_array($filterValues))
                $filterValues = array_slice(func_get_args(),3); // use arguments as input
            if (isset($filterValues[0])) {
                $split = explode('?',$filterClause);
                $filterClause = '';
                foreach($split as $i=>$p) {
                    if ($p) {
                        $filterValues[':p'.$i] = $filterValues[$i];
                        $filterClause.= $p.':p'.$i;
                    }
                }
            }
            $filterValues = array_merge($filterValues,$values);
            $sql.= ' where '.$filterClause;
        }
        $ds = $this->prepare($sql);
        $rt = $ds->execute($filterValues);
        $this->_lastRowsAffected = $ds->rowCount();
        return $rt;
    }

    public function tableDelete($name,$filterClause = null,$filterValues = null) {
        $sql = 'delete from '.$name;
        if ($filterClause!==null) {
            $sql.= ' where '.$filterClause;
            if ($filterValues!==null && !is_array($filterValues))
                $filterValues = array_slice(func_get_args(),2); // use arguments as input
        }
        $ds = $this->prepare($sql);
        $rt = $ds->execute($filterValues);
        $this->_lastRowsAffected = $ds->rowCount();
        return $rt;
    }

}

?>