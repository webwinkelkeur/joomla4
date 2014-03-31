<?php

defined('_JEXEC') or die('Restricted access');

class PlgSystemWebwinkelKeur extends JPlugin {
    private $config;

    public function onBeforeCompileHead() {
        $app = JFactory::getApplication();

        if($app->isSite()) {
            $this->addScript();
        }
    }

    private function getConfig() {
        if(!isset($this->config)) {
            $db = JFactory::getDBO();
            $db->setQuery("SELECT `value` FROM `#__webwinkelkeur_config` WHERE `id` = 1");
            $result = $db->loadResult();
            if($result) {
                $this->config = @json_decode($result, true);
            } else {
                $this->config = array();
            }
        }
        return $this->config;
    }

    private function addScript() {
        $config = $this->getConfig();

        if(empty($config['wwk_shop_id']))
            return;

        if(empty($config['sidebar'])
           && empty($config['tooltip'])
           && empty($config['javascript'])
        )
            return;

        $settings = array(
            '_webwinkelkeur_id' => (int) $config['wwk_shop_id'],
            '_webwinkelkeur_sidebar' => !empty($config['sidebar']),
            '_webwinkelkeur_tooltip' => !empty($config['tooltip']),
        );

        if($sidebar_position = @$config['sidebar_position'])
            $settings['_webwinkelkeur_sidebar_position'] = $sidebar_position;

        $sidebar_top = @$config['sidebar_top'];
        if(is_string($sidebar_top) && $sidebar_top != '')
            $settings['_webwinkelkeur_sidebar_top'] = $sidebar_top;

        ob_start();
        require dirname(__FILE__) . '/sidebar.php';
        $script = ob_get_clean();

        JFactory::getDocument()->addCustomTag($script);
    }
}
