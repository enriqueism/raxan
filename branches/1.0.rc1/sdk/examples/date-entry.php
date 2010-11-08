<?php

require_once "../raxan/pdi/autostart.php";  

// set timezone - needed when using E_STRICT
Raxan::config('site.timezone','America/Jamaica');

class DateEntry extends RaxanWebPage {

    protected function _config() {
        $this->preserveFormContent = true;
    }

    protected function buttonClick($e){
        $f = $this->post->textVal('format');
        $dt = $this->post->dateVal('date',$f);
        if(!$dt) $dt = 'Invalid date';
        $this->msg->text($dt);
    }

}

?>

<!DOCTYPE html>

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Date Entry</title>
    <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
    <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
</head>

<body>
    <h2>Date Entry</h2>
    <form method="post" class="accessible">
        <div class="ctrl-group">
            <label>Enter your Date of Birth:</label><br />
            <input name="date" />
        </div>
        <label>Select date display format:</label><br />
        <select id="format" name="format">
            <option value="iso">iso</option>
            <option value="mysql">mysql</option>
            <option value="mssql">mssql</option>
            <option value="long">long</option>
            <option value="short">short</option>
        </select>&nbsp;
        <input type="submit" id="btnSend" value="Submit Date" xt-bind="click,buttonClick" />
        <h3 id="msg" class="prepend-top"></h3>
    </form>
</body>

</html>