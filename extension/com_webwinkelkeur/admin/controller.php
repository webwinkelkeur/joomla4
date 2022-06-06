<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');
 
jimport('joomla.application.component.controller');
 
class WebwinkelKeurController extends JControllerLegacy {
    private $wwk_fields = array(
        'wwk_shop_id',
        'wwk_api_key',
        'invite',
        'invite_delay',
        'limit_order_data',
        'javascript',
    );
    
    function display($cachable = false, $urlparams = false) {
        // set default view if not set
        $app = JFactory::getApplication();
        $input = $app->input;
        $input->set('view', $input->getCmd('view', 'Config'));

        // add toolbar
        JToolBarHelper::title('WebwinkelKeur', 'webwinkelkeur');
        JToolBarHelper::apply();
        JToolBarHelper::save();
        JToolBarHelper::cancel('cancel', 'JTOOLBAR_CLOSE');

        // set document title
        $doc = $app->getDocument();
        $doc->setTitle('WebwinkelKeur');

        // call parent behavior
        parent::display($cachable);
    }

    private function doApply() {
        $app = JFactory::getApplication();
        $config = $app->input->post->getArray();
        $errors = array();

        foreach($this->wwk_fields as $field_name) {
            $value = @$_POST['webwinkelkeur_' . $field_name];
            if(is_string($value)) {
                $value = str_replace('\\', '', $value);
                $value = trim($value);
                $config[$field_name] = $value;
            } elseif(!isset($config[$field_name])) {
                $config[$field_name] = '';
            }
        }

        if(empty($config['wwk_shop_id']))
            $errors[] = 'Uw webwinkel ID is verplicht.';
        elseif(!ctype_digit($config['wwk_shop_id']))
            $errors[] = 'Uw webwinkel ID kan alleen cijfers bevatten.';

        if($config['invite'] && !$config['wwk_api_key'])
            $errors[] = 'Om uitnodigingen te versturen is uw API key verplicht.';

        $application = JFactory::getApplication();

        foreach($errors as $error)
            $application->enqueueMessage($error, 'error');

        if(!$errors) {
            if($this->getModel('config')->setConfig($config))
                $application->enqueueMessage('Uw wijzigingen zijn opgeslagen.');
            else
                $application->enqueueMessage('Uw wijzigingen konden niet worden opgeslagen.', 'error');
        }

        return !$errors;
    }

    function apply() {
        $this->doApply();
        $this->display();
    }

    function save() {
        if($this->doApply()) {
            $app = JFactory::getApplication();
            $app->redirect(JRoute::_('index.php'));
        } else {
            $this->display();
        }
    }

    function cancel() {
        $app = JFactory::getApplication();
        $app->redirect(JRoute::_('index.php'));
    }
}
