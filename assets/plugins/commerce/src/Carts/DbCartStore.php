<?php

namespace Commerce\Carts;

use Commerce\Interfaces\CartStore;

class DbCartStore implements CartStore
{
    protected $modx;
    protected $instance = '';
    protected $id = 0;
    protected $cartKey = '';
    protected $items = [];

    public function __construct()
    {
        $this->modx = ci()->modx;
    }

    public function load($instance = 'cart')
    {
        $this->instance = $instance;
        $cartKey = $this->cartKey = 'db_' . $instance;
        if (isset($_COOKIE[$cartKey])) {
            $cart = explode('|', $_COOKIE[$cartKey]);
            if (count($cart) === 2) {
                return $this->loadCart($cart[0], $cart[1]);
            }
        }

        return [];
    }

    public function save(array $items)
    {
        if (!$this->id && !empty($items)) {
            $this->createCart();
        };
        $old = array_keys($this->items);
        $new = array_keys($items);
        $delete = array_diff($old, $new);
        if (!empty($delete)) {
            $rows = \APIhelpers::sanitarIn($delete);
            $this->modx->db->query("DELETE FROM {$this->modx->getFullTableName('commerce_db_cart_products')} WHERE `cart_id` = {$this->id} AND `row` IN ({$rows})");
        }
        $insert = array_diff($new, $old);
        $update = array_intersect($new, $old);
        foreach ($update as $row) {
            if ($items[$row] != $this->items[$row]) {
                $insert[] = $row;
            }
        }
        foreach ($insert as $row) {
            $item = $items[$row];
            $data = [
                'cart_id'    => $this->id,
                'product_id' => (int) $item['id'],
                'hash'       => $item['hash'],
                'row'        => $row,
                'title'      => $this->modx->db->escape(trim(isset($item['title']) ? $item['title'] : $item['name'])),
                'price'      => $this->normalizeFloat($item['price']),
                'count'      => $this->normalizeFloat($item['count']),
                'options'    => json_encode(isset($item['options']) && is_array($item['options']) ? $item['options'] : [], JSON_UNESCAPED_UNICODE),
                'meta'       => json_encode(isset($item['meta']) && is_array($item['meta']) ? $item['meta'] : [], JSON_UNESCAPED_UNICODE),
            ];
            $fields = '`' . implode('`,`', array_keys($data)) . '`';
            $values = \APIhelpers::sanitarIn(array_values($data));
            $this->modx->db->query("INSERT IGNORE INTO {$this->modx->getFullTableName('commerce_db_cart_products')} ({$fields}) VALUES ({$values}) ON DUPLICATE KEY UPDATE `product_id` = {$data['product_id']}, `count` = '{$data['count']}', `title` = '{$data['title']}', `price` = '{$data['price']}', `options` = '{$data['options']}', `meta` = '{$data['meta']}'");
        }

        $this->items = $items;
        if (!empty($delete) || !empty($insert)) {
            $this->updateCart();
        }
    }

    protected function normalizeFloat($value)
    {
        $value = str_replace(',', '.', $value);

        return number_format((float) $value, 6, '.', '');
    }

    protected function loadCart($id, $hash)
    {
        $items = [];
        $instance = $this->modx->db->escape($this->instance);
        $id = (int) $id;
        $hash = $this->modx->db->escape($hash);
        $q = $this->modx->db->query("SELECT `id` FROM {$this->modx->getFullTableName('commerce_db_carts')} WHERE `id` = {$id} AND `hash` = '{$hash}' AND `instance` = '{$instance}'");
        if ($id = $this->modx->db->getValue($q)) {
            $this->id = $id;
            $q = $this->modx->db->query("SELECT * FROM {$this->modx->getFullTableName('commerce_db_cart_products')} WHERE `cart_id` = {$id} ORDER BY `id` ASC");
            while ($row = $this->modx->db->getRow($q)) {
                $items[$row['row']] = [
                    'id'      => $row['product_id'],
                    'name'    => $row['title'],
                    'price'   => $row['price'],
                    'count'   => $row['count'],
                    'hash'    => $row['hash'],
                    'row'     => $row['row'],
                    'options' => \jsonHelper::jsonDecode($row['options'], ['assoc' => true], true),
                    'meta'    => \jsonHelper::jsonDecode($row['meta'], ['assoc' => true], true),
                ];
            }
        } else {
            $this->id = 0;
        }

        $this->items = $items;

        return $items;
    }

    protected function createCart()
    {
        $hash = ci()->commerce->generateRandomString(32);
        $customer_id = (int) $this->modx->getLoginUserID('web');
        $instance = $this->modx->db->escape($this->instance);
        $updated_at = date('Y-m-d H:i:s', time() + $this->modx->getConfig('server_offset_time'));
        $q = $this->modx->db->query("INSERT INTO {$this->modx->getFullTableName('commerce_db_carts')} (`instance`, `hash`, `customer_id`, `updated_at`) VALUES ('{$instance}', '{$hash}', {$customer_id}, '{$updated_at}')");
        if ($id = $this->modx->db->getInsertId($q)) {
            $this->id = $id;
            $this->setCookie($id . '|' . $hash);
        }
    }

    protected function updateCart()
    {
        $id = $this->id;
        $customer_id = (int) $this->modx->getLoginUserID('web');
        $updated_at = date('Y-m-d H:i:s', time() + $this->modx->getConfig('server_offset_time'));
        $update = [];
        $update[] = "`updated_at` = '{$updated_at}'";
        if ($customer_id) {
            $update[] = "`updated_at` = '{$updated_at}'";
        }
        $update = implode(',', $update);
        $this->modx->db->query("UPDATE {$this->modx->getFullTableName('commerce_db_carts')} SET {$update} WHERE `id` = {$id}");
        $this->setCookie($_COOKIE[$this->cartKey]);
    }

    protected function setCookie($cookie = '')
    {
        global $session_cookie_domain;
        $cookieDomain = !empty($session_cookie_domain) ? $session_cookie_domain : '';
        $secure = $this->modx->getConfig('server_protocol') == 'https';
        $_COOKIE[$this->cartKey] = $cookie;
        setcookie($this->cartKey, $cookie, time() + 60 * 60 * 24 * 90, MODX_BASE_URL, $cookieDomain, $secure, true);
    }
}
