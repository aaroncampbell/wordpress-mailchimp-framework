<?php
/**
 * Example code for retrieving Ecommerce Orders tracked by
 * campaignEcommAddOrder()
 *
 * This is just an example.  There's more optional information that you can pass
 * to this method.  See the documentation on the actual method for more
 * information.
 */
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Create the Array that we will pass to our function
$args = array(
	// The Campaign ID - string - You can get this using wpMailChimpFramework::campaigns()
	'cid' => 'xxxxxxxxxx'
);

$return = $this->campaignEcommOrders($args);
if ( $return->error ) {
	echo "{$return->error} ({$return->code})";
} else {
	var_dump($return);
}

/**
 * What you should see:
 * array(1) {
 *   [0]=>
 *   object(stdClass)#196 (9) {
 *     ["store_id"]=>
 *     string(3) "987"
 *     ["store_name"]=>
 *     string(18) "Xavisys Test Store"
 *     ["order_id"]=>
 *     string(3) "123"
 *     ["email"]=>
 *     string(19) "xavisys@xavisys.com"
 *     ["order_total"]=>
 *     int(456)
 *     ["tax_total"]=>
 *     int(0)
 *     ["ship_total"]=>
 *     int(0)
 *     ["order_date"]=>
 *     string(19) "2009-10-21 01:44:27"
 *     ["lines"]=>
 *     array(2) {
 *       [0]=>
 *       object(stdClass)#201 (8) {
 *         ["line_num"]=>
 *         int(1)
 *         ["product_id"]=>
 *         int(111)
 *         ["product_name"]=>
 *         string(12) "Test Product"
 *         ["product_sku"]=>
 *         string(0) ""
 *         ["product_category_id"]=>
 *         int(2)
 *         ["product_category_name"]=>
 *         string(14) "Cheap Products"
 *         ["qty"]=>
 *         int(1)
 *         ["cost"]=>
 *         float(6.5)
 *       }
 *       [1]=>
 *       object(stdClass)#200 (8) {
 *         ["line_num"]=>
 *         int(2)
 *         ["product_id"]=>
 *         int(221)
 *         ["product_name"]=>
 *         string(22) "Expensive Test Product"
 *         ["product_sku"]=>
 *         string(0) ""
 *         ["product_category_id"]=>
 *         int(3)
 *         ["product_category_name"]=>
 *         string(18) "Expensive Products"
 *         ["qty"]=>
 *         int(1)
 *         ["cost"]=>
 *         float(224.75)
 *       }
 *     }
 *   }
 * }
 *
 * If there's a problem, the function will return a stdClass with an error
 * message as "error" and an error code as "code" like this:
 * object(stdClass)#202 (2) {
 *   ["error"]=>
 *   string(21) "Invalid Campaign ID: xxxx"
 *   ["code"]=>
 *   int(300)
 * }
 */
