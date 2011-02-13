<?php defined('RAXANPDI')||exit(); ?>

<!DOCTYPE html>

<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
        <title>Notepad</title>
        <link href="../raxan/ui/css/master.css" type="text/css" rel="stylesheet" />
        <!--[if lt IE 8]><link rel="stylesheet" href="../raxan/ui/css/master.ie.css" type="text/css"><![endif]-->
        <link href="../raxan/ui/css/default/theme.css" type="text/css" rel="stylesheet" />
        <style type="text/css">
            #pageHeader {
                padding: 5px 5px 5px 10px;
                border-bottom-width: 2px;
            }
            #pageWrapper {
                border: solid 5px #555;
                width: 800px;
                margin-top: 20px
            }
        </style>
    </head>

    <body class="rax-background-pal">
        <div id="pageWrapper" class="rax-content-pal container round">
            <h1 id="pageHeader" class="rax-header-pal rax-glass"><img class="align-middle rtm" src="views/images/notepad.png" alt="Notepad" widt="44" height="55" />Notepad</h1>
            <div class="master-content prepend1 append1"></div>
            <hr class="space clear" />
        </div>
        <hr class="space clear" />
    </body>

</html>