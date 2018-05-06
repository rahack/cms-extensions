CREATE TABLE IF NOT EXISTS `#__upage_sections` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `page_id` int(10) unsigned NOT NULL DEFAULT '0',
    `props` mediumtext NOT NULL,
    `templateKey` varchar(255) NOT NULL DEFAULT '',
    PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `#__upage_manifests` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `version` varchar(255) NOT NULL DEFAULT '',
    `domain` varchar(255) NOT NULL DEFAULT '',
    `manifest` mediumtext NOT NULL,
    PRIMARY KEY (`id`)
);

CREATE TABLE IF NOT EXISTS `#__upage_params` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `params` text NOT NULL,
  PRIMARY KEY (`id`)
);

TRUNCATE TABLE `#__upage_params`;

INSERT INTO `#__upage_params` (`id`, `name`, `params`) VALUES (1, 'com_upage', '{}');
