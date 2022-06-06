<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');

$app = JFactory::getApplication();
$doc = $app->getDocument();
$doc->addStyleDeclaration('.icon-48-webwinkelkeur { background-image: url(../media/com_webwinkelkeur/images/logo48.png); }');
$doc->addStyleSheet('components/com_webwinkelkeur/webwinkelkeur.css');
 
jimport('joomla.application.component.controller');
if (!class_exists('JControllerLegacy')) {
    class_alias('JController', 'JControllerLegacy');
}
 
$controller = JControllerLegacy::getInstance('WebwinkelKeur');

$jinput = JFactory::getApplication()->input;
$task = $jinput->get('task', "", 'STR' );
 
$controller->execute($task);
 
$controller->redirect();
