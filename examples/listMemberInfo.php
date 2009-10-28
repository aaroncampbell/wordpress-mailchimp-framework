<?php
/**
 * Example code for getting all the information for a particular member of a
 * list
 */
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Create the Array that we will pass to our function
$args = array(
	// The list ID - string - You can get this using wpMailChimpFramework::lists()
	'id'			=> 'xxxxxxxxxx',
	// The member E-Mail address to get the information for - string
	'email_address'	=> 'example@example.com',
);

$return = $this->listMemberInfo($args);
if ( $return->error ) {
	echo "{$return->error} ({$return->code})";
} else {
	var_dump($return);
}

/**
 * What you should see:
 * object(stdClass)#216 (9) {
 *   ["id"]=>
 *   string(10) "xyxyxyxyxy"
 *   ["email"]=>
 *   string(19) "example@example.com"
 *   ["email_type"]=>
 *   string(4) "html"
 *   ["ip_opt"]=>
 *   string(13) "63.225.219.162"
 *   ["ip_signup"]=>
 *   NULL
 *   ["merges"]=>
 *   object(stdClass)#212 (6) {
 *     ["EMAIL"]=>
 *     string(19) "example@example.com"
 *     ["MERGE0"]=>
 *     string(19) "example@example.com"
 *     ["FNAME"]=>
 *     string(5) "First"
 *     ["MERGE1"]=>
 *     string(5) "First"
 *     ["LNAME"]=>
 *     string(4) "Last"
 *     ["MERGE2"]=>
 *     string(4) "Last"
 *   }
 *   ["status"]=>
 *   string(10) "subscribed"
 *   ["timestamp"]=>
 *   string(19) "2009-10-19 00:29:44"
 *   ["lists"]=>
 *   array(0) {
 *   }
 * }
 *
 * If there's a problem, the function will return a stdClass with an error
 * message as "error" and an error code as "code" like this:
 * object(stdClass)#213 (2) {
 *   ["error"]=>
 *   string(31) "Invalid MailChimp List ID: xxxx"
 *   ["code"]=>
 *   int(200)
 * }
 */
