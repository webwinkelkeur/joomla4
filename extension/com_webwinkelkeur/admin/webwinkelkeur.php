<?php

defined('_JEXEC') or die('Restricted access');

$doc = JFactory::getDocument();
$doc->addStyleDeclaration('.icon-48-webwinkelkeur { background-image: url(../media/com_webwinkelkeur/images/logo48.png); }');
$doc->addStyleSheet('components/com_webwinkelkeur/webwinkelkeur.css');
 
jimport('joomla.application.component.controller');
 
$controller = JControllerLegacy::getInstance('WebwinkelKeur');
 
$jinput = JFactory::getApplication()->input;
$task = $jinput->get('task', "", 'STR' );
 
$controller->execute($task);
 
$controller->redirect();
