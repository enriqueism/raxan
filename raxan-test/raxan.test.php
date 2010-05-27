<?php

require_once 'unit.test.php';
require_once '../raxan/pdi/gateway.php';


/**
 * Raxan Class Test
 */
class TestRaxan extends UnitTest {

    protected $msgFromCallback;

    function _setup(){
        $this->name='Raxan Test Script';
        $this->description = 'Unit test script for the Raxan main class';
    }

    function testJSON() {
        $v = array(1,2,3);        
        $a = Raxan::JSON('encode', $v);   // encode
        $this->compare($a,'[1,2,3]','JSON encode');
        $a = Raxan::JSON('decode', '[1,2,3]'); // decode
        $this->compare($a,$v,'JSON decode');
    }

    function testBindSysEvent() {
        $this->title('System Bind and Trigger');
        $callback = array($this,'callback');
        Raxan::bindSysEvent('test_event',$callback);
        Raxan::triggerSysEvent('test_event');
        $this->compare($this->msgFromCallback,'done','Callback Object');
        Raxan::bindSysEvent('test_event2','regular_callback_function');
        Raxan::triggerSysEvent('test_event2');
        $this->compare($GLOBALS['msgFromCallback'],'done','Callback Function');
    }

    function testBindTemplate() {
        $tpl = '<div>{name}-{id}</div>';
        $tplA = '<div>ALT:{name}-{id}</div>';
        $data = $this->getTemplateData();
        // basic
        $rt = Raxan::bindTemplate($data,$tpl);
        $this->compare(htmlspecialchars($rt),htmlspecialchars('<div>mary-1</div><div>john-1</div>'),'Basic Template binding');
        $rt = Raxan::bindTemplate(array('a','b'),'<div>{VALUE}-{INDEX}</div>');
        $this->compare(htmlspecialchars($rt),htmlspecialchars('<div>a-0</div><div>b-1</div>'),'Basic Index Template binding');
        // basic alternate
        $rt = Raxan::bindTemplate($data,array($tpl,$tplA));
        $this->compare(htmlspecialchars($rt),htmlspecialchars('<div>mary-1</div><div>ALT:john-1</div>'),'Alternate Template binding');
        // tplAlt option
        $rt = Raxan::bindTemplate($data,array(
            'tpl'=>$tpl,
            'tplAlt'=>$tplA
         ));
        $this->compare(htmlspecialchars($rt),htmlspecialchars('<div>mary-1</div><div>ALT:john-1</div>'),' Template with tplAlt option');
        // tplFirst option
        $rt = Raxan::bindTemplate($data,array(
            'tpl'=>$tpl,
            'tplFirst'=>'<div>FIRST:{name}-{id}</div>'
        ));
        $this->compare(htmlspecialchars($rt),htmlspecialchars('<div>FIRST:mary-1</div><div>john-1</div>'),'Template with tplFirst option');
        // tplLast option
        $rt = Raxan::bindTemplate($data,array(
            'tpl'=>$tpl,
            'tplLast'=>'<div>LAST:{name}-{id}</div>'
        ));
        $this->compare(htmlspecialchars($rt),htmlspecialchars('<div>mary-1</div><div>LAST:john-1</div>'),'Template with tplLast option');
    }

    function testCDate() {
        $dt = Raxan::cDate('1/1/2009');
        $this->ok($dt instanceof RaxanDateTime,'Instance of RaxanDateTime');
        $this->compare($dt->format('d/M/Y'),'01/Jan/2009','Valid Date format');
    }

    function testConfig() {
        $v = Raxan::config('property1');
        $this->ok($v==='','Read empty config proerty');
        Raxan::config('property1','value1');
        $v = Raxan::config('property1');
        $this->ok($v==='value1','Write/Read config proerty');
        $a = Raxan::config();
        $this->ok(is_array($a),'Read config array');
    }

    function testConnect() {
        $pth = dirname(__FILE__).'/';
        $this->ok(file_put_contents($pth.'sample.db',' '),'Can write to sample.db');
        $dsn = 'sqlite:'.$pth.'sample.db';
        $pdo = Raxan::connect($dsn);
        $this->ok($pdo);
        $this->ok($pdo instanceof RaxanPDO,'Instance of RaxanPDO');
        Raxan::config('db.sample', array('dsn'=>$dsn));
        $pdo = Raxan::connect('sample');  // get name from config file
        $this->ok($pdo,'Connect using config option db.sample');
    }

    function testCurrentURL() {
        $url = Raxan::currentURL();
        $q = isset($_SERVER['QUERY_STRING']) ? $_SERVER['QUERY_STRING'] : '';
        $url2 = $_SERVER['PHP_SELF'].($q ? '?'.$q : '');
        $this->compare($url,$url2,'Current URL');
    }

