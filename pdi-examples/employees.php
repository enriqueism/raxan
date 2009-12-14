<?php

require_once '../raxan/pdi/gateway.php';

//RichAPI::config('debug',true);
RichAPI::config('site.timezone','America/Toronto');

class EmployeePage extends RichWebPage {

    protected $db;
    protected $page;

    protected function _init() {
        $this->source('views/employees.html');
        // reset page data on first load
        $this->resetDataOnFirstLoad = true;
        // connect to db
        $dsn = 'mysql: host=localhost; dbname=employees';
        $this->db = RichAPI::Connect($dsn,'dbuser','password');
        if (!$this->db){
            $this->halt('<h2>Unable to connect to the MySQL Database.</h2>
                Please make sure you have properly configured your MySQL database connection. <br />
                You can download the employee sample database from the MySQL website (<a href="http://dev.mysql.com/doc/">http://dev.mysql.com/doc/</a>)
            ');
        }
    }

    protected function _load() {
        // get current page
        $this->page = & $this->data('page') || $this->data('page',1);
        // bind events
        $this['#pager a']->delegate('click','.change_page');
        $this['#emplist tr']->delegate('click','.row_click');
    }

    protected function _prerender() {
        $this->loadEmployees();
    }
    
    protected function change_page($e){
        $this->page = (int)$e->value;
        if ($this->isCallback) $this->loadEmployees();
    }

    protected function row_click($e){
        $id = (int)$e->value;   // sanitize: convert to number
        if (!$e->ctrlKey && !$e->metaKey) $this->data('selected.empno',$id);
        else {  // multiple selection
            $oldid = & $this->data('selected.empno');
            if (!is_array($oldid)) $oldid = array($oldid);
            $oldid[] = $id;
        }
    }

    protected function loadEmployees() {
        $table = $this['#emplist tbody'];
        // setup templates
        $tpl = $table->html();
        $tplAlt = $table->find('tr')->addClass('even')->end()->html();
        $tplSelected = $table->find('tr')->attr('class','select')->end()->html();
        // load employees
        $rows = $this->getEmployees();
        $this['#emplist tbody']->bind($rows,array(
            'page' => $this->page,
            'pageSize' => 10,
            'tpl' => $tpl,
            'tplAlt' => $tplAlt,
            'tplSelected' => $tplSelected,
            'key'=>'emp_no',
            'selected' => $this->data('selected.empno'),
            'format' => array(
                'name'=>'capitalize',
                'birth_date'=>'date:d M, Y'
             )
        ));
        // add hover effect to table rows
        C('#emplist tbody tr')->hoverClass('hover');
    
        // setup pager
        $tpl = $this['#pager']->html();
        $maxpage = ceil(count($rows)/10); 
        P('#pager')->html('Page: '.RichAPI::paginate($maxpage,$this->page,array(
            'tpl' => $tpl,
            'tplFirst' => '<a href="#{FIRST}" title="First">First</a> .'.$tpl,
            'tplLast' => $tpl.' . <a href="#{LAST}" title="Last">Last</a>',
            'tplSelected' =>'<span class="lightgray hlf-pad">{VALUE}</span>', 'delimiter'=>'.',
        )));
    }

    protected function getEmployees() {
        $ds = $this->db->query("select *,concat(first_name,' ',last_name) as 'name' from employees order by emp_no");
        return $ds->fetchAll(PDO::FETCH_ASSOC);
    }
}

RichWebPage::Init('EmployeePage');

?>