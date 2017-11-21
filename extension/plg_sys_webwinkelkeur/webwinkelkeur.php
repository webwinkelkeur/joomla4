<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');

class PlgSystemWebwinkelKeur extends JPlugin {
    private $config;

    public function onBeforeCompileHead() {
        $app = JFactory::getApplication();
        if($app->isSite())
            $this->addScript();
    }

    public function onAfterInitialise() {
        $app = JFactory::getApplication();
        if(!$app->isSite()) {
            $this->sendHikashopInvites();
            $this->sendVirtuemartInvites();
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

        $delay = (int) @$config['invite_delay'];
        $noremail = @$config['invite'] == 2;

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
            try {
                $api->invite($order['order_number'], $order['user_email'], $delay, null, $order['customername'], 'hikashop', $noremail);
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
    
    /*
    * Virtuemart functions
    */
    private function sendVirtuemartInvites() {
        $app = JFactory::getApplication();
        $db = JFactory::getDBO();
        $config = $this->getConfig();

        // virtuemart enabled?
        $db->setQuery("SELECT enabled FROM #__extensions WHERE element = 'com_virtuemart'");
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
                vo.virtuemart_order_id,
                vo.order_number,
                vo.order_language,
                vou.email,
                CONCAT(vou.first_name, ' ', vou.last_name) as customer_name
            FROM `#__virtuemart_orders` vo
            INNER JOIN `#__virtuemart_order_userinfos` vou ON
                vou.virtuemart_order_id = vo.virtuemart_order_id
                AND vou.address_type = 'BT'
            LEFT JOIN `#__webwinkelkeur_virtuemart_order` wvo ON
                wvo.virtuemart_order_id = vo.virtuemart_order_id
            WHERE
                (
                    wvo.virtuemart_order_id IS NULL
                    OR (
                        wvo.success = 0
                        AND wvo.tries <= 5
                        AND wvo.time < " . (time() - 1800) . "
                    )
                )
                AND vou.email LIKE '%@%'
                AND vo.order_status = 'S'
        ");
        $orders = $db->loadAssocList();
        if(!$orders)
            return;

        // process
        require_once dirname(__FILE__) . '/api.php';
        $api = new WebwinkelKeurAPI($config['wwk_shop_id'], $config['wwk_api_key']);
        foreach($orders as $order) {
            $error = null;
            $url = null;
            $data = array(
                'order'     => $order['order_number'],
                'email'     => $order['email'],
                'delay'     => @$config['invite_delay'],
                'language'  => $order['order_language'],
                'client'    => 'virtuemart',
                'customer_name' => $order['customer_name']
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
                INSERT INTO `#__webwinkelkeur_virtuemart_order` SET
                    `virtuemart_order_id` = " . (int) $order['virtuemart_order_id'] . ",
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
}
