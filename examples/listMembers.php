<?php
/**
 * Example code for getting all the members of a list for a specific status
 *
 * This is just an example.  There's more optional information that you can pass
 * to this method.  See the documentation on the actual method for more
 * information.
 */
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Create the Array that we will pass to our function
$args = array(
	// The list ID - string - You can get this using wpMailChimpFramework::lists()
	'id'			=> 'xxxxxxxxxx',
);

$return = $this->listMembers($args);
if ( $return->error ) {
	echo "{$return->error} ({$return->code})";
} else {
	var_dump($return);
}

/**
 * What you should see:
 * array(2) {
 *   [0]=>
 *   object(stdClass)#202 (2) {
 *     ["email"]=>
 *     string(24) "example-test@example.com"
 *     ["timestamp"]=>
 *     string(19) "2009-10-18 21:29:26"
 *   }
 *   [1]=>
 *   object(stdClass)#201 (2) {
 *     ["email"]=>
 *     string(19) "example@example.com"
 *     ["timestamp"]=>
 *     string(19) "2009-10-19 00:29:44"
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
