<h1>Raxan Framework</h1>
<p>Accelerate your development today</p>


&lt;hr /&gt;



<h2>What is Raxan?</h2>
Raxan is an open source framework for building rich PHP/Ajax web sites and applications.

Whether you are an advanced web developer, designer or just a beginner, you're always looking new ways to improve productivity and accelerate your development cycle.

As developers, we understand your needs and that's why we have created a framework with the right set of features that will improve your productivity. Within a few minutes, you can accomplish more than what it would normally take to get the job done properly.

With our Rich Ajax Application (Raxan) framework solution for developers and designers, you can spend less time coding and more time enjoying the things you love the most.

<h2>Quick Example:</h2>
By creating a Page Controller class you can easily gain access to service-side DOM elements:
```
<?php 

require_once("raxan/pdi/autostart.php");

class MyWebPage extends RaxanWebPage {        
    protected function _load() {
        // find a DOM element with the id "msg" and return a RaxanElement object
        $this->findById("myid")->text('Hello World');
    }            
}

?>
<div id="myid"></div>
```

Adding server-side events to DOM elements can be done in a simple two step process:

1) Create an event handler on the Page Controller<br />
2) Bind the event to the event handler

```
<?php 

require_once('raxan/pdi/autostart.php');

class MyPage extends RaxanWebPage {

    // callback function
    protected function buttonClick($e) {
        // find the msg element and set its html value to hello world
        $this->findById("msg")->html('Hello World');
    }
}

?>
<form name="form1" action="" method="post">
    <input id="mybutton" type="button" value="Click Me" xt-bind="click,buttonClick" />
    <div id="msg" />
</form>

```

To learn more about the framework please site the <a href='http://raxanpdi.com'>Raxan Website</a>