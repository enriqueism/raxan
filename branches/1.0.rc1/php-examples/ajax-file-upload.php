<?php require_once "../raxan/pdi/autostart.php"; ?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
    <title>Ajax File Upload</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link href="../raxan/styles/master.css" type="text/css" rel="stylesheet" />
    <style type="text/css">
        label {display:block; float:left; width:125px;margin-bottom:15px; font-weight:bold}
    </style>
</head>
<body>
  <div>
    <form id ="webform" enctype="multipart/form-data" method="POST">
        <h3>Photo upload</h3>
        <p>Set either height or width to preserve aspect ratio</p>
        <label>Width:</label><input type="text" name="width" size="3" /><br />
        <label>Height:</label><input type="text" name="height" size="3" /><br />
        <label>Photo File:</label><input name="userfile" type="file" />&nbsp;
        <input id="btn" type="submit" value="Upload File" xt-bind="#click,upload" /><br />
        <hr />
        <div id="output" xt-autoupdate></div>
    </form>
  </div>
</body>
</html>

<?php

class AjaxUploadPage extends RaxanWebPage {
    protected $degradable = true;
    protected $preserveFormContent = true;

    public function upload($e){
        $post = $this->sanitizePostBack();
        $w = $post->integer('width');
        $h = $post->integer('height');

        $img  ='<img src="views/images/sample.jpg?'.time().'" />';

        // resample uploaded image
        $ok = $post->fileImageResample('userfile',$w,$h,'jpeg');
        if (!$ok) $txt = 'Unable to resmaple the uploaded image';
        else {
            $post->fileMove('userfile',dirname(__FILE__).'/views/images/sample.jpg');
            $txt ='<img src="views/images/sample.jpg?'.time().'" />';
        }
        $this->output->html($txt);

    }
}


?>
