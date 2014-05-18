<?php
/**
 * @copyright (C) 2014 Albert Peschar
 * @license GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

defined('_JEXEC') or die('Restricted access');

class Peschar_URLRetriever {
    public function retrieve($url) {
        if(($content = $this->retrieveWithCURL($url)) !== false) {
            return $content;
        } elseif(($content = $this->retrieveWithFile($url)) !== false) {
            return $content;
        } else {
            return false;
        }
    }

    public function retrieveWithCURL($url) {
        if(!function_exists('curl_init')) {
            return false;
        }
        if(!($curl = @curl_init($url))) {
            return false;
        }
        if(!@curl_setopt($curl, CURLOPT_RETURNTRANSFER, true)) {
            return false;
        }
        return @curl_exec($curl);
    }

    public function retrieveWithFile($url) {
        return @file_get_contents($url);
    }
}
