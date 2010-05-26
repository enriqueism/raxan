<?php
/**
 * Raxan Toolkit
 * @package Toolkit
 */

/**
 * ContainerView widget
 * @property string $scrollbars
 */
class ContainerView extends RaxanUIContainer {

    protected function _prerender() {
        $props = $this->properties;
        if (isset($props['scrollbars'])) $this->css('overflow',$props['scrollbars']=="true" ? 'scroll' : $props['scrollbars']);
    }

}

?>