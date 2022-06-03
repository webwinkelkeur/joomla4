<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

use Joomla\CMS\Factory;

defined('_JEXEC') or die('Restricted access');

jimport('joomla.application.component.modelitem');

class WebwinkelKeurModelConfig extends JModelItem {
    private $wwk_config = array(
        'invite_delay'     => 3,
        'javascript'       => true,
    );

    public function getConfig() {
        $config = $this->wwk_config;
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery("SELECT `value` FROM `#__webwinkelkeur_config` WHERE id = 1");
        $result = $db->loadResult();
        if($result)
            $config = array_merge($config, json_decode($result, true));
        return $config;
    }

    public function setConfig($config) {
        $json = json_encode($config);
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery("REPLACE INTO `#__webwinkelkeur_config` SET `id` = 1, `value` = " . $db->quote($json));
        return !!$db->execute();
    }

    public function getVirtueMart() {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $db->setQuery("SELECT `enabled` FROM `#__extensions` WHERE `name` = 'virtuemart' LIMIT 1");
        return !!$db->loadResult();
    }

    public function getItem($pk = null) {
        // TODO: Implement getItem() method.
    }
}
