<?php
require_once "../raxan/pdi/autostart.php";

class NewPage extends RaxanWebPage {

    protected function _config() {
        $this->masterTemplate = 'views/master.example.php';
    }

}
?>

<style type="text/css">
    .photo img {
        vertical-align:middle;
        margin-bottom:10px;
    }
    .photo {
        width:165px;
        height:160px;
        text-align:center;
    }
    .featured .column { text-align:center}
    .featured a { 
        color:#2b2;
        text-decoration: none;
        font-weight: bold
    }
    .developer { margin-right: 20px; }
    .developer h2 { color: #b2b; }
    .developer a { text-decoration: none; color: #a2a; font-size: 1.12em}
    .developer p { color: #222;}

    .designer h2 { color: #2b2; }
    .designer a { text-decoration: none; color: #090; font-size: 1.12em}
    .designer p { color: #222;}

    .server-image {
        float:left;
        margin: 5px 20px;
    }

</style>

<h2>Featured Examples</h2>
<div class="featured container append-bottom rax-backdrop">
    <div class="white round pad clearfix">
        <div class="column colborder">
            <div class="photo">
                <a href="example.php?id=employees"><img src="views/images/demo4.png" height="128" alt="Employee Directory"><br />Employee Directory</a>
            </div>
        </div>
        <div class="column colborder">
            <div class="photo">
                <a href="../examples/example.php?id=shoutbox"><img src="views/images/demo3.png" height="128" alt="ShoutBox"><br />Ajax ShoutBox</a>
            </div>
        </div>
        <div class="column colborder">
            <div class="photo">
                <a href="../examples/example.php?id=contactlist"><img src="views/images/demo1.png" height="128" alt="Ajax Contact Form"><br />Ajax Contact Form</a>
            </div>            
        </div>
        <div class="column colborder">
            <div class="photo">
                <a href="../examples/example.php?id=searchbox"><img src="views/images/demo2.png" height="128" alt="Exmployee Search"><br>Employee Search Box</a>
            </div>
        </div>
        <div class="column last">
            <div class="photo">
                <a href="example.php?id=notepad"><img src="views/images/demo5.png" height="128" alt="Notepad"><br />Notepad</a>
            </div>
        </div>
    </div>
</div>

<div class="rax-active-pal round pad border append-bottom rax-glossy">
    <img src="views/images/server.png" alt="Server" width="49" height="56" class="server-image"/>
    <h3 class="bmm"><span class="color-blue">Before you</span> <span class="color-orange">begin...</span></h3>
    A web server running PHP 5.1 or higher is require to view the examples.
    A few examples will also require read/write permission to the <strong>SQLite database files (*.db)</strong> inside the <strong>examples/data</strong> folder.
</div>

<div class="container border pad round">

    <div class="developer c22 column pad">
        <h2 class="bmm">PHP Developers</h2>
        <span>Examples for web developers using PHP, CSS, JavaScript and HTML</span>
        <hr />
        <a href="example.php?id=calculator">Ajax  Calculator</a><br>
        <p>Add two numbers and displays the result</p>

        <a href="example.php?id=contactlist">Ajax Contact Form</a><br>
        <p>Create, edit and delete contacts using an Ajax web form.</p>

        <a href="example.php?id=ajax-file-upload">Ajax File Upload</a><br>
        <p>Uploads an image using an Ajax Web Form</p>

        <a href="example.php?id=client-server">Client-Server Communication</a><br>
        <p>Examples of client-server communication</p>

        <a href="example.php?id=clx">Control Client-Side DOM Elements from the Server</a><br>
        <p>This example uses the Raxan Client Extension (CLX) class to query and control client-side elements from the server</p>

        <a href="example.php?id=custom-validators">Custom Zip and Phone validators</a><br>
        <p>Example showing how to add custom validation to RaxanDataSanitizer</p>

        <a href="example.php?id=date-time-plugin">Date/Time Plugin</a><br>
        <p>An example of how to create a basic plugin</p>

        <a href="example.php?id=date-entry">Date Entry</a><br>
        <p>An example of how to use the RaxanDataSanitizer->date() method</p>

        <a href="example.php?id=dragdrop">Drag and Drop</a><br>
        <p>Drag and drop items into a shopping cart</p>

        <a href="example.php?id=dragresize">Drag and Resize</a><br>
        <p>Draggable and Resizable elements</p>

        <a href="example.php?id=editable">Editable List Items</a><br>
        <p>Edit and update a list of items via Ajax</p>

        <a href="example.php?id=echobox">Echo Box</a><br>
        <p>Echoes the content of a Textbox when the submit button is clicked</p>

        <a href="example.php?id=employees">Employee Directory</a><br>
        <p>Displays a list of employee records inside a table/grid with a pager</p>

        <a href="example.php?id=searchbox">Employee Search Box</a><br>
        <p>Query and display employee records using an Ajax-based search form.</p>

        <a href="example.php?id=custom-methods">Extending the RaxanElement Class</a><br>
        <p>Use callback methods to extending the RaxanElement</p>

        <a href="example.php?id=flashmsg">Flash Message</a><br>
        <p>Examples of how to use the flashmsg() method to display messages within the web browser</p>

        <a href="example.php?id=image-search">Image Search</a><br>
        <p>Display list of images from the server. This example uses the Raxan.dispatchEvent() method</p>

        <a href="mobile-weather.php">Mobile Weather (WAP)</a><br>
        <p>Displays weather information for a city or location on a mobile phone or WAP enabled device.
            This example can be viewed using the <a href="http://www.opera.com/developer/tools/">Opera Mobile Emulator</a>.
            View the Mobile weather application <a href="example.php?id=mobile-weather&view-source=on">source code</a>.
        </p>

        <a href="example.php?id=multi-lang">Multilingual Date Display</a><br>
        <p>Displays the current date in multiple languages</p>

        <a href="example.php?id=notepad">Notepad</a><br>
        <p>An an example for how to use page views and events to create a data driven application</p>

        <a href="example.php?id=pageview">Page Views</a><br>
        <p>Use the page view handlers to change the view of a web page</p>

        <a href="example.php?id=popup-box">Popup Box</a><br>
        <p>Displays a client-side confirm or prompt popup box from the server</p>

        <a href="example.php?id=inventory">Phone Inventory</a><br>
        <p>A simple Ajax application that display items from a CSV data file</p>

        <a href="example.php?id=state">Preserve Element State</a><br>
        <p>Preserves the state of an element during post backs and page load</p>

        <a href="example.php?id=form-state">Preserve Web Form Content</a><br>
        <p>Example showing the use of the $preserveFormContent property.</p>

        <a href="example.php?id=rating">Product Rating</a><br>
        <p>An example using the jQuery UI star plugin to trigger a server-side event</p>

        <a href="example.php?id=randdiv">Random DIVs</a><br>
        <p>Randomly generated DIV elements with mouse over effect</p>

        <a href="example.php?id=smartphones">Smart Phones with star rating</a><br>
        <p>Displays smart phones with star rating component</p>

        <a href="example.php?id=shoutbox">ShoutBox</a><br>
        <p>A simple Ajax-based ShoutBox application</p>

        <a href="example.php?id=embedded">ShoutBox inside a web page</a><br>
        <p>This example shows how to embed the ShoutBox application within an HTML web page.</p>

        <a href="example.php?id=template-binder">Template Binder</a><br>
        <p>An example of some of the most commonly used Template Binder options</p>

        <a href="example.php?id=web-page-extractor">Web Page Extractor</a><br>
        <p>This example uses an instance of the RaxanWebPage class to connect and extract data from a Yahoo search page.</p>
    </div>

    <div class="designer c21 column pad last">
        <h2 class="bmm">JavaScript/HTML Developers</h2>
        <span>Examples for web developers using CSS, HTML and JavaScript</span>
        <hr />

        <a href="../examples/example.php?id=css-framework&view-source=off">CSS Framework Classes</a><br />
        <p>Examples of CSS framework classes with default theme</p>

        <a href="../examples/example.php?id=css-bounce">Bouncing Ball</a><br />
        <p>An example showing how to combine Physics, jQuery and the CSS framework to create an interactive user experience</p>

        <a href="../examples/example.php?id=css-buttons">CSS Buttons</a><br />
        <p>Creating button elements with the CSS framework</p>

        <a href="../examples/example.php?id=css-dragbox">Drag Box (jQuery)</a>
        <p>An example showing the use of the jQuery UI interactive APIs</p>

        <a href="../examples/example.php?id=css-elastic">Elastic layouts</a><br />
        <p>Using elastic cells to create liquid layouts with fixed-width sidebars</p>

        <a href="../examples/example.php?id=css-elastic-grid">Elastic grid layout</a><br />
        <p>Displays the list of the available elastic cell classes</p>

        <a href="../examples/example.php?id=css-grid-cells">Fixed-Width grid layout</a><br />
        <p>Creates a grid layout with fixed width cell classes</p>

        <a href="../examples/example.php?id=css-grid-cell-width">Fixed-Width grid columns</a><br />
        <p>Displays the list of available fixed-width cell (column) classes</p>

        <a href="../examples/example.php?id=css-grid-cell-height">Fixed-Height grid rows</a><br />
        <p>Displays the list of available fixed-height cell (row) classes</p>

        <a href="../examples/example.php?id=css-sortable-columns">Sortable Columns (jQuery)</a><br />
        <p>Rearrange blocks by dragging and dropping them across columns.</p>

        <a href="../examples/example.php?id=css-raxan-cursor">Raxan JavaScript Cursor Plugin</a><br />
        <p>An example of how to initialize the Raxan cursor plugin with JavaScript.</p>

        <a href="../examples/example.php?id=css-raxan-tabstrip">Raxan JavaScript TabStrip Plugin</a><br />
        <p>An example of how to initialize the Raxan TabStrip plugin with JavaScript.</p>

        <a href="../examples/example.php?id=css-raxan-tabstrip-explorer">Raxan JavaScript TabStrip Explorer</a><br />
        <p>Explore the features of the TabStrip plugin. Learn how to create themes and animations and how to use autopilot features</p>

        <a href="../examples/example.php?id=css-tables">Table with hover effect</a><br />
        <p>An HTML Table with jQuery hover effect.</p>

        <a href="../examples/example.php?id=css-textboxes">Web form with text input</a><br />
        <p>Creates a web form with default theme</p>

        <a href="../examples/example.php?id=css-webform">Web form with CSS Buttons</a><br />
        <p>Creates a nice looking web form using a combination classes from the CSS framework</p>

        <a href="../examples/example.php?id=css-sample-page">Sample Web Page with default theme</a><br />
        <p>Provides a sample of some of the classes and layout features of the CSS framework</p>

    </div>
</div>

<hr class="space"/>
