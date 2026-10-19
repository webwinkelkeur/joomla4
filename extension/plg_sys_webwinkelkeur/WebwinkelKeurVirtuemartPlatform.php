<?php

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;

require_once dirname(__FILE__) . '/WebwinkelKeurShopPlatform.php';
class WebwinkelKeurVirtuemartPlatform implements WebwinkelKeurShopPlatform {

    /**
     * @var JDatabaseDriver
     * @since 1.0.1
     */
    private $db;

    private $table_list;

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
        $min_order_id = $this->getLastOrderBeforePluginId();
        return $this->db->setQuery("
            SELECT
                vo.virtuemart_order_id,
                vo.order_number,
                vo.order_language,
                vo.order_total,
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
                AND vo.virtuemart_order_id > $min_order_id
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
        $addresses = $this->db->setQuery(
            "SELECT `phone_1`, `phone_2` FROM `#__virtuemart_order_userinfos` WHERE `virtuemart_order_id` = "
            . (int) $order['virtuemart_order_id'] . " ORDER BY `address_type` = 'BT' DESC"
        )->loadAssocList();
        $phones = array();
        foreach ($addresses as $address) {
            $phones[] = $address['phone_1'];
            $phones[] = $address['phone_2'];
        }
        return array_values(array_unique(array_filter($phones)));
    }

