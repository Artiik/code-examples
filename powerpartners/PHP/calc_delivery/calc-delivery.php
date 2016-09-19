<?php

require_once '../config.php';
require_once '../lib/edost/edost.php';

openDB();

$city = null;
$products = null;

$products = isset($_REQUEST['products']) ? json_decode($_REQUEST['products']) : array();
$city = iconv("UTF-8", "windows-1251", $_POST['city']);

if (count($products) > 0) {
    $products_data = getProductsParam($products);

    $result = getCalcData($city, $products_data['products_weight']);

    if (!$products_data['api_calc']) {
        if ( count($result) > 0 ) {
            if (!isset($result['error'])) {
                print json_encode(array('result' => '1', 'data' => $result));
            } else {
                print json_encode(array('result' => '2'));
            }
        } else {
            print json_encode(array('result' => '0'));
        }
    } else {
        print json_encode($result);
    }

}

closeDB();


