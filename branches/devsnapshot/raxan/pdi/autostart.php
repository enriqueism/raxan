<?php
/**
 * Raxan Autostart
 */

require_once dirname(__FILE__).'/gateway.php';

ob_start();
register_shutdown_function('raxan_auto_startup',getcwd());
/**
 * Automatically initializes a Raxan Web Page
 * This function will initialize either the last declared subclass of RaxanWebPage
 * or the first subclass with an $autostart property.
 *
 * Example:
 *<code>
 * class Page1 extends RaxanWebPage {    // this will be executed first because of $autostart.
 *     protected $autostart;
 *     protected function _load(){
 *          $this->content('Page1');
 *     }
 * }
 * class Page2 extends RaxanWebPage {    // this will be executed if $autostart was removed from Page1
 *     protected function _load(){
 *          $this->content('Page2');
 *     }
 * }
 * </code>
 */
function raxan_auto_startup($pth){

    // fix php CWD path bug when running under apache
    if ($pth!=getcwd()) chdir($pth);

    $i = 0; $ok = false; $page = 'RaxanWebPage';
    $autostart = Raxan::config('autostart');
    $src = trim(ob_get_clean());
    $class = 'RaxanWebPage';

    if ($autostart && is_subclass_of($autostart,$class)) $page = $autostart;
    else {
        // find page classes
        $cls = get_declared_classes();
        foreach ($cls as $cn) {
            if ($cn==$class) { $ok=true; continue; }
            if (!$ok) continue;
            if (is_subclass_of($cn, $class)) {  // only init classes that extends RaxanWebPage
                $page = $cn;
                $r = new ReflectionClass($cn);
                if ($r->hasProperty('autostart')) break;
            }
        }
    }

    raxan_auto_create($page,$src);

}

/**
 * Creates an instance of a page class
 * @param string $page The name of a RaxanWebPage sub-class
 * @param string $content
 */
function raxan_auto_create($page,$content = null) {
    // initialize page class
    if ($page) try {
        if (!$content) $content = '';
        $o = new $page($content); $o->reply();
    }catch(Exception $e) {
        // raise system error event
        $rt = Raxan::triggerSysEvent('system_error', $e);
        if (!$rt) {
            $err = $e->getMessage().'  Line '.$e->getLine().' in '.
                $e->getFile()."\n".$e->getTraceAsString();
            if (ini_get('display_errors')==1) echo "Uncaught Error: ".nl2br($err);
            Raxan::log($err, 'ERROR', 'Uncaught Error');
        }
    }
}

?>