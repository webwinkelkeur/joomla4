<?php

interface WebwinkelKeurShopPlatform {

    public function getExtensionName();

    public function getPlatformAbbreviation();

    public function getClientName();

    public function getOrdersToInvite();

    public function getOrderId($order);

    public function getOrderEmail($order);

    public function getOrderCustomerName($order);

    public function getOrderLanguage($order);

    public function getOrderPhones($order);

    public function getOrderTotal($order);

    public function getOrderData($order);

    public function updateOrderInvitesSend($order, $error);

}
