<?php

defined('_JEXEC') or die('Restricted access');
 
jimport('joomla.application.component.controller');
 
class WebwinkelKeurController extends JController {
    function display($cachable = false, $urlparams = false) {
        // set default view if not set
        $input = JFactory::getApplication()->input;
        $input->set('view', $input->getCmd('view', 'Config'));

        // add toolbar
        JToolBarHelper::title('WebwinkelKeur', 'webwinkelkeur');

        // set document title
        $doc = JFactory::getDocument();
        $doc->setTitle('WebwinkelKeur');

        // call parent behavior
        parent::display($cachable);
    }
}
