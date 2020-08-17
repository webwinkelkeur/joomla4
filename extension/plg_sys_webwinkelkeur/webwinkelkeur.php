<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');

require_once dirname(__FILE__) . '/WebwinkelKeurShopPlatform.php';
require_once dirname(__FILE__) . '/WebwinkelKeurVirtuemartPlatform.php';
require_once dirname(__FILE__) . '/WebwinkelKeurHikaShopPlatform.php';

class PlgSystemWebwinkelKeur extends JPlugin {
    private $config;

    public function onBeforeCompileHead() {
        $app = JFactory::getApplication();
        if($app->isSite())
            $this->addScript();
    }

    public function onAfterInitialise() {
        $app = JFactory::getApplication();
        $db = JFactory::getDBO();
        if(!$app->isSite()) {
            $this->sendPlatformInvites(new WebwinkelKeurHikaShopPlatform($db));
            $this->sendPlatformInvites(new WebwinkelKeurVirtuemartPlatform($db));
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

        if(empty($config['javascript']))
            return;

        $settings = array(
            '_webwinkelkeur_id' => (int) $config['wwk_shop_id'],
        );

        ob_start();
        require dirname(__FILE__) . '/sidebar.php';
        $script = ob_get_clean();

        JFactory::getDocument()->addCustomTag($script);
    }

    private function sendPlatformInvites(WebwinkelKeurShopPlatform $platform) {
        $app = JFactory::getApplication();
        $db = JFactory::getDBO();
        $config = $this->getConfig();

        // platform enabled?
        list ($is_enabled, $platform_manifest) = $this->getExtensionInfo($platform->getExtensionName(), $db);
        if (!$is_enabled) {
            return;
        }

        // invites enabled?
        if(empty($config['invite'])
           || empty($config['wwk_shop_id'])
           || empty($config['wwk_api_key'])
        )
            return;

        // find orders
        try {
            $orders = $platform->getOrdersToInvite();
        } catch (RuntimeException $e) {
            return;
        }

        if(!$orders)
            return;

        // process
        list (, $wwk_manifest) = $this->getExtensionInfo('webwinkelkeur', $db);
        require_once dirname(__FILE__) . '/api.php';
        $api = new WebwinkelKeurAPI($config['wwk_shop_id'], $config['wwk_api_key']);
        foreach($orders as $order) {
            $error = null;
            $url = null;

            try {
                $data = array(
                    'order'     => $platform->getOrderId($order),
                    'email'     => $platform->getOrderEmail($order),
                    'delay'     => @$config['invite_delay'],
                    'language'  => $platform->getOrderLanguage($order),
                    'client'    => $platform->getClientName(),
                    'customer_name' => $platform->getOrderCustomerName($order),
                    'phone_numbers' => $platform->getOrderPhones($order),
                    'order_total'   => $platform->getOrderTotal($order),
                    'platform_version' => join('-', array(
                        'j',
                        JVERSION,
                        $platform->getPlatformAbbreviation(),
                        $platform_manifest->version
                    )),
                    'plugin_version' => $wwk_manifest->version
                );

                if (@$config['invite'] == 2) {
                    $data['max_invitations_per_email'] = 1;
                }
                if (empty ($config['limit_order_data']) || !$config['limit_order_data']) {
                    $data['order_data'] = json_encode($platform->getOrderData($order));
                }


                try {
                    $api->invite($data);
                } catch(WebwinkelKeurAPIAlreadySentError $e) {
                } catch(WebwinkelKeurAPIError $e) {
                    $error = $e->getMessage();
                    $url = $e->getURL();
                }

                $platform->updateOrderInvitesSend($order, $error);

            } catch (Exception $e) {
                $error = $e->getMessage();
                $url = '';
            }

            if($error) {
                $db->setQuery("
                    INSERT INTO `#__webwinkelkeur_invite_error` SET
                        `url` = " . $db->quote($url) . ",
                        `response` = " . $db->quote($error) . ",
                        `time` = " . time() . ",
                        `reported` = 0
                ");
                $db->query();
                $app->enqueueMessage("De WebwinkelKeur uitnodiging voor order {$platform->getOrderId($order)} kon niet worden verstuurd. -- $error", 'error');
            }
        }
    }

    private function getExtensionInfo($extension_name, $db) {
        $db->setQuery("SELECT enabled, manifest_cache FROM #__extensions WHERE element = '$extension_name'");
        list ($is_enabled, $manifest_json) = $db->loadRow();
        $manifest = json_decode($manifest_json);
        return array($is_enabled, $manifest);
    }
}
