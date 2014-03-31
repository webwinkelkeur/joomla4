DROP TABLE IF EXISTS `#__webwinkelkeur_config`;

CREATE TABLE `#__webwinkelkeur_config` (
    `id` INT NOT NULL,
    `value` TEXT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

UPDATE `#__extensions` SET `enabled` = 1 WHERE `type` = 'plugin' AND `element` = 'webwinkelkeur';
