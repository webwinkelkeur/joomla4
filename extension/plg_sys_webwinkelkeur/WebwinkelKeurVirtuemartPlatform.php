<?php

require_once dirname(__FILE__) . '/WebwinkelKeurShopPlatform.php';
class WebwinkelKeurVirtuemartPlatform implements WebwinkelKeurShopPlatform {

    /**
     * @var JDatabaseDriver
     * @since 1.0.1
     */
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getExtensionName() {
        return 'com_virtuemart';
    }

    public function getPlatformAbbreviation() {
        return 'vm';
    }

    public function getClientName() {
        return 'virtuemart';
    }

    public function getOrdersToInvite() {
        return $this->db->setQuery("
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
        ")->loadAssocList();
    }

    public function getOrderId($order) {
        return $order['order_number'];
    }

    public function getOrderEmail($order) {
        return $order['email'];
    }

    public function getOrderCustomerName($order) {
        return $order['customer_name'];
    }

    public function getOrderLanguage($order) {
        return $order['order_language'];
    }

    public function getOrderPhones($order) {
        $order_data = $this->getOrderData($order);
        $phones = array(
            $order_data['invoice_address']['phone_1'],
            $order_data['invoice_address']['phone_2']
        );
        if (isset ($order_data['delivery_address'])) {
            $phones[] = $order_data['delivery_address']['phone_1'];
            $phones[] = $order_data['delivery_address']['phone_2'];
        }
        return array_unique(array_filter($phones));
    }

    public function getOrderData($order) {
        static $orders_cache = array();
        if (isset ($orders_cache[$order['virtuemart_order_id']])) {
            return $orders_cache[$order['virtuemart_order_id']];
        }
        $order_query = "SELECT * FROM `#__virtuemart_orders` WHERE `virtuemart_order_id` = "
            . $order['virtuemart_order_id'];
        $order_info = $this->db->setQuery($order_query)->loadAssoc();
        $lines_query = "SELECT * FROM `#__virtuemart_order_items` WHERE `virtuemart_order_id` = "
            . $order['virtuemart_order_id'];
        $order_info['order_lines'] = $this->db->setQuery($lines_query)->loadAssocList();

        $product_ids = join(',', array_map(function ($line) {
            return $line['virtuemart_product_id'];
        }, $order_info['order_lines']));
        $products_query = "SELECT * FROM `#__virtuemart_products` WHERE `virtuemart_product_id` IN ($product_ids)";
        $products = array();
        foreach ($this->db->setQuery($products_query)->loadAssocList() as $product) {
            $product['images'] = array();
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
        foreach ($this->db->setQuery($images_query)->loadAssocList() as $image) {
            $products[$image['virtuemart_product_id']]['images'][] =
                'http' . (isset ($_SERVER['HTTPS']) ? 's' : '') . '://'
                . $_SERVER['HTTP_HOST'] . '/' . $image['file_url'];
        }

        $customer_query = "SELECT * FROM `#__users` WHERE `id` = " . $order_info['virtuemart_user_id'];
        $customer = $this->db->setQuery($customer_query)->loadAssoc();
        if (!empty ($customer)) {
            unset ($customer['password']);
        }

        $order_data = array(
            'order' => $order_info,
            'products' => array_values($products),
            'customer' => $customer
        );

        $addresses_query = "SELECT * FROM `#__virtuemart_order_userinfos` WHERE `virtuemart_order_id` = "
            . $order['virtuemart_order_id'];
        foreach ($this->db->setQuery($addresses_query)->loadAssocList() as $address) {
            if ($address['address_type'] == 'BT') {
                $order_data['invoice_address'] = $address;
            } else {
                $order_data['delivery_address'] = $address;
            }
        }
        $orders_cache[$order['virtuemart_order_id']] = $order_data;

        return $order_data;
    }

    public function updateOrderInvitesSend($order, $error) {
        $now = time();
        $this->db->setQuery("
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
        $this->db->execute();
    }

}
