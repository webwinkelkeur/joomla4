<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');
require_once dirname(__FILE__) . '/URLRetriever.php';
class WebwinkelKeurAPI {
    private $shop_id;
    private $api_key;

    public function __construct($shop_id, $api_key) {
        $this->shop_id = (string) $shop_id;
        $this->api_key = (string) $api_key;
    }

    public function invite($order_id, $email, $delay, $lang, $customername, $noremail = false) {
        $parameters = array(
            'id'        => $this->shop_id,
            'password'  => $this->api_key,
            'order'     => $order_id,
            'email'     => $email,
            'delay'     => $delay,
            'lang'      => str_replace('-', '_', $lang),
            'customername' => $customername,
            'client'    => 'virtuemart',
        );

        if($noremail)
            $parameters['noremail'] = true;

        $url = $this->buildURL('https://www.webwinkelkeur.nl/api.php', $parameters);

        $retriever = new Peschar_URLRetriever();
        $response = $retriever->retrieve($url);

        if(!$response) {
            throw new WebwinkelKeurAPIError($url, 'API not reachable.');
        } elseif(preg_match('|^\s*Success:|', $response)) {
            return true;
        } elseif(preg_match('|invite already sent|', $response)) {
            throw new WebwinkelKeurAPIAlreadySentError($url, $response);
        } else {
            throw new WebwinkelKeurAPIError($url, $response);
        }
    }

    private function buildURL($address, $parameters) {
        $query_string = http_build_query($parameters);
        if(strpos($address, '?') === false) {
            return $address . '?' . $query_string;
        } else {
            return $address . '&' . $query_string;
        }
    }
}

class WebwinkelKeurAPIError extends Exception {
    private $url;

    public function __construct($url, $message) {
        $this->url = $url;
        parent::__construct($message);
    }

    public function getURL() {
        return $this->url;
    }
}

class WebwinkelKeurAPIAlreadySentError extends WebwinkelKeurAPIError {}
