<?php

require_once 'lib.php';

function getProductsParam($products) {
    $products_weight = 0;
    foreach ($products as $product) {
        if (!isset($product->product_id)) {
            $product_code = $product->code;
            $res = mysql_query('SELECT id FROM products WHERE code="' . $product_code . '"');
            if (mysql_num_rows($res)>0)
                $res = mysql_fetch_assoc($res);
            $product_id = $res['id'];
            $product_count = $product->quantity;

            $api_calc = true;
        } else {
            $product_id = $product->product_id;
            $product_count = $product->count;

            $api_calc = false;
        }

        $res = mysql_query("SELECT weight FROM products WHERE id=$product_id");
        if (mysql_num_rows($res)>0)
            $res = mysql_fetch_assoc($res);
        if ($product_count > 1) {
            $product_weight = $res['weight'] * $product_count;
        } else {
            $product_weight = $res['weight'];
        }
        $products_weight += $product_weight;
    }

    $products_data['products_weight'] = $products_weight;
    $products_data['api_calc'] = $api_calc;

    return $products_data;
}

function getCalcData($city, $products_weight, $res = null) {
    $edost_calc = new edost_class ();

    $calc_data = $edost_calc -> edost_calc($city, $products_weight, false, empty($res['length']) || $res['length'] == null ? false : $res['length'], empty($res['width']) || $res['width'] == null ? false : $res['width'], empty($res['height']) || $res['height'] == null ? false : $res['height']);

    $cres = mysql_query("SELECT * FROM transport_companies ORDER BY id");

    if (mysql_num_rows($cres) > 0) {
        $result = array();
        while ($row = mysql_fetch_assoc($cres)) {
            for($i = 1; $i <= $calc_data['qty_company']; $i++) {
                if ($calc_data['id'.$i] == $row['tarif_id']) {
                    $result[$row['id']]['id'] = $row['id'];
                    $result[$row['id']]['company'] = iconv('windows-1251', 'utf-8', $calc_data['company'.$i]);
                    $result[$row['id']]['price'] = $calc_data['price'.$i];
                    $result[$row['id']]['day'] = iconv('windows-1251', 'utf-8', $calc_data['day'.$i]);
                } else if ($calc_data['id'.$i] == $row['tarif_id_entrance']) {
                    $result[$row['id']]['entrance']['id'] = $row['id'];
                    $result[$row['id']]['entrance']['company'] = iconv('windows-1251', 'utf-8', $calc_data['company'.$i]);
                    $result[$row['id']]['entrance']['price'] = $calc_data['price'.$i];
                    $result[$row['id']]['entrance']['day'] = iconv('windows-1251', 'utf-8', $calc_data['day'.$i]);
                }
            }
        }

        return $result;
    } else {
        $result['error'] = true;
        return $result;
    }

}