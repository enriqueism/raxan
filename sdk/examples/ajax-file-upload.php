<?php

require_once "../raxan/pdi/autostart.php"; 

class AjaxUploadPage extends RaxanWebPage {

    protected function _config() {
        $this->degradable = true;
        $this->preserveFormContent = true;
    }

    public function upload($e){
        
        // sanitize input values
        $w = $this->post->intVal('width');
        $h = $this->post->intVal('height');
        // check for file upload errors
        $err = $this->post->fileUploadError('file1');
        if ($err) {
            $errors = array(
                0=>"There is no error, the file uploaded with success",
                1=>"The uploaded file exceeds the upload_max_filesize directive in php.ini",
                2=>"The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form",
                3=>"The uploaded file was only partially uploaded",
                4=>"No file was uploaded",
                6=>"Missing a temporary folder"
            );
            $this->flashmsg($errors[$err],'fade','rax-box notice');
            return;
        }

        // resample uploaded image
        $ok = $this->post->fileImageResample('userfile',$w,$h,'jpeg');
        if (!$ok) $this->flashmsg('Unable to process image file.','fade','pad softred');
        else {
            try {
                $imgFile = dirname(__FILE__).'/data/sample.jpg'; // make sure file is writable
                $this->post->fileMove('userfile',$imgFile);
                $txt ='<img src="data/sample.jpg?'.time().'" />';
                $this->output1->html($txt);
            }catch (Exception $err) {
                $msg = 'Unable to process image file. Make sure data/sample.jpg is writable.';
                $this->flashmsg($msg,'fade','pad softred');
            }
        }
    }
}


?>

<!DOCTYPE html>
<html>
<head>
    <title>Ajax File Upload</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
    <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
    <style type="text/css">
        label { display:block; float:left; width:125px; margin-bottom:15px; font-weight:bold; }
    </style>
</head>

<body>
  <div class="container pad">
    <form id ="webform" enctype="multipart/form-data" method="POST">
        <div class="rax-backdrop c25" >
            <div class="pad white round">
                <h3>Ajax Photo Upload</h3>
                <p>Set either height or width to preserve aspect ratio</p>
                <label>Width:</label><input type="text" name="width" size="3" /><br />
                <label>Height:</label><input type="text" name="height" size="3" /><br />
                <label>Photo File:</label><input name="userfile" type="file" />&nbsp;
                <input id="btn" type="submit" value="Upload File" xt-bind="#click,upload" /><br />
                <hr />
                <div class="flashmsg" xt-autoupdate></div>
                <div id="output1" xt-autoupdate></div>           
            </div>
        </div>
    </form>
  </div>
</body>
</html>
