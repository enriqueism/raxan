<?php defined('RAXANPDI')||exit(); ?>

<div class="container rax-backdrop">
    <div class="container rax-content-pal round pad">
        <h2 id="title">New Note</h2>
        <div class="flashmsg"></div>
        <form id="form1" name="form1" action="" method="post">
            <input type="hidden" name="id" value="" />
            <div class="left">
                <p>
                    <label>Subject:</label><br />
                    <input type="text" name="subject" id="subject" value="" size="50" class="textbox" />
                </p>
                <p>
                    <label>Message:</label><br />
                    <textarea name="message" id="message" cols="52" rows="7"  class="textbox"></textarea>
                </p>

                <p>
                    <input type="submit" name="submitbtn" id="submitbtn" value="Save" xt-bind="click,saveNote" class="button process" />&nbsp;
                    <a class="button cancel" href="notepad.php">Cancel</a>
                </p>
            </div>
            <p class="left c15 prepend2">
                <strong>General Help</strong><br>
                Lorem ipsum cu nam impedit efficiantur, ei aperiri dissentiet eos, mea dico error saperet in. Vidisse pertinax deterruisset id vel, dicunt audire labitur his eu. Pro magna propriae at, augue choro quodsi est eu. </p>
        </form>
    </div>
</div>