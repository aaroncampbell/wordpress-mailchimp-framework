<?php
/**
 * Example code for pinging Mailchimp
 */
// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();

// Send the ping and echo the results
echo wpMailChimpFramework->ping();

/**
 * What you should see: "Everything's Chimpy!"
 */
