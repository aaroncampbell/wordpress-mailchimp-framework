<?php
/**
 * Example code for attaching Ecommerce Order Information to a Campaign
 *
 * This is just an example.  There is more optional information that you can
 * pass to this method.  See the documentation on the actual method for more
 * information.
 */
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Create the Array that we will pass to our function
$args = array(
	'order' => array(
		// The Order id - a string
		'id'			=> '123',
		// The Campaign ID - string - You can get this using wpMailChimpFramework::campaigns()
		'campaign_id'	=> 'xxxxxxxxxx',
		// The email_id - string - You can get this using wpMailChimpFramework::listMemberInfo()
		'email_id'		=> 'xxxxxxxxxx',
		// The order total - double
		'total'			=> '456.00',
		// A unique ID for the store sending the order in - string
		'store_id'		=> '987',
		// A "nice" name for the store - string
		'store_name'	=> 'Xavisys Test Store',
		// This will hold our line items, which we will attach next
		'items'			=> array(),
	)
);

// We will use this to build our line item and attach it to $args['order']['items']
$item = array(
	// The store's internal Id for the product - integer
	'product_id'	=> 111,
	// The product name for the item.  Mailchimp will update these as they change (based on the produt_id) - string
	'product_name'	=> "Test Product",
	// The store's internal Id for the main category associated with this product - integer
	'category_id'	=> 2,
	// The category name - can be something like "Root - Subcat1 - Subcat5" - string
	'category_name'	=> "Cheap Products",
	// The quantity of the item ordered - double
	'qty'			=> 1,
	// The cost of the item ordered - double
	'cost'			=> "6.50",
);
// Attach the item to our original array
$args['order']['items'][] = $item;

// We will use this to build our line item and attach it to $args['order']['items']
$item = array(
	// The store's internal Id for the product - integer
	'product_id'	=> 221,
	// The product name for the item.  Mailchimp will update these as they change (based on the produt_id) - string
	'product_name'	=> "Expensive Test Product",
	// The store's internal Id for the main category associated with this product - integer
	'category_id'	=> 3,
	// The category name - can be something like "Root - Subcat1 - Subcat5" - string
	'category_name'	=> "Expensive Products",
	// The quantity of the item ordered - double
	'qty'			=> 2,
	// The cost of the item ordered - double
	'cost'			=> "224.75",
);
// Attach the item to our original array
$args['order']['items'][] = $item;

$return = $this->campaignEcommAddOrder($args);
if ( $return->error ) {
	echo "{$return->error} ({$return->code})";
} else {
	echo "Order successfully added to Campaign";
}

/**
 * What you should see: "Order successfully added to Campaign"
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
