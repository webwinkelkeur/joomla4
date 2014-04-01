<?php

defined('_JEXEC') or die('Restricted access');
 
jimport('joomla.application.component.view');

class WebwinkelKeurViewConfig extends JViewLegacy {
    function display($tpl = null) {
        $this->config = $this->get('Config');
        $this->virtuemart = $this->get('VirtueMart');

        if(!$this->virtuemart) {
            JFactory::getApplication()->enqueueMessage('Om uitnodigingen te versturen moet VirtueMart zijn geïnstalleerd.', 'notice');
        }

        parent::display($tpl);
    }
}
