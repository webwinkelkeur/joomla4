<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');
 
jimport('joomla.application.component.view');
if (!class_exists('JViewLegacy')) {
    class_alias('JView', 'JViewLegacy');
}

class WebwinkelKeurViewConfig extends JViewLegacy {
    function display($tpl = null) {
        $this->config = $this->get('Config');

        $db = JFactory::getDBO();
        $db->setQuery("SELECT enabled FROM #__extensions WHERE element = 'com_virtuemart'");
        $this->virtuemart = $db->loadResult();
        $db->setQuery("SELECT enabled FROM #__extensions WHERE element = 'com_hikashop'");
        $this->hikashop = $db->loadResult();
        if (!$this->hikashop AND !$this->virtuemart) {
            JFactory::getApplication()->enqueueMessage('Om uitnodigingen te versturen moet HikaShop of Virtuemart zijn geïnstalleerd.', 'notice');
        }
        if ($this->hikashop) {
            JFactory::getApplication()->enqueueMessage('Uitnodigingen kunnen verzonden worden middels Hikashop.', 'notice');
        }
        if ($this->virtuemart) {
            JFactory::getApplication()->enqueueMessage('Uitnodigingen kunnen verzonden worden middels VirtueMart.', 'notice');
        }
        if (!JPluginHelper::isEnabled('system','webwinkelkeur')) {
            JFactory::getApplication()->enqueueMessage('WebwinkelKeur plugin moet enabled zijn voor een juiste werking. Deze lijkt momenteel uitgeschakeld.', 'warning');
        }
        
        parent::display($tpl);
    }
}
