<?php

defined('_JEXEC') or die('Restricted access');
 
jimport('joomla.application.component.view');

class WebwinkelKeurViewConfig extends JView {
    function display($tpl = null) {
        $this->config = $this->get('Config');

        parent::display($tpl);
    }
}