    function testData() {
        $v = Raxan::data('id1','fieldname','12345');
        $this->compare($v,'12345','Set and read returned data value');
        $v = Raxan::data('id1','fieldname');
        $this->compare($v,'12345','Read data value');
        Raxan::data('id1','fieldname2','123'); // set first value
        $v = Raxan::data('id1','fieldname2','1234',true); // should return the previous vlaue
        $this->compare($v,'123','Set data value if not set');
        $v = & Raxan::data('id1','fieldname3',10);
        $v++;
        $this->compare($v,11,'Read and update value by reference');
    }

    function testDataSanitizer() {
        $html = '<b>string</b>';
        $d = Raxan::dataSanitizer();
        $this->ok($d instanceof RaxanDataSanitizer,'Instance of DataSanitzer');
        $d = Raxan::dataSanitizer(array(
            'int' => 1234,
            'text' => 'string',
            'html' => $html
        ));
        $this->compare($d->integer('int'),1234,'Read data from sanitizer');
        $this->compare($d->text('text'),'string','Read text data from sanitizer');
        $this->compare($d->text('html'),'string','Read (sanitzed)html data from sanitizer');
        $this->compare($d->value('html'),$html,'Read (raw) data value from sanitizer');
    }

    function testDataStorage() {
        Raxan::removeData('id1');
        session_destroy();
        $this->title('Data Storage');
        $sessid = '1234567';
        $store = new RaxanSessionStorage($sessid);
        $store1 = Raxan::dataStorage($store);
        $this->compare($store,$store1 ,'Set Data Storage');
        $id = Raxan::dataStorageId();
        $this->ok($id == $sessid,'Data Storage Id');
    }

    function testDataStorageId() {
        //Code here
    }

    function testDebug() {
        $var1 = "Variable 1";
        $var2 = "Variable 2";
        Raxan::config('debug',true);
        Raxan::config('debug.output','embedded'); // for embedded/popup html output
        Raxan::debug($var1);
        Raxan::debug($var2);
        $out = htmlspecialchars(Raxan::debugOutut());
        $this->compare($var1.'&lt;hr /&gt;'.$var2,$out,'Embedded/Popup Debug output');
        Raxan::config('debug.output','console'); // for console/alert non-html output
        $out = Raxan::debugOutut();
        $this->compare($var1."\n".$var2,$out,'Console/Alert Debug output');
    }

	function testDebugOutut() {
        //Code here
	}

    function testEscapeText() {
        $txt = "Hello\nWorld";
        $txt2 = "Hello\\nWorld";
        $txt1 = Raxan::escapeText($txt);
        $this->compare($txt1,$txt2,'Convert php string to JS string');
    }

    function testFlash() {
        //Code here
    }

    function testGetSharedSanitizer() {
        //Code here
    }

    function testImageResample() {
        $file = 'image.png';
        // missing file or error
        $ok = Raxan::imageResample($file.'.misssing', 48, 48);
        $this->ok($ok==false, 'File not found');
        // resize image
        $ok = Raxan::imageResample($file, 128, 128);
        $this->ok($ok, 'Image file resized to 128x128');
        // change image type
        $ok = Raxan::imageResample($file, 128, 128,'jpg');
        $this->ok($ok, 'Image file type changed to JPEG');
    }

    function testImageSize() {
        $file = 'image.png';
        $dim = array('width'=>64,'height'=>64,'type'=>2);
        $ok = Raxan::imageResample($file, 64, 64);
        $size = Raxan::imageSize($file);
        $this->compare($size,$dim, 'Image file resized to 64x64');
    }

    function testImportCSV() {
        $file = 'data.csv';
        $row = array(
            array(
                'column1'=>'value1 row1',
                'column2'=>'value2\\" row1'
            )
        );
        $csv = Raxan::importCSV($file);
        $this->compare($csv,$row,'Import CSV Data');
        $csv = Raxan::importCSV($file,',','"','\\',"\n");
        $this->compare($csv,$row,'Import CSV Data with parameters');
    }

    function testInitJSON() {
        //Code here
    }

    function testLoadConfig() {
        Raxan::loadConfig('myconfig.php');
        $v = Raxan::config('custom.key');
        $this->compare('value1',$v,'Load custom config value');
    }

    function testLoadLangFile() {
        $file = 'lang';
        $oldlocale = Raxan::config('site.locale');
        $oldLocalePth = Raxan::config('locale.path');
        Raxan::config('locale.path',dirname(__FILE__).'/lang/');
        Raxan::setLocale('en');
        $ok = Raxan::loadLangFile($file);
        $this->ok($ok,'Language file loaded');
        $title = Raxan::locale('mytitle');
        $this->compare($title,'My Title','Get value from language file');
        Raxan::config('locale.path',$oldLocalePth);
        Raxan::setLocale($oldlocale);
    }

    function testLoadPlugin() {
        $file = 'plugin/myplugin.php';
        $ok = Raxan::loadPlugin($file,true);
        $this->ok($ok,'Load plugin file');
        // plugins are self-registered
    }

