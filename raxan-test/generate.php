<?php require_once '../raxan/pdi/autostart.php'; ?>

<?php 

class NewPage extends RaxanWebPage {

    protected function _load() {

        $cls = get_declared_classes();
        $tab = "    ";
        foreach($cls as $c) {
            if (strpos($c, 'Raxan')!==false) {
                $html = array();
                $html[] = 'class Test'.$c. ' extends UnitTest {';
                $methods = get_class_methods($c);
                sort($methods);
                foreach($methods as $m) {
                    if (strpos($m,'_')!==0)
                        $html[] = $tab."function test".ucfirst($m).
                            "() {\n".$tab.$tab."//Code here\n".$tab."}\n";
                }
                $html[] = "}\n";
                // save file
                $data = "<?php\n\n".implode("\n",$html)."\n\n?>";
                $file = 'tpl.'.strtolower($c).'.test.php';
                if (!file_exists($file)) file_put_contents($file, $data);
                $this->append('Generating '.$c.'...<br />');
            }
        }
    }

}

?>