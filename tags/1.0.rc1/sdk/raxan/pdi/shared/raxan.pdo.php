<?php
/**
 * Raxan PHP Data Objects
 * Extends standard PDO class to provide additional functionality
 * @package Raxan
 */

/**
 * Raxan PDO Store Procedure Response
 * @property-read int $returnValue Returned value from SP
 * @property-read array $parameters Input/Output Parameters
 * @property-read array $rowset Returns current rowset
 * @property-read array $nextRowset Returns the next available rowset from the query
 */
class RaxanPDOProcResult {

    protected $rs, $return, $stmt, $params;

    public function __construct($stmt,&$params,&$return) {
        $this->return = &$return;
        $this->params = &$params;
        $this->stmt = $stmt;
    }

    public function __destruct(){
        unset($this->params); 
        unset($this->stmt);
        unset($this->rs);
    }
    
    public function __get($name){
        switch ($name) {
            case 'returnValue' : return $this->return; break;
            case 'parameters' : return $this->params; break;
            case 'nextRowset' :
                $this->stmt->nextRowset();
                $this->rs = null;
            case 'rowset' :
                if(!isset($this->rs)) 
                    $this->rs = ($this->stmt->columnCount()) ? $this->stmt->fetchAll(PDO::FETCH_ASSOC) : null;
                return $this->rs;
                break;
        }
    }

}

/**
 * Raxan PDO Store Procedure Response
 */
class RaxanPDO extends PDO {

    protected $_lastRowsAffected = 0;
    protected static $_BadFieldNameChars = array('\\','"',"'",";","\r","\n","\x00","\x1a"); //@todo: need to fix this to allow space, ', " in field name as some dbs support it


    /**
     * Last number of rows affected
     * @return int
     */
    public function getLastRowsAffected() {
        return $this->_lastRowsAffected;
    }

    /**
     * Executes a query and returns a recordset
     * @example <p>$pdo->execQuery('select * from Customer where f_name=? and l_name=?',array($fname,$lname));</p>
     * @param string $query
     * @param mixed $param
     * @returns mixed Array or false on error
     */
    public function execQuery($query, $param = null) {
        if ($param == null && func_num_args ()==1) $ds = $this->query($query);
        else {
            $ds = $this->prepare($query);
            if (!is_array($param))
                $param = array_slice(func_get_args(),1); // use arguments as input
            $ds->execute($param);
        }

        if ($ds===false) return false;
        else return $ds->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Executes a stored procedure and returns a recordset
     * @param string $query
     * @param array $param
     * @return RaxanPDOProcResult
     */
    public function execProc($name, $params = null) {        
        $params = is_array($params) ? $params : array_slice(func_get_args(),1); // use arguments as input
        $cnt = count($params);

        $pl = $cnt ?  array_fill(0,$cnt,'?') : array();
        $query = '? = call '.$this->_cleanField($name).' ('.implode(',',$pl).')';
        $driver = strtolower($this->getAttribute(PDO::ATTR_DRIVER_NAME));
        if ($driver=='sqlsrv'||$driver=='mssql'||$driver=='odbc') $query ='{'.$query.'}';
        $ds = $this->prepare($query);

        $ds->bindParam(1, $return, PDO::PARAM_INT | PDO::PARAM_INPUT_OUTPUT,4);
        for ($i=0; $i<count($params); $i++) {
            if (!is_array($params[$i])) $ds->bindParam($i+2, $params[$i], PDO::PARAM_STR);
            else {
                $dt = $params[$i]; $params[$i] = $dt[0];
                $ds->bindParam($i+2, $params[$i], $dt[1], $dt[2]);
            }
        }
        $ds->execute();

        $rt = new RaxanPDOProcResult($ds, $params, $return);
        return $rt;
        
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
        $sql = 'select '.$fields.' from '.$this->_cleanField($name);
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

    /**
     * Inserts a record into a table
     * @param string $name Table name
     * @param array $data An associative array containing data for a list of fields to be inserted
     * @return boolean
     */
    public function tableInsert($name,$data) {
        if (!$data) return 0;
        else if(!is_array($data)) $data = (array)$data;

        $keys = array_keys($data);
        $keyCnt = count($keys);
        if ($keyCnt==0) return 0;
        $values = array_values($data);
        foreach($keys as $i=>$k)
            $key[$i] = $this->_cleanField($k);
        $marks = trim(str_repeat(',?',$keyCnt),',');

        $sql = 'insert into '.$this->_cleanField($name).' ( '.implode(',',$keys).' ) values ( '.$marks.' )';
        $ds = $this->prepare($sql);
        $rt = $ds->execute($values);
        $this->_lastRowsAffected = $ds->rowCount();
        return $rt;
    }

    /**
     * Updates a record within a table
     * @param string $name Table name
     * @param array $data An associative array containing data for a list of fields to be updated
     * @param string $filterClause Optional SQL where clause. Supports ? and :name parameters
     * @param array $filterValues Optional parameter values
     * @return boolean
     */
    public function tableUpdate($name,$data,$filterClause = null,$filterValues = null) {
        if (!$data) return 0;
        else if(!is_array($data)) $data = (array)$data;

        $prefix = ':fld'.rand(1,20);
        $keys = ''; $values = array();
        foreach($data as $k=>$v) {
            $key = trim($this->_cleanField($k)); // clean field names
            $keys.= $key.'='.$prefix.$key.',';
            $values[$prefix.$key] = $v;
        }
        
        $sql = 'update '.$this->_cleanField($name).' set ' .trim($keys,',');
        if ($filterClause===null) $filterValues = $values;
        else {
            if ($filterValues!==null && !is_array($filterValues))
                $filterValues = array_slice(func_get_args(),3); // use arguments as input
            if (isset($filterValues[0])) {
                $paramValues = $filterValues;
                $split = explode('?',$filterClause);
                $filterClause = ''; $filterValues = array();
                foreach($split as $i=>$p) {
                    if ($p) {
                        $filterValues[':p'.$i] = $paramValues[$i];
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

    /**
     * Deletes a record from a table
     * @param string $name Table name
     * @param string $filterClause Optional SQL where clause. Supports ? and :name parameters
     * @param array $filterValues Optional parameter values
     * @return boolean
     */
    public function tableDelete($name,$filterClause = null,$filterValues = null) {
        $sql = 'delete from '.$this->_cleanField($name);
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

    /*
     * Protected function
     * --------------------------------
     */
    
    protected function _cleanField($name){
        return str_replace(self::$_BadFieldNameChars,'',$name);
    }

}

?>