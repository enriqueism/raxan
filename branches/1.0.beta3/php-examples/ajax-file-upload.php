<?php


include_once "../raxan/pdi/gateway.php";

$page = new RichWebPage('views/upload.html');
$page->updateFields();

$page['#webform']->bind('#submit','form_upload');
function form_upload($e){
    $rq = $e->page()->clientRequest();
    $w = $rq->integer('width');
    $h = $rq->integer('height');

    $img  ='<img src="views/images/sample.jpg?'.time().'" />';

    // resample
    $ok = $rq->fileImageResample('userfile',$w,$h,'jpeg');
    if (!$ok) $txt = 'Unable to resmaple the uploaded image';
    else {
        $rq->fileMove('userfile',dirname(__FILE__).'/views/images/sample.jpg');
        $txt ='<img src="views/images/sample.jpg?'.time().'" />';
    }
    C('#output')->html($txt);

//     //get file info
//    $size =  $rq->fileSize('userfile');
//    C('#output')->html($size);

    // copy
//    $rq->fileMove('userfile','./views/images/sample.jpg');
//    C('#output')->html($img);

    // count
    //$d = $rq->fileOrigName('userfile');
    //C('#output')->html($d);

    //C('#output')->html(file_get_contents('script.html'));
    //C()->popup('','photo','width='.($w+20).',height='.($h+20).',scrollbars=yes')->append($txt);
    //C()->popup('','photo','width='.($w+20).',height='.($h+20).',scrollbars=yes')->html($txt);
    //C()->popup('views/images/sample.jpg?'.time(),'photo','width='.($w+20).',height='.($h+20).',scrollbars=yes');
    //C()->alert('Ajax Uploads!');

    //$img = $rq->fileImageSize('userfile');
    //C('#output')->html("Image:".print_r($img,true));

}


$page->reply();

?>
