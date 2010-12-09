<?php

// setup PDO Database connector for employees database
$config['db.employees'] = array(
    'dsn'       => 'mysql: host=localhost; dbname=employees',
    'user'      => 'user',
    'password'  => 'password',
    'attribs'   => PDO::ERRMODE_EXCEPTION // use pdo exception mode
);

?>
