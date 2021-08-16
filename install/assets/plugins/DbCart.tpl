//<?php
/**
 * DbCart Store
 *
 * Commerce addon
 *
 * @category    plugin
 * @version     1.0.0
 * @author      Pathologic
 * @internal    @events OnInitializeCommerce
 * @internal    @modx_category Commerce
 * @internal    @disabled 1
 * @internal    @installset base
*/

use Commerce\Carts\DbCartStore;
use Commerce\Carts\ProductsCart;
use Commerce\Currency;

if ($modx->event->name === 'OnInitializeCommerce') {
    ci()->commerce->currency = new Currency($modx);
    $carts = ci()->carts;
    $carts->registerStore('db', DbCartStore::class);
    $cart = new ProductsCart($modx, 'products', 'db');
    $carts->addCart('products', $cart);
}
