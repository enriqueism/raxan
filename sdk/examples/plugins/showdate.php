
<?php
class ShowDate extends RaxanPlugin {
    public static $name = 'DateTimePlugin';
    public static $description = "Date/Time plugin";
    public static $author = "Raymond";
    protected function methods() { return get_class_methods($this); }
    public static function register() { return self::instance(__CLASS__); }

    // handler for page prerender
    protected function page_prerender($e,$page) {
        $dt = '<hr /><div class="rax-box info round rax-box-shadow" align="center">'.
            '<h2 class="bottom">'.
            date('h:i:s a').'</h2>'.
            date('d-M-Y').
        '</div>';
        $page->append($dt);
    }
}
ShowDate::register();

?>
