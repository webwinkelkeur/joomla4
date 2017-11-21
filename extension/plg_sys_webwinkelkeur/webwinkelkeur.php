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
    
    /*
    * Virtuemart functions
    */
    private function sendVirtuemartInvites() {
        $app = JFactory::getApplication();
        $db = JFactory::getDBO();
        $config = $this->getConfig();

        // virtuemart enabled?
        list ($is_enabled, $virtuemart_manifest) = $this->getExtensionInfo('com_virtuemart', $db);
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
        list (, $wwk_manifest) = $this->getExtensionInfo('webwinkelkeur', $db);
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
                'customer_name' => $order['customer_name'],
                'platform_version' => 'j-' . JVERSION . '-vm-' . $virtuemart_manifest->version,
                'plugin_version' => $wwk_manifest->version
            );

            if (@$config['invite'] == 2) {
                $data['max_invitations_per_email'] = 1;
            }

            try {
                $order_data = $this->getVirtuemarketOrderData($order, $db);
                $phones = array(
                    $order_data['invoice_address']['phone_1'],
                    $order_data['invoice_address']['phone_2']
                );
                if (isset ($order_data['delivery_address'])) {
                    $phones[] = $order_data['delivery_address']['phone_1'];
                    $phones[] = $order_data['delivery_address']['phone_2'];
                }
                $data['phone_numbers'] = array_unique(array_filter($phones));

                if (empty ($config['limit_order_data']) || !$config['limit_order_data']) {
                    $data['order_data'] = json_encode($order_data);
                }
            } catch (Exception $e) {}

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

    private function getExtensionInfo($extension_name, $db) {
        $db->setQuery("SELECT enabled, manifest_cache FROM #__extensions WHERE element = '$extension_name'");
        list ($is_enabled, $manifest_json) = $db->loadRow();
        $manifest = json_decode($manifest_json);
        return array($is_enabled, $manifest);
    }

    private function getVirtuemarketOrderData($order, $db) {
        $order_query = "SELECT * FROM `#__virtuemart_orders` WHERE `virtuemart_order_id` = "
            . $order['virtuemart_order_id'];
        $order_info = $db->setQuery($order_query)->loadAssoc();
        $lines_query = "SELECT * FROM `#__virtuemart_order_items` WHERE `virtuemart_order_id` = "
            . $order['virtuemart_order_id'];
        $order_info['order_lines'] = $db->setQuery($lines_query)->loadAssocList();

        $product_ids = join(',', array_map(function ($line) {
            return $line['virtuemart_product_id'];
        }, $order_info['order_lines']));
        $products_query = "SELECT * FROM `#__virtuemart_products` WHERE `virtuemart_product_id` IN ($product_ids)";
        $products = [];
        foreach ($db->setQuery($products_query)->loadAssocList() as $product) {
            $product['images'] = [];
            $products[$product['virtuemart_product_id']] = $product;
        }

        $product_ids = join(',', array_keys($products));
        $images_query = "
          SELECT 
            `p`.`virtuemart_product_id`, 
            `m`.`file_url`
          FROM `#__virtuemart_products` AS `p`
            LEFT JOIN `#__virtuemart_product_medias` AS `pm`
              ON `p`.`virtuemart_product_id` = `pm`.`virtuemart_product_id`
                  OR `p`.`product_parent_id` = `pm`.`virtuemart_product_id`
            LEFT JOIN `#__virtuemart_medias` AS `m`
              ON `pm`.`virtuemart_media_id` = `m`.`virtuemart_media_id`
          WHERE `p`.`virtuemart_product_id` IN ($product_ids)";
        foreach ($db->setQuery($images_query)->loadAssocList() as $image) {
            $products[$image['virtuemart_product_id']]['images'][] =
                'http' . (isset ($_SERVER['HTTPS']) ? 's' : '') . '://'
                . $_SERVER['HTTP_HOST'] . '/' . $image['file_url'];
        }

        $customer_query = "SELECT * FROM `#__users` WHERE `id` = " . $order_info['virtuemart_user_id'];
        $customer = $db->setQuery($customer_query)->loadAssoc();
        if (!empty ($customer)) {
            unset ($customer['password']);
        }

        $order_data = [
            'order' => $order_info,
            'products' => array_values($products),
            'customer' => $customer
        ];

        $addresses_query = "SELECT * FROM `#__virtuemart_order_userinfos` WHERE `virtuemart_order_id` = "
            . $order['virtuemart_order_id'];
        foreach ($db->setQuery($addresses_query)->loadAssocList() as $address) {
            if ($address['address_type'] == 'BT') {
                $order_data['invoice_address'] = $address;
            } else {
                $order_data['delivery_address'] = $address;
            }
        }

        return $order_data;

    }

}
