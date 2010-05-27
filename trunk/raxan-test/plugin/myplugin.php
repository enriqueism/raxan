<?php

class NewPlugin extends RaxanPlugin {
    public static $name = 'New Plugin';
    public static $description = "Plugin descrtion goes here";
    public static $author = "Author's Name";
    protected function methods() {
        return get_class_methods($this);
    }
    public static function register() {
        return self::instance(__CLASS__);
    }

    protected function page_load($e,$page) {
        ;
    }
}
NewPlugin::register();


?>