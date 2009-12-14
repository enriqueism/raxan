
<?php
class ShowDate extends RaxanPlugin {
    public static $name = 'MyPlugin';
    public static $description = "Test plugin";
    public static $author = "Raymond";
    protected function methods() { return get_class_methods($this); }
    public static function register() { return self::instance(__CLASS__); }

    protected function page_prerender($e,$page) {
        $dt = '<hr /><div class="info c7 r3" align="center"><h2 class="bottom">'.
            date('h:i:s a').'</h2>'.
            date('d-M-Y').'</div>';
        $page->append($dt);
    }
}

ShowDate::register();

?>