    public function getOrderTotal($order) {
        return $order['order_total'];
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

        $products = $this->getProducts($order_info['order_lines'], $order_info['order_language']);

        $customer_query = "SELECT * FROM `#__users` WHERE `id` = " . (int) $order_info['virtuemart_user_id'];
        $customer = $this->db->setQuery($customer_query)->loadAssoc();
        if (!empty ($customer)) {
            unset ($customer['password']);
        }

        $order_data = array(
            'order' => $order_info,
            'products' => $products,
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

    private function getProducts(array $order_lines, $language) {
        $products = array();
        foreach ($order_lines as $line) {
            try {
                $product_id = (int) $line['virtuemart_product_id'];
                if (!$product_id || isset ($products[$product_id])) {
                    continue;
                }
                $product = $this->db->setQuery(
                    "SELECT * FROM `#__virtuemart_products` WHERE `virtuemart_product_id` = $product_id"
                )->loadAssoc();
                if (!$product) {
                    continue;
                }

                $parent = $this->getVariantParent($product);
                $main = $parent ?: $product;
                $main_id = (int) $main['virtuemart_product_id'];
                $product_ids = array_values(array_unique(array_filter(array(
                    $main_id,
                    $product_id,
                    (int) $product['product_parent_id']
                ))));
                $sku = $line['order_item_sku'] !== '' ? $line['order_item_sku'] : $product['product_sku'];
                $name = $line['order_item_name'];

                $variant = null;
                if ($parent) {
                    $variant = $this->filterEmpty(array(
                        'id' => (string) $product_id,
                        'name' => $name,
                        'image_url' => $this->getProductImageUrl(array($product_id)),
                        'sku' => $sku,
                        'gtin' => $product['product_gtin'],
                        'mpn' => $product['product_mpn']
                    ));
                    $name = $this->getProductName($main_id, $language) ?: $name;
                }

                $products[$product_id] = $this->filterEmpty(array(
                    'id' => (string) $main_id,
                    'name' => $name,
                    'url' => Uri::root()
                        . 'index.php?option=com_virtuemart&view=productdetails&virtuemart_product_id=' . $main_id,
                    'image_url' => $this->getProductImageUrl($product_ids),
                    'sku' => $sku,
                    'gtin' => $product['product_gtin'],
                    'mpn' => $main['product_mpn'],
                    'brand' => $this->getProductBrand($product_ids, $language),
                    'price' => (float) $line['product_final_price'],
                    'variant' => $variant
                ));
            } catch (Throwable $e) {
                Log::add(
                    'WebwinkelKeur: could not read VirtueMart product ' . $line['virtuemart_product_id'] . ': ' . $e->getMessage(),
                    Log::WARNING,
                    'webwinkelkeur'
                );
            }
        }
        return array_values($products);
    }

    /**
     * Returns the parent if the product is one of its variants, i.e. the parent is published and offers its
     * children through a "Generic Child Variant" (A) or "Multi Variant" (C) custom field. Children of other
     * parents, like VirtueMart's pattern products, are separate products that only inherit from their parent.
     */
    private function getVariantParent(array $product) {
        $parent_id = (int) $product['product_parent_id'];
        if (!$parent_id) {
            return null;
        }
        $parent = $this->db->setQuery("
            SELECT `p`.*
            FROM `#__virtuemart_products` AS `p`
            WHERE
                `p`.`virtuemart_product_id` = $parent_id
                AND `p`.`published` = 1
                AND EXISTS (
                    SELECT 1
                    FROM `#__virtuemart_product_customfields` AS `pc`
                    INNER JOIN `#__virtuemart_customs` AS `c`
                        ON `c`.`virtuemart_custom_id` = `pc`.`virtuemart_custom_id`
                    WHERE
                        `pc`.`virtuemart_product_id` = `p`.`virtuemart_product_id`
                        AND `pc`.`published` = 1
                        AND `pc`.`disabler` = 0
                        AND `c`.`field_type` IN ('A', 'C')
                        AND `c`.`published` = 1
                )
        ")->loadAssoc();
        return $parent ?: null;
    }

    private function getProductName($product_id, $language) {
        foreach ($this->getLanguageTables('#__virtuemart_products', $language) as $table) {
            $name = $this->db->setQuery(
                "SELECT `product_name` FROM `$table` WHERE `virtuemart_product_id` = " . (int) $product_id
            )->loadResult();
            if ($name) {
                return $name;
            }
        }
        return null;
    }

    private function getProductImageUrl(array $product_ids) {
        $file_url = $this->db->setQuery("
            SELECT `m`.`file_url`
            FROM `#__virtuemart_product_medias` AS `pm`
            INNER JOIN `#__virtuemart_medias` AS `m`
                ON `m`.`virtuemart_media_id` = `pm`.`virtuemart_media_id`
            WHERE
                `pm`.`virtuemart_product_id` IN (" . join(',', $product_ids) . ")
                AND `m`.`file_url` != ''
                AND `m`.`file_is_forSale` = 0
                AND `m`.`published` = 1
            ORDER BY
                FIELD(`pm`.`virtuemart_product_id`, " . join(',', $product_ids) . "),
                `pm`.`ordering`
            LIMIT 1
        ")->loadResult();
        if (!$file_url) {
            return null;
        }
        if (preg_match('~^https?://~i', $file_url)) {
            return $file_url;
        }
        return Uri::root() . ltrim($file_url, '/');
    }

    private function getProductBrand(array $product_ids, $language) {
        foreach ($this->getLanguageTables('#__virtuemart_manufacturers', $language) as $table) {
            $brand = $this->db->setQuery("
                SELECT `mf`.`mf_name`
                FROM `#__virtuemart_product_manufacturers` AS `pm`
                INNER JOIN `$table` AS `mf`
                    ON `mf`.`virtuemart_manufacturer_id` = `pm`.`virtuemart_manufacturer_id`
                WHERE
                    `pm`.`virtuemart_product_id` IN (" . join(',', $product_ids) . ")
                    AND `mf`.`mf_name` != ''
                ORDER BY FIELD(`pm`.`virtuemart_product_id`, " . join(',', $product_ids) . ")
                LIMIT 1
            ")->loadResult();
            if ($brand) {
                return $brand;
            }
        }
        return null;
    }

    private function filterEmpty(array $data) {
        return array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
    }

    /**
     * VirtueMart stores translations in one table per language, e.g. `#__virtuemart_manufacturers_nl_nl`.
     * Returns the existing tables for the order language, the site language and en-GB, in that order.
     */
    private function getLanguageTables($table, $language) {
        if (!isset ($this->table_list)) {
            $this->table_list = array_map('strtolower', $this->db->getTableList());
        }
        $languages = array($language, ComponentHelper::getParams('com_languages')->get('site'), 'en-GB');
        $tables = array();
        foreach (array_filter($languages) as $lang) {
            $lang_table = $table . '_' . strtolower(str_replace('-', '_', $lang));
            if (in_array(strtolower($this->db->replacePrefix($lang_table)), $this->table_list)) {
                $tables[] = $lang_table;
            }
        }
        return array_unique($tables);
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

    private function getLastOrderBeforePluginId() {
        $select_query = 'SELECT * FROM `#__webwinkelkeur_virtuemart_invites_start`';
        $result = null;
        try {
            $result = $this->db->setQuery($select_query)->loadAssoc();
        } catch (RuntimeException $e) {
            $create_query = '
                CREATE TABLE IF NOT EXISTS `#__webwinkelkeur_virtuemart_invites_start` (
                    `start_id` INT UNSIGNED NOT NULL DEFAULT 0
                );
            ';
            $this->db->setQuery($create_query)->execute();
        }
        if (empty ($result)) {
            $insert_query = '
                INSERT INTO `#__webwinkelkeur_virtuemart_invites_start`
                SELECT COALESCE(MAX(`virtuemart_order_id`), 0) FROM `#__virtuemart_orders`
            ';
            $this->db->setQuery($insert_query)->execute();
            $result = $this->db->setQuery($select_query)->loadAssoc();
        }
        return $result['start_id'];
    }

}
