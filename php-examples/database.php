<?php require_once '../raxan/pdi/autostart.php';  ?>

<h2>Employees</h2>
<div id="employees" class="c20 prepend1">
    <div>{first_name} {last_name},  {birth_date}</div>
</div>

<?php

class NewPage extends RaxanWebPage {

    protected $masterTemplateFile = 'tpl.homemade.html';

    protected function _load() {

        try {
            $dns = 'mysql:host=localhost;dbname=employees';
            $db = Raxan::connect($dns,'dbuser','password',true);

            $rows = $db->table('employees','first_name like ?','k%');

            $this->employees->bind($rows);

        }
        catch(PDOException $e) {
            $this->halt($e);
        }
    }

}

?>