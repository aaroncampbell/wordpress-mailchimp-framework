=== MailChimp Framework ===
Contributors: aaroncampbell
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_xclick&business=paypal%40xavisys%2ecom&item_name=MailChimp%20Framework&no_shipping=0&no_note=1&tax=0&currency_code=USD&lc=US&bn=PP%2dDonationsBF&charset=UTF%2d8
Tags: MailChimp
Requires at least: 2.8
Tested up to: 2.9
Stable tag: 1.0.0

MailChimp integration framework and admin interface as well as WebHook listener.  Requires PHP5.

== Description ==

This plugins gives you a great framework to use for integrating with MailChimp.

Requires PHP5.

== Installation ==

1. Verify that you have PHP5, which is required for this plugin.
1. Upload the whole `mailchimp-framework` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Frequently Asked Questions ==

= How do I send a request to MailChimp? =

The most basic form of communication with MailChimp is a simple ping to check the status of MailChimp.  To send one simply use this code:
<code>
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Send the ping and echo the results
echo wpMailChimpFramework->ping();
</code>

= How do I send a request to MailChimp? =

The most basic form of communication with MailChimp is a simple ping to check the status of MailChimp.  To send one simply use this code:
<code>
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Send the ping and echo the results
echo wpMailChimpFramework->ping();
</code>

= How do I send a request my current lists from MailChimp? =

Requesting your lists is easy, just use this code:
<code>
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Request the lists and var_dump so we can see them
$lists = $this->lists();
var_dump($lists);
</code>

Here is what you can expect to see:
<code>
array(2) {
  [0]=>
  object(stdClass)#140 (12) {
    ["id"]=>
    string(10) "xx55x5555x"
    ["web_id"]=>
    int(55555)
    ["name"]=>
    string(21) "Your Most Recent List"
    ["date_created"]=>
    string(19) "2009-04-03 14:21:43"
    ["member_count"]=>
    int(1)
    ["unsubscribe_count"]=>
    int(0)
    ["cleaned_count"]=>
    int(0)
    ["email_type_option"]=>
    bool(false)
    ["default_from_name"]=>
    string(14) "Some From Name"
    ["default_from_email"]=>
    string(19) "example@example.com"
    ["default_subject"]=>
    string(0) ""
    ["default_language"]=>
    string(3) "eng"
  }
  [1]=>
  object(stdClass)#132 (12) {
    ["id"]=>
    string(10) "5555555xx5"
    ["web_id"]=>
    int(555556)
    ["name"]=>
    string(15) "Your Older List"
    ["date_created"]=>
    string(19) "2009-08-01 19:10:06"
    ["member_count"]=>
    int(9)
    ["unsubscribe_count"]=>
    int(0)
    ["cleaned_count"]=>
    int(0)
    ["email_type_option"]=>
    bool(false)
    ["default_from_name"]=>
    string(7) "Xavisys"
    ["default_from_email"]=>
    string(19) "example@example.com"
    ["default_subject"]=>
    string(20) "Some Default Subject"
    ["default_language"]=>
    string(3) "eng"
  }
}
</code>

= How do I send a request my current campaigns from MailChimp? =

Requesting your campaigns is easy, just use this code:
<code>
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Request the campaigns and var_dump so we can see them
$campaigns = $this->campaigns();
var_dump($campaigns);
</code>