    function testLoadUI() {
        //Code here
    }

    function testLocale() {
        $en = Raxan::locale();
        $this->compare($en,'en','Language code is english');
    }

    function testLog() {
        $msg = 'Raxan Class Test';
        $t = date('Y-m-d H:i:s',time());
        $file = dirname(__FILE__).'/logfile.txt';
        file_put_contents($file, '');
        Raxan::config('log.enable',true);
        Raxan::config('log.file',$file);
        // log
        Raxan::log($msg);
        $c = file_get_contents($file);
        $this->compare("INFO \t".$t." \t \t".$msg, trim($c),'Log file content');
        // log with params
        file_put_contents($file, '');
        Raxan::log($msg,'ERROR','Label');
        $t = date('Y-m-d H:i:s',time());
        $c = file_get_contents($file);
        $this->compare("ERROR \t".$t." \t [Label] \t".$msg, trim($c),'Log with label params');
    }

    function testMapSitePathToUrl() {
        //Code here
    }

    function testPaginate() {
        $output1 = '<a href="#1">1</a>&nbsp;<a href="#2">2</a>&nbsp;';
        $output2 = '<span>1&nbsp;</span><a href="#2">2</a>&nbsp;';
        $output3 = '<span>1&nbsp;</span>&nbsp;<a href="#2">2</a>&nbsp;&nbsp;...&nbsp;<a href="#4">4</a>&nbsp;';
        $output4 = '<span>1&nbsp;</span>&nbsp;...&nbsp;<a href="#3">3</a>&nbsp;&nbsp;<a href="#4">4</a>&nbsp;';
        $pg = Raxan::paginate(2,3);
        $this->compare($output1,$pg,'Simple paginator');
        $pg = Raxan::paginate(2, 1);
        $this->compare($output2,$pg,'Page 1 selected');
        // paginator with truncate options
        $pg = Raxan::paginate(4, 1,array('truncate'=>-1.2,'delimiter'=>'&nbsp;'));
        $this->compare($output3,$pg,'Paginator with truncate (-1.2) and delimiter options');
        // paginator with truncate options
        $pg = Raxan::paginate(4, 1,array('truncate'=>1.1,'delimiter'=>'&nbsp;'));
        $this->compare($output4,$pg,'Paginator with truncate (1.1) and delimiter options');
    }

    function testRemoveData() {
        Raxan::data('id2','data1','value1');
        $v1 = Raxan::data('id2','data1');
        Raxan::removeData('id2','data1');
        $v2 = Raxan::data('id2','data1');
        $this->ok($v1!==null,'Data added');
        $this->ok($v2===null,'Remove data');
    }

    function testSendError() {
        $url = 'http://'.$_SERVER['SERVER_NAME'].dirname($_SERVER['PHP_SELF']).'/';
        $errCodes = array(400,401,403,404);
        foreach ($errCodes as $code) {
            try { $err  = ''; $c = file_get_contents($url.'send.error.'.$code.'.php'); }
            catch(Exception $e) { $err = $e->getMessage(); }
            $this->compare('HTTP/1.1 '.$code,substr(trim($err),-12),$code.' - HTTP Error');
        }        
    }

    function testSetBasePath() {
        $pth = dirname(__FILE__).'/';
        $oldbase = Raxan::config('base.path');
        Raxan::setBasePath($pth);
        $this->ok($pth==$pth,'Set new base path');
        Raxan::config('base.path',$oldbase);
    }

    function testSetLocale() {
        $ok = Raxan::setLocale('fr');
        $this->ok($ok,'Set new local');
        $v = Raxan::locale('yes');
        $this->compare($v, 'Oui','Change Locale to fr');
        Raxan::setLocale('en');
    }

    function testStartTimer() {
        $this->_varTimer = Raxan::startTimer();
        $this->ok($this->_varTimer,'Start Timer');
    }

    function testStopTimer() {
        sleep(1);
        $time = Raxan::stopTimer($this->_varTimer);
        $this->compare(round($time),1.0,'Stop Timer after 1 second');
    }

    function testThrowCallbackException() {
        try {
            // this should raise an error
            Raxan::throwCallbackException('back_callback'); 
            $ok = true;
        }
        catch (Exception $e) {
            $ok = false;
        }
        $this->okFalse($ok,'Callback Error');
    }

    // testTriggerSysEvent - see testBindSysEvent
    
    function callback() {
        $this->msgFromCallback = 'done';
    }

    function getTemplateData() {
       return array(
           array('id'=>1,'name'=>'mary'),
           array('id'=>1,'name'=>'john')
       );
    }

    function testTriggerSysEvent() {
        //Code here
    }
    
}

function regular_callback_function() {
    $GLOBALS['msgFromCallback'] = 'done';
}



?>