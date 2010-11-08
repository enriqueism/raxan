<?php
/**
 * Raxan UI Element
 * Classes for building UI components and widgets
 * Copyright (c) 2008-2010 Raymond Irving (http://raxanpdi.com)
 * @package Raxan
 */

/**
 * Raxan User Interface Element Class
 * This class stores a reference to a single DOM element that is used to render the UI components
 * @property string $elmId Element id
 * @property string $elmMarkup Default UI html markup
 * @property DOMElement $element
 * @property DOMElement $contentElement Used as a proxy element to display text and html content
 * @property array $properties An array or ui Element properties 
 * @property string $preserveState Default state mode. set to local or session to preserve state within a component
 * @property string $background Set background color or image
 * @property string $foregound Set UI forground color
 * @property string $bordercolor Set UI border color
 * @property int $borderwidth Set UI border width value
 * @property int $borderradius Set UI border radius value
 * @property int $height Sets the height of the UI element
 * @property int $width Sets the width of the UI element
 * @property boolean $enableDefaultUIProperties Enables default UI properties such as borderradius, bordercolor, borderwidth, etc
 */
abstract class RaxanUIElement extends RaxanElement {

    public $isUIElement = true;

    protected $elmId;
    protected $element;
    protected $contentElement;
    protected $preserveState;
    protected $isRendered = false;
    protected $elmMarkup = '<div />';
    protected $properties = array();
    protected $enableDefaultUIProperties = true;

    // constructor
    public function __construct($id,$properties = null) {
        $this->_config(); // config ui 

        $autoid = $idIsString = $isArray = false;

        if ($properties instanceof RaxanDOMDocument) $doc = $properties;
        else if ($properties instanceof RaxanWebPage) $doc = $properties->document();
        else {
            $doc = RaxanWebPage::controller()->document();
            $isArray = is_array($properties);
        }
        
        if ($id instanceof DOMElement) { $elm = $id; $autoid=true; }
        else if (is_string($id)) { $elm = $doc->page->getElementById($id); $idIsString = true;}
        if (!$elm)  { $elm = $this->elmMarkup; $autoid = true; }

        // create instance
        parent::__construct($elm, $doc);

        if ($autoid) { // auto id
            if ($idIsString) $this->attr('id',$id);
            else $this->autoId();
        }

        $this->element = $this->elms[0];
        $this->elmId = $this->element->getAttribute('id');

        // import properties from element attributes
        $xtAttrs = array();
        foreach($this->element->attributes as $attr) {
            if (substr($attr->name,0,6)=='xt-ui-') {
                $this->properties[substr($attr->name,6)] = $attr->value;
                $xtAttrs[] = $attr->name;
            }
        }

        // remove xt-ui attribs
        foreach($xtAttrs as $attr) $this->element->removeAttribute($attr); 

        // merge properties
        if ($isArray) $this->properties = array_merge($this->properties,$properties);

        $this->_init(); // init
        $page = $this->doc->page;

        // setup default ui state
        if ($this->preserveState && $elm) {
            if (!$page->isLoaded && !$elm->hasAttribute('xt-preservestate')) {
                $elm->setAttribute('xt-preservestate',$this->preserveState);
            }
        }

        $page->registerUIElement($this);

    }

    public function __destruct() {
        $this->_destroy();
    }

    public function __get($name) {
        $v = parent::__get($name);
        return isset($v) ? $v : $this->_property($name);
    }

    public function __set($name,  $value) {
        $this->_property($name,$value,1);
    }

    // Special Event Handlers
    protected function _config() { }        // cofigure UI properties
    protected function _init() { }          // initialize UI 
    protected function _restore($mode,&$data) { return false; }  // restore UI state
    protected function _load() { }          // invoked after page load event
    protected function _prerender() { }     // invoked when page is been rendered
    protected function _save($mode,&$data) { return false; }     // save UI state
    protected function _destroy() { }       // class is being destroyed

    /**
     * Used to setup/intercept UI events. This handler is invoked when an event is attached.
     * @param string $type Event name or type. Example: click or #click
     * @param array $options Event options
     * @param boolean $local True for local events
     * @return boolean Returns true if event binding was handled locally
     */
    protected function _bind(&$type,&$options,&$local) { return false; } 

