<?php

require_once '../raxan/pdi/gateway.php';

RichAPI::config('site.timezone','America/Toronto');

class SearchBoxPage extends RichWebPage {

    protected $db;
    protected $infoTpl, $searchTpl;

    function _init() {
        $this->source('views/searchbox.html');

        // Modify this line to connect to your employees table.
        // For employee sample data visit http://dev.mysql.com/doc/
        $this->db = RichAPI::Connect('mysql: host=localhost; dbname=employees', 'dbuser', 'password');
        if (!$this->db){
            $this->halt('<h2>Unable to connect to the MySQL Database.</h2>
                Please make sure you have properly configured your MySQL database connection. <br />
                You can download the employee sample database from the MySQL website (<a href="http://dev.mysql.com/doc/">http://dev.mysql.com/doc/</a>)
            ');
        }
    }

    function _load() {

        // event to handle employee click
        $this['#results a']->delegate('#click','.employee_click');

        // event to handle auto search
        $this['#name']->bind('#keydown',array(
            'callback' => '.search_employee',
            'delay' => 600,
            'autoToggle' => 'img#pre',
            'serialize' => '#name',
            'inputCache' => true   
        ));                    


        // get templates from document
        $this->infoTpl = $this['#info']->html();
        $this->searchTpl = $this['#results']->html();

    }

    function employee_click($e) {
        $id = $e->value;
        $ds = $this->getEmployeeInfo($id);
        $html = RichAPI::bindTemplate($ds,array(
            'tpl'=>$this->infoTpl,
            'removeUnusedTags'=>true,
            'format'=>array(
                'gender'=>'capitalize',
                'hire_date'=>'date: M d, Y',
                'birth_date'=>'date: M d, Y'
            )
        ));
    
        C('#info')->hide()
            ->html($html)
            ->fadeIn();
    }

    function search_employee($e) {
        $html = '';
        $rq = $e->page()->clientRequest();
        $name = $rq->text('name');

        if ($name)  {
            $name = explode(' ',trim($name),2);
            $fname = $name[0];
            $lname = isset($name[1]) ? trim($name[1]) : '';
            $rows = $this->getEmployees($fname.'%', $lname.'%');
            $rows = RichAPI::bindTemplate($rows,$this->searchTpl);
            $name = $fname.' '.$lname;
            if ($rows) $html = '<span class="quiet">Top 10 results for '.$name.'<hr />'.$rows;
            else $html = '<span class="quiet">No results found for '.$name.'<hr />'.$rows;
        }

        C('#results')->html($html);
    }

    function getEmployees($fname,$lname) {
        $sql = "select emp_no,first_name,last_name                
                from employees where first_name like :fname ".
               (($fname && $lname) ? ' and ' : ' or ').
               ' last_name like :lname limit 10';
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':fname',$fname, PDO::PARAM_STR);
        $stmt->bindParam(':lname',$lname, PDO::PARAM_STR);
        $stmt->execute();
        return $stmt;
    }

    function getEmployeeInfo($id) {
        $sql = "select *,
                case gender when 'm' then 'male' when 'f' then 'female' else 'male' end as icon,
                case gender when 'm' then 'male' when 'f' then 'female' else 'male' end as gender
                from employees where emp_no=:id ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindParam(':id',$id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt;
    }

    function _prerender(){
        $this['#results']->html('');
        $this['#info']->html('');
    }
}

RichWebPage::Init('SearchBoxPage');


?>