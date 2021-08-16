//<?php
/**
 * DbCartInstall
 *
 * Commerce solution installer
 *
 * @category    plugin
 * @author      Pathologic
 * @internal    @events OnWebPageInit,OnManagerPageInit,OnPageNotFound
 * @internal    @modx_category Commerce
 * @internal    @installset base
*/

$modx->db->query("CREATE TABLE IF NOT EXISTS {$modx->getFullTableName('commerce_db_carts')} (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `instance` varchar(255) NOT NULL,
        `hash` varchar(32) NOT NULL,
        `customer_id` INT(10) UNSIGNED NOT NULL DEFAULT 0,
        `updated_at` timestamp NULL DEFAULT NULL,
        PRIMARY KEY (`id`, `hash`, `instance`),
        KEY (`instance`),
        KEY (`customer_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");

$modx->db->query("CREATE TABLE IF NOT EXISTS {$modx->getFullTableName('commerce_db_cart_products')} (
        `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `cart_id` int(10) unsigned NOT NULL,
        `hash` varchar(32) NOT NULL,
        `row` varchar(16) NOT NULL,
        `product_id` int(10) unsigned DEFAULT NULL,
        `title` varchar(255) NOT NULL,
        `price` decimal(16,2) NOT NULL,
        `count` float unsigned NOT NULL DEFAULT 1,
        `options` text,
        `meta` text,
        PRIMARY KEY (`id`),
        UNIQUE KEY `product_hash` (`cart_id`,`hash`),
        KEY `product_id` (`cart_id`,`product_id`),
        CONSTRAINT `commerce_db_cart_products_ibfk_1`
        FOREIGN KEY (`cart_id`)
        REFERENCES {$modx->getFullTableName('commerce_db_carts')} (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;
");

// remove installer
$query = $modx->db->select('id', $modx->getFullTableName('site_plugins'), "`name` = 'DbCartInstall'");

if ($id = $modx->db->getValue($query)) {
   $modx->db->delete($modx->getFullTableName('site_plugins'), "`id` = '$id'");
   $modx->db->delete($modx->getFullTableName('site_plugin_events'), "`pluginid` = '$id'");
};
