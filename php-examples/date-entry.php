<?php require_once "../raxan/pdi/autostart.php";  ?>

<!DOCTYPE html>

<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <title>Date Entry</title>
</head>

<body>
    <h2>Date Entry</h2>
    <form method="post" class="accessible">
        Enter your Date of Birth:<br />
        <input name="date" />&nbsp;
        <select id="format" name="format">
            <option value="iso">iso</option>
            <option value="mysql">mysql</option>
            <option value="mssql">mssql</option>
            <option value="long">long</option>
            <option value="short">short</option>
        </select>
        <input type="submit" id="btnSend" value="Send" xt-bind="click,buttonClick" />
        <p id="msg"></p>
    </form>
</body>

</html>

<?php

// Set timezone - also needed when using E_STRICT
Raxan::config('site.timezone','America/Jamaica');

class DateEntry extends RaxanWebPage {

    protected $preserveFormContent = true;

    protected function buttonClick($e){
        $post = $this->sanitizePostBack();
        $f = $post->text('format');
        $dt = $post->date('date',$f);
        if(!$dt) $dt = 'Invalid date';
        $this->msg->text($dt);
    }

}

?>