Here is what you can expect to see:
<code>
array(2) {
  [0]=>
  object(stdClass)#211 (22) {
    ["id"]=>
    string(10) "xxxxxx42xx"
    ["web_id"]=>
    int(646505)
    ["list_id"]=>
    string(10) "xx55x5555x"
    ["folder_id"]=>
    int(0)
    ["title"]=>
    string(21) "Test Newsletter Title"
    ["type"]=>
    string(7) "regular"
    ["create_time"]=>
    string(19) "2009-10-18 22:18:08"
    ["send_time"]=>
    NULL
    ["status"]=>
    string(4) "save"
    ["from_name"]=>
    string(14) "Some From Name"
    ["from_email"]=>
    string(19) "example@example.com"
    ["subject"]=>
    string(23) "Test Newsletter Subject"
    ["to_email"]=>
    NULL
    ["archive_url"]=>
    string(22) "http://eepurl.com/xxxx"
    ["emails_sent"]=>
    int(0)
    ["inline_css"]=>
    string(1) "N"
    ["analytics"]=>
    string(6) "google"
    ["analytics_tag"]=>
    string(23) "my_google_analytics_key"
    ["track_clicks_text"]=>
    bool(false)
    ["track_clicks_html"]=>
    bool(true)
    ["track_opens"]=>
    bool(true)
    ["segment_opts"]=>
    array(0) {
    }
  }
  [1]=>
  object(stdClass)#210 (22) {
    ["id"]=>
    string(10) "x52x1x00xx"
    ["web_id"]=>
    int(646449)
    ["list_id"]=>
    string(10) "xx55x5555x"
    ["folder_id"]=>
    int(0)
    ["title"]=>
    string(21) "Test Newsletter Title"
    ["type"]=>
    string(7) "regular"
    ["create_time"]=>
    string(19) "2009-10-18 22:07:15"
    ["send_time"]=>
    NULL
    ["status"]=>
    string(4) "save"
    ["from_name"]=>
    string(14) "Some From Name"
    ["from_email"]=>
    string(19) "example@example.com"
    ["subject"]=>
    string(23) "Test Newsletter Subject"
    ["to_email"]=>
    NULL
    ["archive_url"]=>
    string(22) "http://eepurl.com/xxxx"
    ["emails_sent"]=>
    int(0)
    ["inline_css"]=>
    string(1) "N"
    ["analytics"]=>
    string(6) "google"
    ["analytics_tag"]=>
    string(23) "my_google_analytics_key"
    ["track_clicks_text"]=>
    bool(false)
    ["track_clicks_html"]=>
    bool(true)
    ["track_opens"]=>
    bool(true)
    ["segment_opts"]=>
    array(0) {
    }
  }
}
</code>

= How do I create a campaign in MailChimp? =

Creating a campaign is slightly more complicated, but here is the code:
<code>
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

$ccParams = array();
$ccParams['type'] = 'regular';

$ccParams['options']['list_id'] = 'cc55x5555xs';
$ccParams['options']['subject'] = 'Test Newsletter Subject';
$ccParams['options']['from_email'] = 'example@example.com';
$ccParams['options']['from_name'] = 'Xavisys - Repurposed Marketing';

$ccParams['options']['tracking']=array('opens' => true, 'html_clicks' => true, 'text_clicks' => false);

$ccParams['options']['authenticate'] = true;
$ccParams['options']['analytics'] = array('google'=>'my_google_analytics_key');
$ccParams['options']['title'] = 'Test Newsletter Title';

$ccParams['content'] = array(
	'html'=>'some pretty html <a href="http://xavisys.com">Xavisys</a> content *|UNSUB|* message',
	'text' => 'text http://xavisys.com text text *|UNSUB|*'
);
/*
// OR use a template
$ccParams['content'] = array(
	'html_main'=>'some pretty html content',
	'html_sidecolumn' => 'this goes in a side column',
	'html_header' => 'this gets placed in the header',
	'html_footer' => 'the footer with an *|UNSUB|* message',
	'text' => 'text content text content *|UNSUB|*'
);
$ccParams['options']['template_id'] = "1";
*/

// Create the Campaign and var_dump so we can see the result
$campaign = $wpMailChimpFramework->campaignCreate($ccParams);
var_dump($campaign);
</code>

Here is what you can expect to see:
<code>
array(1) {
  ["id"]=>
  string(10) "xxx0x078xx"
}
</code>

= How do I send a test campaign? =

To test a campaign without actually sending it (each campaign can only be sent once), use this code:
<code>
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

$campaignTestParam = array(
	'cid'			=> 'xxx0x078xx',
	'test_emails'	=> array(
		'example@example.com',
		'example2@example.com',
	)
);

// Send the test campaign and var_dump so we can see the result
$campaign = $wpMailChimpFramework->campaignSendTest($campaignTestParam);
var_dump($campaign);
</code>

Here is what you can expect to see:
<code>
bool(true)
</code>

== Changelog ==

= 1.0.0 =
* Original version released to wordpress.org repository
