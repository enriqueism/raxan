<?php defined('RAXANPDI')||exit(); ?>

<h1>Notes</h1>
<hr />
<div class="flashmsg" />
<div class="hlf-pad bmm">
    <form id="searchfrm" name="searchfrm" action="" method="post">
        <span class="right">
            <label>Search: </label>
            <input type="text" name="querytxt" id="querytxt" value="" class="textbox" />&nbsp;
            <input type="submit" name="searchbtn" id="searchbtn" value="Search" class="button"/>
        </span>
        <span ><a href="notes.php?vu=form" class="button ok" >Add Note</a></span>
    </form>
</div>
<table class="border" >
    <thead>
        <tr class="tbl-header">
            <th>Subject</th><th>Message</th><th class="c4">Action</th>
        </tr>
    </thead>
    <tbody id="noteList" xt-delegate="a.delete click,deleteNote">
        <tr class="{ROWCLASS}">
            <td><a href="notes.php?vu=details&id={id}" title="{subject}">{subject}</a></td><td>{message}</td><td><a href="notes.php?vu=form&id={id}">Edit</a> | <a class="delete" href="#{id}">Delete</a></td>
        </tr>
    </tbody>
</table>


