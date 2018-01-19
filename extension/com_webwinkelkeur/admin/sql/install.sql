CREATE TABLE IF NOT EXISTS `#__webwinkelkeur_config` (
    `id` INT NOT NULL,
    `value` TEXT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__webwinkelkeur_virtuemart_order` (
    `virtuemart_order_id` INT NOT NULL,
    `success` TINYINT(1) NOT NULL,
    `tries` INT NOT NULL,
    `time` BIGINT NOT NULL,
    PRIMARY KEY (`virtuemart_order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE IF NOT EXISTS `#__webwinkelkeur_hikashop_order` (
    `hikashop_order_id` INT UNSIGNED NOT NULL,
    `success` TINYINT(1) NOT NULL,
    `tries` INT NOT NULL,
    `time` BIGINT NOT NULL,
    PRIMARY KEY (`hikashop_order_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS `#__webwinkelkeur_invite_error`;

CREATE TABLE `#__webwinkelkeur_invite_error` (
    `id` int NOT NULL AUTO_INCREMENT,
    `url` varchar(255) NOT NULL,
    `response` text NOT NULL,
    `time` bigint NOT NULL,
    `reported` boolean NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `time` (`time`),
    KEY `reported` (`reported`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

UPDATE `#__extensions` SET `enabled` = 1 WHERE `type` = 'plugin' AND `element` = 'webwinkelkeur';


