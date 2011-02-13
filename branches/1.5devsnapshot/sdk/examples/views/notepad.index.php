<?php defined('RAXANPDI') || exit(); ?>

<style type="text/css">
    table.list { margin-top:15px; }
    table.list h3 a {
        color: #004b7b
    }
</style>


<div class="pad">
    <div class="rax-inactive-pal rax-glass round pad border1 bmm">
        <span class="right"><a href="notepad.php?vu=form" class="button ok" >Add Note</a></span>
        <form id="searchfrm" name="searchfrm" action="" method="post">
            <div>
                <input type="text" name="querytxt" id="querytxt" value="" class="textbox" />&nbsp;
                <input type="submit" name="searchbtn" id="searchbtn" value="Search" class="button"/>&nbsp;
                <input type="button" name="btnClear" id="btnClear" value="Clear" class="button hide" title="Clear search" xt-bind="click" />
            </div>
        </form>
    </div>

    <div class="flashmsg" />

    <table class="rax-table rax-box-shadow list " >
        <thead>
            <tr class="tbl-header">
                <th class="tpb ltb bmb">Messages</th>
                <th class="tpb rtb bmb c4">Action</th>
            </tr>
        </thead>
        <tbody id="noteList" xt-delegate="a.delete click,deleteNote">
            <tr class="{ROWCLASS}">
                <td>
                    <div class="column last tpm"><img src="views/images/notepin.png" alt="Note" width="32" /></div>
                    <div class="column c25 tpm bmm">
                        <h3 class="bmm"><a href="notepad.php?vu=details&id={id}" title="{subject}">{subject}</a></h3>
                        {message}
                    </div>
                </td>
                <td>
                    <div class="right rax-active-pal round hlf-pad">
                        <a href="notepad.php?vu=form&id={id}" title="Edit note"><img class="align-middle" src="views/images/pencil.png" alt="Edit note" width="16" height="16"/></a>&nbsp;&nbsp;
                        <a class="delete" href="#{id}" title="Delete note"
                           data-event-confirm="Are you sure you want to delete this record?"><img class="align-middle" src="views/images/delete.png" alt="Delete Note" width="16" height="16"/></a>
                    </div>
                </td>
            </tr>
        </tbody>
    </table>
</div>
    
<hr class="space" />


