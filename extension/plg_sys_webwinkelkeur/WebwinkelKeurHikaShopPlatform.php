<?php

require_once dirname(__FILE__) . '/WebwinkelKeurShopPlatform.php';
if (file_exists(
        rtrim(JPATH_ADMINISTRATOR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'components' . DIRECTORY_SEPARATOR
        . 'com_hikashop' . DIRECTORY_SEPARATOR
        .'helpers' .DIRECTORY_SEPARATOR
        .'helper.php'
)) {
    include_once rtrim(JPATH_ADMINISTRATOR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'components' . DIRECTORY_SEPARATOR
        . 'com_hikashop' . DIRECTORY_SEPARATOR
        .'helpers' .DIRECTORY_SEPARATOR
        .'helper.php';
}

class WebwinkelKeurHikaShopPlatform implements WebwinkelKeurShopPlatform {

    /**
     * @var JDatabaseDriver
     * @since 1.1.0
     */
    private $db;

    private $upload_url = '';

    public function __construct($db) {
        $this->db = $db;
        if (function_exists('hikashop_config') && class_exists('JURI') && class_exists('JPath')) {
            $config = hikashop_config();
            $upload_folder = ltrim(JPath::clean(html_entity_decode($config->get('uploadfolder'))), DIRECTORY_SEPARATOR);
            $upload_folder = rtrim($upload_folder, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
            $this->upload_url = JURI::root() . str_replace(DIRECTORY_SEPARATOR,'/',$upload_folder);
        }
    }

    public function getExtensionName() {
        return 'com_hikashop';
    }

    public function getPlatformAbbreviation() {
        return 'hs';
    }

    public function getClientName() {
        return 'hikashop';
    }

    public function getOrdersToInvite() {
        $min_order_id = $this->getLastOrderBeforePluginId();
        return $this->db->setQuery("
            SELECT
                ho.order_id,
                ho.order_number,
                ho.order_full_price,
                hu.user_email,
                CONCAT(ha.address_firstname, ' ', ha.address_lastname) as customer_name
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
                AND ho.order_id > $min_order_id
        ")->loadAssocList();
    }

    public function getOrderId($order) {
        return $order['order_number'];
    }

    public function getOrderEmail($order) {
        return $order['user_email'];
    }

    public function getOrderTotal($order) {
        return $order['order_full_price'];
    }

    public function getOrderCustomerName($order) {
        return $order['customer_name'];
    }

    public function getOrderLanguage($order) {}

    public function getOrderPhones($order) {
        $order_data = $this->getOrderData($order);
        $phones = array();
        foreach (array('invoice_address', 'delivery_address') as $address) {
            if (isset ($order_data[$address])) {
                $phones[] = $order_data[$address]['address_telephone'];
                $phones[] = $order_data[$address]['address_telephone2'];
            }
        }
        return array_values(array_unique(array_filter($phones)) );
    }

    public function getOrderData($order) {
        static $orders_cache = array();
        if (isset ($orders_cache[$order['order_id']])) {
            return $orders_cache[$order['order_id']];
        }

        $order_query = "SELECT * FROM `#__hikashop_order` WHERE `order_id` = " . $order['order_id'];
        $order_info = $this->db->setQuery($order_query)->loadAssoc();

        $order_lines_query = "SELECT * FROM `#__hikashop_order_product` WHERE `order_id` = " . $order['order_id'];
        $order_info['order_lines'] = array();
        $products = array();
        foreach ($this->db->setQuery($order_lines_query)->loadAssocList() as $line) {
            $order_info['order_lines'][] = $line;
            if ($line['product_id']) {
                $products[] = $this->getProduct($line['product_id']);
            }
        }

        $customer_query  = "
            SELECT * 
            FROM `#__hikashop_user` AS `user`
            LEFT JOIN `#__users` AS `cms_users`
              ON `cms_users`.`id` = `user`.`user_cms_id` 
            LEFT JOIN `#__hikashop_currency` AS `currency`
              ON `currency`.`currency_id` = `user`.`user_currency_id`
            WHERE `user`.`user_id` = " . $order_info['order_user_id'];
        $customer = $this->db->setQuery($customer_query)->loadAssoc();
        unset ($customer['password']);

        $order_data = array(
            'order' => $order_info,
            'products' => $products,
            'customer' => $customer,
            'delivery_address' => array(),
            'invoice_address' => array()
        );

        $address_ids = array_filter(array(
            $order_info['order_billing_address_id'],
            $order_info['order_shipping_address_id']
        ));
        if (!empty ($address_ids)) {
            $address_query =
                "SELECT * FROM `#__hikashop_address` WHERE `address_id` IN (" . join(',', $address_ids) . ")";
            foreach ($this->db->setQuery($address_query)->loadAssocList() as $address) {
                if ($address['address_id'] == $order_info['order_billing_address_id']) {
                    $order_data['invoice_address'] = $address;
                } else {
                    $order_data['delivery_address'] = $address;
                }
            }
        }

        $orders_cache[$order['order_id']] = $order_data;
        return $order_data;
    }

    private function getLastOrderBeforePluginId() {
        $select_query = 'SELECT * FROM `#__webwinkelkeur_hikashop_invites_start`';
        $result = null;
        try {
            $result = $this->db->setQuery($select_query)->loadAssoc();
        } catch (JDatabaseExceptionExecuting $e) {
            if ($e->getCode() != 1146) {
                throw $e;
            }
            $create_query = '
                CREATE TABLE IF NOT EXISTS `#__webwinkelkeur_hikashop_invites_start` (
                    `start_id` INT UNSIGNED NOT NULL DEFAULT 0
                );
            ';
            $this->db->setQuery($create_query)->execute();
        }
        if (empty ($result)) {
            $insert_query = '
                INSERT INTO `#__webwinkelkeur_hikashop_invites_start`
                SELECT COALESCE(MAX(`order_id`), 0) FROM `#__hikashop_order`
            ';
            $this->db->setQuery($insert_query)->execute();
            $result = $this->db->setQuery($select_query)->loadAssoc();
        }
        return $result['start_id'];
    }

    private function getProduct($productId) {
        $query = "SELECT * FROM `#__hikashop_product` WHERE `product_id` = $productId";
        $result = $this->db->setQuery($query)->loadAssoc();
        if ($result && $result['product_parent_id']) {
            return $this->getProduct($result['product_parent_id']);
        }
        $images_query = "
            SELECT `file_path` 
            FROM `#__hikashop_file` 
            WHERE 
              `file_type` = 'product' 
              AND `file_ref_id` = {$result['product_id']}
        ";
        $result['image_urls'] = array();
        foreach ($this->db->setQuery($images_query)->loadColumn() as $image) {
            $result['image_urls'][] = $this->upload_url . $image;
        }
        return $result;
    }

    public function updateOrderInvitesSend($order, $error) {
        $now = time();
        $this->db->setQuery("
                INSERT INTO `#__webwinkelkeur_hikashop_order` SET
                    `hikashop_order_id` = " . (int) $order['order_id'] . ",
                    `success` = " . ($error ? '0' : '1') . ",
                    `tries` = 1,
                    `time` = " . $now . "
                ON DUPLICATE KEY UPDATE
                    `success` = IF(`success` = 1, 1, " . ($error ? '0' : '1') . "),
                    `tries` = `tries` + 1,
                    `time` = " . $now . "
            ")->execute();
    }

}
