<?php defined('RAXANPDI')||exit(); ?>

<h1 id="title" style="color:green" >New Note</span></h1>
<hr />
<hr class="space" />
<div class="flashmsg"></div>
<div class="box ">
    <form id="form1" name="form1" action="" method="post">
        <input type="hidden" name="id" value="" />
        <div class="left">
            <p>
                <label>Subject:</label><br />
                <input type="text" name="subject" id="subject" value="" size="40" class="textbox" />
            </p>
            <p>
                <label>Message:</label><br />
                <textarea name="message" id="message" cols="40" rows="5"  class="textbox"></textarea>
            </p>

            <p>
                <input type="submit" name="submitbtn" id="submitbtn" value="Save" xt-bind="click,saveNote" class="button process" /> |
                <a href="notes.php">Cancel</a>
            </p>
        </div>
        <p class="left c10">Lorem ipsum cu nam impedit efficiantur, ei aperiri dissentiet eos, mea dico error saperet in. Vidisse pertinax deterruisset id vel, dicunt audire labitur his eu. Pro magna propriae at, augue choro quodsi est eu. Tota cotidieque reformidans ei qui, ad dicit impetus persequeris pri, harum accommodare id per. Mediocrem quaerendum cu has, habeo inermis nominati eu sed.</p>
        <hr class="clear space"/>
    </form>
</div>