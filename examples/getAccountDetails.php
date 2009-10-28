<?php
/**
 * Example code for getting account details
 */
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

$return = $this->getAccountDetails();
if ( $return->error ) {
	echo "{$return->error} ({$return->code})";
} else {
	var_dump($return);
}

/**
 * What you should see:
 * object(stdClass)#202 (17) {
 *   ["user_id"]=>
 *   string(25) "xxxxxxxxxxxxxxxxxxxxxxxxx"
 *   ["username"]=>
 *   string(8) "username"
 *   ["member_since"]=>
 *   string(19) "2008-11-07 03:24:12"
 *   ["is_approved"]=>
 *   bool(false)
 *   ["is_trial"]=>
 *   bool(true)
 *   ["timezone"]=>
 *   string(10) "US/Arizona"
 *   ["plan_type"]=>
 *   string(4) "free"
 *   ["emails_left"]=>
 *   int(2971)
 *   ["pending_monthly"]=>
 *   bool(false)
 *   ["first_payment"]=>
 *   NULL
 *   ["last_payment"]=>
 *   NULL
 *   ["times_logged_in"]=>
 *   int(1219)
 *   ["last_login"]=>
 *   string(19) "2009-10-28 14:52:51"
 *   ["affiliate_link"]=>
 *   string(72) "http://www.mailchimp.com/affiliates/?aid=68e7a06777df63be98d550af3&afl=1"
 *   ["contact"]=>
 *   object(stdClass)#201 (11) {
 *     ["fname"]=>
 *     string(5) "Aaron"
 *     ["lname"]=>
 *     string(8) "Campbell"
 *     ["email"]=>
 *     string(17) "aaron@xavisys.com"
 *     ["company"]=>
 *     string(7) "Xavisys"
 *     ["address1"]=>
 *     NULL
 *     ["address2"]=>
 *     NULL
 *     ["city"]=>
 *     string(7) "Phoenix"
 *     ["state"]=>
 *     string(7) "Arizona"
 *     ["zip"]=>
 *     string(5) "85015"
 *     ["country"]=>
 *     string(3) "USA"
 *     ["url"]=>
 *     NULL
 *   }
 *   ["modules"]=>
 *   array(1) {
 *     [0]=>
 *     object(stdClass)#200 (2) {
 *       ["name"]=>
 *       string(19) "MailChimp Ecomm 360"
 *       ["added"]=>
 *       string(19) "2009-10-21 01:31:01"
 *     }
 *   }
 *   ["orders"]=>
 *   array(1) {
 *     [0]=>
 *     object(stdClass)#199 (5) {
 *       ["order_id"]=>
 *       int(214262)
 *       ["type"]=>
 *       string(26) "Addon: MailChimp Ecomm 360"
 *       ["amount"]=>
 *       int(0)
 *       ["date"]=>
 *       NULL
 *       ["credits_user"]=>
 *       string(4) "0.00"
 *     }
 *   }
 * }
 *
 * If there's a problem, the function will return a stdClass with an error
 * message as "error" and an error code as "code" like this:
 * object(stdClass)#213 (2) {
 *   ["error"]=>
 *   string(31) "Some Error String"
 *   ["code"]=>
 *   int(123)
 * }
 */