    /**
     * Used to setup/intercept data binding. This handler is invoked when binding data.
     * @param mixed $data Dataset
     * @param array $opt Optional
     * @return boolean Returns true if data binding was handled locally
     */
    protected function _bindData(&$data, &$opt) { return false; }
    
    /**
     * Used to read or write UI property values
     * @param string $name Property name
     * @param mixed $value Property value
     * @param boolean $writeMode
     * @return mixed
     */
    protected function _property($name,$value = null,$writeMode = false) {
        $name = strtolower($name); //  make properrty names case-insensitive so that they can be used as xt-ui attributes
        if ($writeMode) $this->properties[$name] = $value;
        else return ($name && isset($this->properties[$name])) ?
            $this->properties[$name] : null;
    }

    /**
     * Get UI Property value
     * @param mixed $name Option name
     * @return mixed
     */
    public function getProperty($name) {
        return $this->_property($name);
    }

    /**
     * Set UI Property value
     * @param string $name Option name
     * @param mixed $value
     * @return RaxanUIElement
     */
    public function setProperty($name,$value) {
        $this->_property($name,$value,true);
        return $this;
    }

    /**
     * Returns true if state data was handled by the UI. Used by RaxanWebPage::saveElement
     * @param string $mode
     * @param array $data
     * @param boolean $save
     * @return boolean
     */
    public function handleStateData($mode, &$data, $save = false) {
        $rt = $save ?
            $this->_save($mode,$data) :
            $this->_restore($mode,$data);
        return $rt;
    }

    /**
     * Handle event binding. Used to interecept UI event bindings
     * @param string $type
     * @param array $options
     * @param boolean $local
     * @return mixed
     */
    public function handleEventBinding(&$type,&$options,&$local) {
        return $this->_bind($type,$options,$local);
    }

    /**
     * Triggers the _load event handler to load addition data
     * @return RaxanUIElement
     */
    public function loadInterface() {
        $this->_load();
        return $this;
    }
    
    /**
     * Triggers the _prerender event handler to render the UI element.
     * @return RaxanUIElement
     */
    public function renderInterface() {
        if (!$this->isRendered) {
            if ($this->enableDefaultUIProperties) {
                $css = array();
                $prop = & $this->properties;
                if (isset($prop['background'])) $css['background'] = $prop['background'];
                if (isset($prop['foreground'])) $css['color'] = $prop['foreground'];
                if (isset($prop['height'])) $css['height'] = is_numeric($prop['height']) ? trim($prop['height']).'px' : $prop['height'];
                if (isset($prop['width'])) $css['width'] = is_numeric($prop['width']) ? trim($prop['width']).'px' : $prop['width'];;
                // border
                $bw = (isset($prop['borderwidth'])) ? $prop['borderwidth'] : null;
                $bc = (isset($prop['bordercolor'])) ? $prop['bordercolor'] : null;
                if ($bw||$bc) $css['border'] = ($bw ? $bw : 1).'px solid '.($bc ? $bc : '#000');
                if (isset($prop['borderradius'])) {
                    $css['border-radius'] =
                    $css['-moz-border-radius'] =
                    $css['-webkit-border-radius'] = $prop['borderradius'].'px';
                }
                if ($css) $this->css($css);
            }
            $this->_prerender();
        }
        $this->isRendered = true;
        return $this;
    }

    // Protected function
    // ---------------------------
    

}

/**
 * Raxan User Interface Container Class
 * This class stores a reference to a single DOM element that is used as a container when rendering UI components
 */
abstract class RaxanUIContainer extends RaxanUIElement {
    
    /**
     * Make child elements selectable
     * @param array $opt Optional. See jQuery Selectable plugin
     * @return RaxanUIContainer
     */
    public function selectable($opt = null) {
        $this->page->loadScript('jquery-ui-interactions');
        $this->client->selectable($opt);
        return $this;
    }

    /**
     * Make child elements sortable
     * @param array $opt Optional. See jQuery Sortable plugin
     * @return RaxanUIContainer
     */
    public function sortable($opt = null) {
        $this->page->loadScript('jquery-ui-interactions');
        $this->client->sortable($opt);
        return $this;
    }

}

?>