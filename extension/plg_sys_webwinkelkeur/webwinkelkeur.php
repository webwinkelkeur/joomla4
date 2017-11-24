<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');

require_once dirname(__FILE__) . '/WebwinkelKeurShopPlatform.php';
require_once dirname(__FILE__) . '/WebwinkelKeurVirtuemartPlatform.php';

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
            $this->sendHikashopInvites();
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

    /*
    * Hikashop functions
    */
    function plgHikashopName(&$subject, $config){
        parent::__construct($subject, $config);
    }

    function sendHikashopInvites() {
        $app = JFactory::getApplication();
        $db = JFactory::getDBO();
        $config = $this->getConfig();

        // hikashop enabled?
        $db->setQuery("SELECT enabled FROM #__extensions WHERE element = 'com_hikashop'");
        $is_enabled = $db->loadResult();
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
        $db->setQuery("
            SELECT
                ho.order_id,
                ho.order_number,
                hu.user_email,
                CONCAT(ha.address_firstname, ' ', ha.address_lastname) as customername
            FROM `#__hikashop_order` ho
            INNER JOIN `#__hikashop_user` hu ON
                ho.order_user_id = hu.user_id
            LEFT JOIN `#__hikashop_address` ha ON
                ho.order_billing_address_id = ha.address_id
            LEFT JOIN `#__hikashop_zone` hz ON
                ha.address_country = hz.zone_namekey
            LEFT JOIN `#__webwinkelkeur_hikashop_order` who ON
                who.hikashop_order_id = ho.order_id
            WHERE
                (
                    who.hikashop_order_id IS NULL
                    OR (
                        who.success = 0
                        AND who.tries <= 5
                        AND who.time < " . (time() - 1800) . "
                    )
                )
                AND hu.user_email LIKE '%@%'
                AND ho.order_status = 'shipped'
        ");
        $orders = $db->loadAssocList();
        if(!$orders)
            return;

        // process
        require_once dirname(__FILE__) . '/api.php';
        foreach($orders as $order) {
            $api = new WebwinkelKeurAPI($config['wwk_shop_id'], $config['wwk_api_key']);
            $error = null;
            $url = null;
            $data = array(
                'order'     => $order['order_number'],
                'email'     => $order['user_email'],
                'delay'     => @$config['invite_delay'],
                'language'  => null,
                'client'    => 'hikashop',
                'customer_name' => $order['customername']
            );
            if (@$config['invite'] == 2) {
                $data['max_invitations_per_email'] = 1;
            }

            try {
                $api->invite($data);
            } catch(WebwinkelKeurAPIAlreadySentError $e) {
            } catch(WebwinkelKeurAPIError $e) {
                $error = $e->getMessage();
                $url = $e->getURL();
            }

            $now = time();

            $db->setQuery("
                INSERT INTO `#__webwinkelkeur_hikashop_order` SET
                    `hikashop_order_id` = " . (int) $order['order_id'] . ",
                    `success` = " . ($error ? '0' : '1') . ",
                    `tries` = 1,
                    `time` = " . $now . "
                ON DUPLICATE KEY UPDATE
                    `success` = IF(`success` = 1, 1, " . ($error ? '0' : '1') . "),
                    `tries` = `tries` + 1,
                    `time` = " . $now . "
            ");
            $db->query();

            if($error) {
                $db->setQuery("
                    INSERT INTO `#__webwinkelkeur_invite_error` SET
                        `url` = " . $db->quote($url) . ",
                        `response` = " . $db->quote($error) . ",
                        `time` = " . $now . ",
                        `reported` = 0
                ");
                $db->query();
                $app->enqueueMessage("De WebwinkelKeur uitnodiging voor order {$order['order_number']} kon niet worden verstuurd. -- $error", 'error');
            }
        }
    }


    private function sendPlatformInvites(WebwinkelKeurShopPlatform $platform) {
        $app = JFactory::getApplication();
        $db = JFactory::getDBO();
        $config = $this->getConfig();

        // virtuemart enabled?
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
        $orders = $platform->getOrdersToInvite();
        if(!$orders)
            return;

        // process
        list (, $wwk_manifest) = $this->getExtensionInfo('webwinkelkeur', $db);
        require_once dirname(__FILE__) . '/api.php';
        $api = new WebwinkelKeurAPI($config['wwk_shop_id'], $config['wwk_api_key']);
        foreach($orders as $order) {
            $error = null;
            $url = null;
            $data = array(
                'order'     => $platform->getOrderId($order),
                'email'     => $platform->getOrderEmail($order),
                'delay'     => @$config['invite_delay'],
                'language'  => $platform->getOrderLanguage($order),
                'client'    => $platform->getClientName(),
                'customer_name' => $platform->getOrderCustomerName($order),
                'phone_numbers' => $platform->getOrderPhones($order),
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
