CREATE TABLE IF NOT EXISTS `_DB_PREFIX_omniva_int_order` (
    `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
    `id_shop` int(10) NOT NULL,
    `service_code` varchar(20) NOT NULL,
    `shipment_id` varchar(100) DEFAULT NULL,
    `cart_id` varchar(100) DEFAULT NULL,
    `cod` tinyint(1),
    `cod_amount` float(10),
    `insurance` tinyint(1),
    `carry_service` tinyint(1),
    `doc_return` tinyint(1),
    `own_login` tinyint(1),
    `fragile` tinyint(1),
    `date_add` datetime NOT NULL,
    `date_upd` datetime NOT NULL,
    PRIMARY KEY (`id`),
    KEY `id_shop` (`id_shop`),
    KEY `service_code` (`service_code`)
) ENGINE=_MYSQL_ENGINE_ DEFAULT CHARSET=utf8;