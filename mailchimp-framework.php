<?php
/**
 * Plugin Name: MailChimp Framework
 * Plugin URI: http://xavisys.com/2009/09/wordpress-mailchimp-framework/
 * Description: MailChimp integration framework and admin interface as well as WebHooks listener.  Requires PHP5.
 * Version: 1.0.0
 * Author: Aaron D. Campbell
 * Author URI: http://xavisys.com/
 * Text Domain: mailchimp-framework
 */

/*  Copyright 2009  Aaron D. Campbell  (email : wp_plugins@xavisys.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * wpMailChimpFramework is the class that handles ALL of the plugin functionality.
 * It helps us avoid name collisions
 * http://codex.wordpress.org/Writing_a_Plugin#Avoiding_Function_Name_Collisions
 */
class wpMailChimpFramework
{
	/**
	 * @access private
	 * @var array Plugin settings
	 */
	private $_settings;

	/**
	 * @access private
	 * @var array Errors
	 */
	private $_errors = array();

	/**
	 * @access private
	 * @var array Notices
	 */
	private $_notices = array();

	/**
	 * Static property to hold our singleton instance
	 * @var wpMailChimpFramework
	 */
	static $instance = false;

	/**
	 * @access private
	 * @var string Name used for options
	 */
	private $_optionsName = 'mailchimp-framework';

	/**
	 * @access private
	 * @var string Name used for options
	 */
	private $_optionsGroup = 'mailchimp-framework-options';

	/**
	 * @access private
	 * @var string API Url
	 */
	private $_url = "https://us1.api.mailchimp.com/1.2/";

	/**
	 * @access private
	 * @var string Query var for listener to watch for
	 */
	private $_listener_query_var	= 'mailchimpListener';

	/**
	 * Approved MailChimp plugins get a plugin_id, which is needed to use
	 * the campaignEcommAddOrder API Method
	 *
	 * @access private
	 * @var string MailChimp Plugin ID
	 */
	private $_plugin_id = '1225';

	/**
	 * @access private
	 * @var int Timeout for server calls
	 */
	private $_timeout = 30;

	/**
	 * This is our constructor, which is private to force the use of
	 * getInstance() to make this a Singleton
	 *
	 * @return wpMailChimpFramework
	 */
	private function __construct() {
		$this->_getSettings();
		$this->_fixDebugEmails();

		// Get the datacenter from the API key
		$datacenter = substr( strrchr($this->_settings['apikey'], '-'), 1 );
		if ( empty( $datacenter ) ) {
			$datacenter = "us1";
		}
		// Put the datacenter and version into the url
		$this->_url = "https://{$datacenter}.api.mailchimp.com/{$this->_settings['version']}/";

		/**
		 * Add filters and actions
		 */
		add_filter( 'init', array( $this, 'init_locale') );
		add_action( 'admin_init', array($this,'registerOptions') );
		add_action( 'admin_menu', array($this,'adminMenu') );
		add_action( 'template_redirect', array( $this, 'listener' ));
		add_filter( 'query_vars', array( $this, 'addMailChimpListenerVar' ));
		register_activation_hook( __FILE__, array( $this, 'activatePlugin' ) );
		add_filter( 'pre_update_option_' . $this->_optionsName, array( $this, 'optionUpdate' ), null, 2 );
		add_action( 'admin_notices', array($this, 'showMessages'));
	}

	public function showMessages() {
		$this->showErrors();
		$this->showNotices();
	}
	public function showErrors() {
		$this->_getErrors();
		if ( !empty($this->_errors) ) {
			echo '<div class="error fade">';
			foreach ($this->_errors as $e) {
				echo "<p><strong>{$e->error}</strong> ({$e->code})</p>";
			}
			echo '</div>';
		}
		$this->_emptyErrors();
	}

	public function showNotices() {
		$this->_getNotices();
		if ( !empty($this->_notices) ) {
			echo '<div class="updated fade">';
			foreach ($this->_notices as $n) {
				echo "<p><strong>{$n}</strong></p>";
			}
			echo '</div>';
		}
		$this->_emptyNotices();
	}

	public function optionUpdate( $newvalue, $oldvalue ) {
		if ( !empty( $_POST['get-apikey'] ) ) {
			unset( $_POST['get-apikey'] );

			// If the user set their username at the same time as they requested an API key
			if ( empty($this->_settings['username']) ) {
				$this->_settings['username'] = $newvalue['username'];
			}

			// If the user set their password at the same time as they requested an API key
			if ( empty($this->_settings['password']) ) {
				$this->_settings['password'] = $newvalue['password'];
			}

			// Get API keys, if one doesn't exist, the login will create one
			$keys = $this->apikeys();

			// Set the API key
			if ( !empty( $keys ) ) {
				$newvalue['apikey'] = $keys[0]->apikey;
				$this->_addNotice("API Key Added: {$newvalue['apikey']}");
			}
		} elseif ( !empty( $_POST['expire-apikey'] ) ) {
			unset( $_POST['expire-apikey'] );

			// If the user set their username at the same time as they requested to expire the API key
			if ( empty($this->_settings['username']) ) {
				$this->_settings['username'] = $newvalue['username'];
			}

			// If the user set their password at the same time as they requested to expire the API key
			if ( empty($this->_settings['password']) ) {
				$this->_settings['password'] = $newvalue['password'];
			}

			// Get API keys, if one doesn't exist, the login will create one
			$expired = $this->apikeyExpire();

			// Empty the API key and add a notice
			if ( empty($expired['error']) ) {
				$newvalue['apikey'] = '';
				$this->_addNotice("API Key Expired: {$oldvalue['apikey']}");
			}
		} elseif ( !empty( $_POST['regenerate-security-key']) ) {
			unset( $_POST['expire-apikey'] );

			$newvalue['listener_security_key'] = $this->_generateSecurityKey();
			$this->_addNotice("New Security Key: {$newvalue['listener_security_key']}");
		}

		if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
			wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - optionUpdate', "New Value:\r\n".print_r($newvalue, true)."\r\n\r\nOld Value:\r\n".print_r($oldvalue, true)."\r\n\r\nPOST:\r\n".print_r($_POST, true));
		}
		return $newvalue;
	}

	/**
	 * If an instance exists, this returns it.  If not, it creates one and
	 * retuns it.
	 *
	 * @return wpMailChimpFramework
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function activatePlugin() {
		$this->_updateSettings();
	}

	private function _getSettings() {
		if (empty($this->_settings)) {
			$this->_settings = get_option( $this->_optionsName );
		}
		if ( !is_array( $this->_settings ) ) {
			$this->_settings = array();
		}
		$defaults = array(
			'username'				=> '',
			'password'				=> '',
			'apikey'				=> '',
			'debugging'				=> 'on',
			'debugging_email'		=> '',
			'listener_security_key'	=> $this->_generateSecurityKey(),
			'version'				=> '1.2',
		);
		$this->_settings = wp_parse_args($this->_settings, $defaults);
	}

	private function _generateSecurityKey() {
		return sha1(time());
	}

	private function _updateSettings() {
		update_option( $this->_optionsName, $this->_settings );
	}

	public function getSetting( $settingName, $default = false ) {
		if ( empty( $this->_settings ) ) {
			$this->_getSettings();
		}
		if ( isset( $this->_settings[$settingName] ) ) {
			return $this->_settings[$settingName];
		} else {
			return $default;
		}
	}

	public function registerOptions() {
		register_setting( $this->_optionsGroup, $this->_optionsName );
	}

	public function adminMenu() {
		add_options_page(__('MailChimp Settings', 'mailchimp-framework'), __('MailChimp', 'mailchimp-framework'), 'manage_options', 'MailChimpFramework', array($this, 'options'));
	}

	/**
	 * This is used to display the options page for this plugin
	 */
	public function options() {
?>
		<style type="text/css">
			#wp_mailchimp_framework table tr th a {
				cursor:help;
			}
			.large-text{width:99%;}
			.regular-text{width:25em;}
		</style>
		<div class="wrap">
			<h2><?php _e('MailChimp Options', 'mailchimp-framework') ?></h2>
			<form action="options.php" method="post" id="wp_mailchimp_framework">
				<?php settings_fields( $this->_optionsGroup ); ?>
				<table class="form-table">
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_username">
								<?php _e('MailChimp Username', 'mailchimp-framework'); ?>
								<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_username').toggle(); return false;">
									<?php _e('[?]', 'mailchimp-framework'); ?>
								</a>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[username]" value="<?php echo attribute_escape($this->_settings['username']); ?>" id="<?php echo $this->_optionsName; ?>_username" class="regular-text code" />
							<ol id="mc_username" style="display:none; list-style-type:decimal;">
								<li>
									<?php echo sprintf(__('You must have a MailChimp account.  If you do not have one, <a href="%s">sign up for one</a>.', 'mailchimp-framework'), 'http://www.mailchimp.com/affiliates/?aid=68e7a06777df63be98d550af3&afl=1'); ?>
								</li>
							</ol>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_password">
								<?php _e('MailChimp Password', 'mailchimp-framework') ?>
								<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_password').toggle(); return false;">
									<?php _e('[?]', 'mailchimp-framework'); ?>
								</a>
							</label>
						</th>
						<td>
							<input type="password" name="<?php echo $this->_optionsName; ?>[password]" value="<?php echo attribute_escape($this->_settings['password']); ?>" id="<?php echo $this->_optionsName; ?>_password" class="regular-text code" />
							<ol id="mc_password" style="display:none; list-style-type:decimal;">
								<li>
									<?php echo sprintf(__('You must have a MailChimp account.  If you do not have one, <a href="%s">sign up for one</a>.', 'mailchimp-framework'), 'http://www.mailchimp.com/affiliates/?aid=68e7a06777df63be98d550af3&afl=1'); ?>
								</li>
							</ol>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_apikey">
								<?php _e('MailChimp API Key', 'mailchimp-framework') ?>
								<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_apikey').toggle(); return false;">
									<?php _e('[?]', 'mailchimp-framework'); ?>
								</a>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[apikey]" value="<?php echo attribute_escape($this->_settings['apikey']); ?>" id="<?php echo $this->_optionsName; ?>_apikey" class="regular-text code" />
							<?php if ( empty($this->_settings['apikey']) ) {
							?>
							<input type="submit" name="get-apikey" value="<?php _e('Get API Key', 'mailchimp-framework'); ?>" />
							<?php
							} else {
							?>
							<input type="submit" name="expire-apikey" value="<?php _e('Expire API Key', 'mailchimp-framework'); ?>" />
							<?php
							}
							?>
							<ol id="mc_apikey" style="display:none; list-style-type:decimal;">
								<li>
									<?php echo sprintf(__('You must have a MailChimp account.  If you do not have one, <a href="%s">sign up for one</a>.', 'mailchimp-framework'), 'http://www.mailchimp.com/affiliates/?aid=68e7a06777df63be98d550af3&afl=1'); ?>
								</li>
						</ol>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_version">
								<?php _e('MailChimp API version', 'mailchimp-framework') ?>
								<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_version').toggle(); return false;">
									<?php _e('[?]', 'mailchimp-framework'); ?>
								</a>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[version]" value="<?php echo attribute_escape($this->_settings['version']); ?>" id="<?php echo $this->_optionsName; ?>_version" class="small-text" />
							<small id="mc_version" style="display:none;">
								This is the default version to use if one isn't
								specified.
							</small>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php _e('Debugging Mode', 'mailchimp-framework') ?>
							<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_debugging').toggle(); return false;">
								<?php _e('[?]', 'mailchimp-framework'); ?>
							</a>
						</th>
						<td>
							<input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="on" id="<?php echo $this->_optionsName; ?>_debugging-on"<?php checked('on', $this->_settings['debugging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-on"><?php _e('On', 'mailchimp-framework'); ?></label><br />
							<input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="webhooks" id="<?php echo $this->_optionsName; ?>_debugging-webhooks"<?php checked('webhooks', $this->_settings['debugging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-webhooks"><?php _e('Partial - Only WebHook Messages', 'mailchimp-framework'); ?></label><br />
							<input type="radio" name="<?php echo $this->_optionsName; ?>[debugging]" value="off" id="<?php echo $this->_optionsName; ?>_debugging-off"<?php checked('off', $this->_settings['debugging']); ?> />
							<label for="<?php echo $this->_optionsName; ?>_debugging-off"><?php _e('Off', 'mailchimp-framework'); ?></label><br />
							<small id="mc_debugging" style="display:none;">
								<?php _e('If this is on, debugging messages will be sent to the E-Mail addresses set below.', 'mailchimp-framework'); ?>
							</small>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_debugging_email">
								<?php _e('Debugging E-Mail', 'mailchimp-framework') ?>
								<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_debugging_email').toggle(); return false;">
									<?php _e('[?]', 'mailchimp-framework'); ?>
								</a>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[debugging_email]" value="<?php echo attribute_escape($this->_settings['debugging_email']); ?>" id="<?php echo $this->_optionsName; ?>_debugging_email" class="regular-text" />
							<small id="mc_debugging_email" style="display:none;">
								<?php _e('This is a comma separated list of E-Mail addresses that will receive the debug messages.', 'mailchimp-framework'); ?>
							</small>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<label for="<?php echo $this->_optionsName; ?>_listener_security_key">
								<?php _e('MailChimp WebHook Listener Security Key', 'mailchimp-framework'); ?>
								<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_listener_security_key').toggle(); return false;">
									<?php _e('[?]', 'mailchimp-framework'); ?>
								</a>
							</label>
						</th>
						<td>
							<input type="text" name="<?php echo $this->_optionsName; ?>[listener_security_key]" value="<?php echo attribute_escape($this->_settings['listener_security_key']); ?>" id="<?php echo $this->_optionsName; ?>_listener_security_key" class="regular-text code" />
							<input type="submit" name="regenerate-security-key" value="<?php _e('Regenerate Security Key', 'mailchimp-framework'); ?>" />
							<div id="mc_listener_security_key" style="display:none; list-style-type:decimal;">
								<p><?php echo _e('This is used to make the listener a little more secure. Usually the key that was randomly generated for you is fine, but you can make this whatever you want.', 'mailchimp-framework'); ?></p>
								<p class="error"><?php echo _e('Warning: Changing this will change your WebHook Listener URL below and you will need to update it in your MailChimp account!', 'mailchimp-framework'); ?></p>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php _e('MailChimp WebHook Listener URL', 'mailchimp-framework') ?>
							<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_listener_url').toggle(); return false;">
								<?php _e('[?]', 'mailchimp-framework'); ?>
							</a>
						</th>
						<td>
							<?php echo $this->_getListenerUrl(); ?>
							<div id="mc_listener_url" style="display:none;">
								<p><?php _e('To set this in your MailChimp account:', 'mailchimp-framework'); ?></p>
								<ol style="list-style-type:decimal;">
									<li>
										<?php echo sprintf(__('<a href="%s">Log into your MailChimp account</a>', 'mailchimp-framework'), 'https://admin.mailchimp.com/'); ?>
									</li>
									<li>
										<?php _e('Navigate to your <strong>Lists</strong>', 'mailchimp-framework'); ?>
									</li>
									<li>
										<?php _e("Click the <strong>View Lists</strong> button on the list you want to configure.", 'mailchimp-framework'); ?>
									</li>
									<li>
										<?php _e('Click the <strong>List Tools</strong> menu option at the top.', 'mailchimp-framework'); ?>
									</li>
									<li>
										<?php _e('Click the <strong>WebHooks</strong> link.', 'mailchimp-framework'); ?>
									</li>
									<li>
										<?php echo sprintf(__("Configuration should be pretty straight forward. Copy/Paste the URL shown above into the callback URL field, then select the events and event sources (see the <a href='%s'>MailChimp documentation for more information on events and event sources) you'd like to have sent to you.", 'mailchimp-framework'), 'http://www.mailchimp.com/api/webhooks/'); ?>
									</li>
									<li>
										<?php _e("Click save and you're done!", 'mailchimp-framework'); ?>
									</li>
								</ol>
							</div>
						</td>
					</tr>
					<tr valign="top">
						<th scope="row">
							<?php _e('Current MailChimp Status', 'mailchimp-framework') ?>
							<a title="<?php _e('Click for Help!', 'mailchimp-framework'); ?>" href="#" onclick="jQuery('#mc_status').toggle(); return false;">
								<?php _e('[?]', 'mailchimp-framework'); ?>
							</a>
						</th>
						<td>
							<?php echo $this->ping(); ?>
							<p id="mc_status" style="display:none;"><?php _e("The current status of your server's connection to MailChimp", 'mailchimp-framework'); ?></p>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="Submit" value="<?php _e('Update Options &raquo;', 'mailchimp-framework'); ?>" />
				</p>
			</form>
		</div>
<?php
	}

	private function _getListenerUrl() {
		return get_bloginfo('url').'/?'.$this->_listener_query_var.'='.urlencode($this->_settings['listener_security_key']);
	}

	/**
	 * This function creates a name value pair (nvp) string from a given array,
	 * object, or string.  It also makes sure that all "names" in the nvp are
	 * all caps (which PayPal requires) and that anything that's not specified
	 * uses the defaults
	 *
	 * @param array|object|string $req Request to format
	 *
	 * @return string NVP string
	 */
	private function _prepRequest($req) {
		$defaults = array();
		$req = wp_parse_args( $req );

		//Always include the apikey if we are not logging in
		if ( $req['method'] != 'login' ) {
			if ( !empty($this->_settings['apikey']) ) {
				$defaults['apikey'] = $this->_settings['apikey'];
			} else {
				$defaults['apikey'] = $this->login();
			}
		}
		unset($req['method']);
		/*
		if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
			wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - _prepRequest', "Request:\r\n".print_r($req, true)."\r\n\r\nDefaults:\r\n".print_r($defaults, true)."\r\n\r\nEnd Args:\r\n".print_r(wp_parse_args( $req, $defaults ), true));
		}
		*/
		return wp_parse_args( $req, $defaults );
	}

	/**
	 * Set the timeout.  The default is 30 seconds
	 *
	 * @param int $seconds - Timeout in seconds
	 *
	 * @return bool
	 */
	public function setTimeout($seconds){
		$this->_timeout = absint($seconds);
		return true;
	}

	/**
	 * Get the current timeout.  The default is 30 seconds
	 *
	 * @return int - Timeout in seconds
	 */
	public function getTimeout(){
		return $this->timeout;
	}

	/**
	 * callServer: Function to perform the API call to MailChimp
	 * @param string|array $args Parameters needed for call
	 *
	 * @return array On success return associtive array containing the response from the server.
	 */
	public function callServer( $args ) {

		$reqParams = array(
			'body'		=> $this->_prepRequest($args),
			'sslverify' => false,
			'timeout' 	=> $this->_timeout,
		);

		$_url = "{$this->_url}?method={$args['method']}&output=json";

		$resp = wp_remote_post( $_url, $reqParams );

		// If the response was valid, decode it and return it.  Otherwise return a WP_Error
		if ( !is_wp_error($resp) && $resp['response']['code'] >= 200 && $resp['response']['code'] < 300 ) {
			if (function_exists('json_decode')) {
				$decodedResponse = json_decode( $resp['body'] );
			} else {
				global $wp_json;

				if ( !is_a($wp_json, 'Services_JSON') ) {
					require_once( 'class-json.php' );
					$wp_json = new Services_JSON();
				}

				$decodedResponse =  $wp_json->decode( $resp['body'] );
			}
			// Used for debugging.
			if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
				$request = $this->_sanitizeRequest($reqParams['body']);
				wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - serverCall sent successfully', "Request to {$_url}:\r\n".print_r($request, true)."\r\n\r\nResponse:\r\n".print_r($resp['body'], true)."\r\n\r\nDecoded Response:\r\n".print_r(wp_parse_args($decodedResponse), true));
			}
			//$decodedResponse = wp_parse_args($decodedResponse);
			if ( !empty($decodedResponse->error) ) {
				$this->_addError($decodedResponse);
			}

			return $decodedResponse;
		} else {
			if ( $this->_settings['debugging'] == 'on' && !empty($this->_settings['debugging_email']) ) {
				$request = $this->_sanitizeRequest($reqParams['body']);
				wp_mail($this->_settings['debugging_email'], 'MailChimp Framework - serverCall failed', "Request to {$_url}:\r\n".print_r($request, true)."\r\n\r\nResponse:\r\n".print_r($resp, true));
			}
			if ( !is_wp_error($resp) ) {
				$resp = new WP_Error('http_request_failed', $resp['response']['message'], $resp['response']);
			}
			return $resp;
		}
	}

	/**
	 * Retrieve a set of errors that have occured as a multi-dimensional array.
	 *
	 * @return array Errors, each with an 'error' and 'code'
	 */
	public function getErrors() {
		if ( empty($this->_errors) ) {
			$this->_getErrors();
		}
		return $this->_errors;
	}

	private function _getErrors() {
		$this->_errors = get_option( $this->_optionsName . '-errors', array() );
	}

	private function _addError($error) {
		if ( empty($this->_errors) ) {
			$this->_getErrors();
		}
		$this->_errors[] = $error;
		$this->_setErrors();
	}

	private function _emptyErrors() {
		$this->_errors = array();
		$this->_setErrors();
	}

	private function _setErrors() {
		update_option( $this->_optionsName . '-errors', $this->_errors );
	}

	/**
	 * Retrieve a set of notices that have occured.
	 *
	 * @return array Notices
	 */
	public function getNotices() {
		if ( empty($this->_notices) ) {
			$this->_getNotices();
		}
		return $this->_notices;
	}

	private function _getNotices() {
		$this->_notices = get_option( $this->_optionsName . '-notices', array() );
	}

	private function _addNotice($notice) {
		if ( empty($this->_notices) ) {
			$this->_getNotices();
		}
		$this->_notices[] = $notice;
		$this->_setNotices();
	}

	private function _emptyNotices() {
		$this->_notices = array();
		$this->_setNotices();
	}

	private function _setNotices() {
		update_option( $this->_optionsName . '-notices', $this->_notices );
	}

	private function _sanitizeRequest($request) {
		/**
		 * Hide sensitive data in the debug E-Mails we send
		 */
		//$request['ACCT']	= str_repeat('*', strlen($request['ACCT'])-4) . substr($request['ACCT'], -4);
		//$request['EXPDATE']	= str_repeat('*', strlen($request['EXPDATE']));
		//$request['CVV2']	= str_repeat('*', strlen($request['CVV2']));
		return $request;
	}

	/**
	 * This is our listener.  If the proper query var is set correctly it will
	 * attempt to handle the response.
	 */
	public function listener() {
		// Check that the query var is set and is the correct value.
		if (get_query_var( $this->_listener_query_var ) == $this->_settings['listener_security_key']) {
			$_POST = stripslashes_deep($_POST);
			$this->_processMessage();
			// Stop WordPress entirely
			exit;
		}
	}

	public function _fixDebugEmails() {
		$this->_settings['debugging_email'] = preg_split('/\s*,\s*/', $this->_settings['debugging_email']);
		$this->_settings['debugging_email'] = array_filter($this->_settings['debugging_email'], 'is_email');
		$this->_settings['debugging_email'] = implode(',', $this->_settings['debugging_email']);
	}

	/**
	 * Add our query var to the list of query vars
	 */
	public function addMailChimpListenerVar($public_query_vars) {
		$public_query_vars[] = $this->_listener_query_var;
		return $public_query_vars;
	}

	/**
	 * Throw an action based off the transaction type of the message
	 */
	private function _processMessage() {
		do_action("mailchimp-webhook", $_POST);
		if ( !empty($_POST['type']) ) {
			$specificAction = " and mailchimp-webhook-{$_POST['type']}";
			do_action("mailchimp-webhook-{$_POST['type']}", $_POST);
		}

		// Used for debugging.
		if ( ( $this->_settings['debugging'] == 'on' || $this->_settings['debugging'] == 'webhooks' ) && !empty($this->_settings['debugging_email']) ) {
			wp_mail($this->_settings['debugging_email'], 'MailChimp WebHook Listener Test - _processMessage()', "Actions thrown: mailchimp-webhook{$specificAction}\r\n\r\nPassed to action:\r\n".print_r($_POST, true));
		}
	}

	/**
	 * Add an API Key to your account. Mailchimp will generate a new key for you
	 * it will be returned.  If one is not currently set in the the settings,
	 * the new one will be saved there.  A username and password must be set.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 			username [optional] - MailChimp Username, setting used by default
	 * 			password [optional] - MailChimp Password, setting used by default
	 *
	 * @return string a new API Key that can be immediately used.
	 */
	public function apikeyAdd( $args = null ) {
		$defaults = array(
			'username'	=> $this->_settings['username'],
			'password'	=> $this->_settings['password']
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'apikeyAdd';

		$resp = $this->callServer( $args );

		return $resp;
	}

	/**
	 * Retrieve a list of all MailChimp API Keys for this User
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string username [optional] - MailChimp Username, setting used by default
	 * 		string password [optional] - MailChimp Password, setting used by default
	 * 		bool expired [optional] - whether or not to include expired keys, defaults to false
	 *
	 * @return array an array of API keys including:
	 * 		string apikey The api key that can be used
	 * 		string created_at The date the key was created
	 * 		string expired_at The date the key was expired
	 */
	public function apikeys( $args = null ) {
		$defaults = array(
			'username'	=> $this->_settings['username'],
			'password'	=> $this->_settings['password'],
			'expired'	=> false
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'apikeys';

		return $this->callServer( $args );
	}

	/**
	 * Expire a Specific API Key. Note that if you expire all of your keys, a new, valid one will be created and returned
	 * next time you call login(). If you are trying to shut off access to your account for an old developer, change your
	 * MailChimp password, then expire all of the keys they had access to. Note that this takes effect immediately, so make
	 * sure you replace the keys in any working application before expiring them! Consider yourself warned...
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string username [optional] - MailChimp Username, setting used by default
	 * 		string password [optional] - MailChimp Password, setting used by default
	 * 		string apikey [optional] - If no apikey is specified, the currently specified one is expired
	 *
	 * @return bool true if it worked, otherwise an error is thrown.
	 */
	public function apikeyExpire( $args = null ) {
		$defaults = array(
			'username'	=> $this->_settings['username'],
			'password'	=> $this->_settings['password'],
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'apikeyExpire';

		return $this->callServer( $args );
	}

	/**
	 * Add an API Key to your account. Mailchimp will generate a new key for you
	 * it will be returned.  If one is not currently set in the the settings,
	 * the new one will be saved there.  A username and password must be set.
	 *
	 * @param string|array $args Parameters needed for call
	 *
	 * @return string a new API Key that can be immediately used.
	 */
	public function login( $args = null ) {
		$defaults = array(
			'username'	=> $this->_settings['username'],
			'password'	=> $this->_settings['password']
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'login';

		$resp = $this->callServer( $args );

		return $resp;
	}

	/**
	 * "Ping" the MailChimp API - a simple method you can call that will return a constant value as long as everything is good. Note
	 * than unlike most all of our methods, we don't throw an Exception if we are having issues. You will simply receive a different
	 * string back that will explain our view on what is going on.
	 *
	 * @return string returns "Everything's Chimpy!" if everything is chimpy, otherwise returns an error message
	 */
	public function ping() {
		$args = array();
		$args['method'] = 'ping';
		return $this->callServer( $args );
	}

	/**
	 * Retrieve all of the lists defined for your user account
	 *
	 * @return array list of your Lists and their associated information including:
	 * 		string	id - The list id for this list. This will be used for all other list management functions.
	 * 		int		web_id - The list id used in our web app, allows you to create a link directly to it
	 * 		string	name - The name of the list.
	 * 		date	date_created - The date that this list was created.
	 * 		int		member_count - The number of active members in the given list.
	 * 		int		unsubscribe_count - The number of members who have unsubscribed from the given list.
	 * 		int		cleaned_count - The number of members cleaned from the given list.
	 * 		bool	email_type_option - Whether or not the List supports multiple formats for emails or just HTML
	 * 		string	default_from_name - Default From Name for campaigns using this list
	 * 		string	default_from_email - Default From Email for campaigns using this list
	 * 		string	default_subject - Default Subject Line for campaigns using this list
	 * 		string	default_language - Default Language for this list's forms
	 */
	public function lists() {
		$args = array();
		$args['method'] = 'lists';

		return $this->callServer( $args );
	}

	/**
	 * Create a new draft campaign to send
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	type - the Campaign Type to create - one of "regular", "plaintext", "absplit", "rss", "trans", "auto"
	 * 		array	options - a hash of the standard options for this campaign :
	 * 			string	list_id - the list to send this campaign to- get lists using lists()
	 * 			string	subject - the subject line for your campaign message
	 * 			string	from_email - the From: email address for your campaign message
	 * 			string	from_name - the From: name for your campaign message (not an email address)
	 * 			string	to_email - the To: name recipients will see (not email address)
	 * 			int		template_id [optional] - use this template to generate the HTML content of the campaign
	 * 			int		folder_id [optional] - automatically file the new campaign in the folder_id passed
	 * 			array	tracking [optional] - set which recipient actions will be tracked, as a struct of bool values with the following keys: "opens", "html_clicks", and "text_clicks".  By default, opens and HTML clicks will be tracked.
	 * 			string	title [optional] - an internal name to use for this campaign.  By default, the campaign subject will be used.
	 * 			bool	authenticate [optional] - set to true to enable SenderID, DomainKeys, and DKIM authentication, defaults to false.
	 * 			array	analytics [optional] - if provided, use a struct with "service type" as a key and the "service tag" as a value. For Google, this should be "google"=>"your_google_analytics_key_here". Note that only "google" is currently supported - a Google Analytics tags will be added to all links in the campaign with this string attached. Others may be added in the future
	 * 			bool	auto_footer [optional] Whether or not we should auto-generate the footer for your content. Mostly useful for content from URLs or Imports
	 * 			bool	inline_css [optional] Whether or not css should be automatically inlined when this campaign is sent, defaults to false.
	 * 			bool	generate_text [optional] Whether of not to auto-generate your Text content from the HTML content. Note that this will be ignored if the Text part of the content passed is not empty, defaults to false.
	 * 		array	content - the content for this campaign - use a struct with the following keys:
	 * 			string	html - for pasted HTML content
	 * 				If you chose a template instead of pasting in your HTML content, then use "html_" followed by the template sections as keys - for example, use a key of "html_MAIN" to fill in the "MAIN" section of a template. Supported template sections include:
	 * 				string html_HEADER
	 * 				string html_MAIN
	 * 				string html_SIDECOLUMN
	 * 				string html_FOOTER
	 * 			string	text - for the plain-text version
	 * 			string	url - to have us pull in content from a URL. Note, this will override any other content options - for lists with Email Format options, you'll need to turn on generate_text as well
	 * 			string	archive - to send a Base64 encoded archive file for us to import all media from. Note, this will override any other content options - for lists with Email Format options, you'll need to turn on generate_text as well
	 * 			string	archive_type - [optional] - only necessary for the "archive" option. Supported formats are: zip, tar.gz, tar.bz2, tar, tgz, tbz . If not included, we will default to zip
	 * 		array	segment_opts [optional] - if you wish to do Segmentation with this campaign this array should contain: see campaignSegmentTest(). It's suggested that you test your options against campaignSegmentTest(). Also, "trans" campaigns <strong>do not</strong> support segmentation.
	 * 		array	type_opts [optional]
	 * 			For RSS Campaigns this, array should contain:
	 * 				string url the URL to pull RSS content from - it will be verified and must exist
	 * 			For A/B Split campaigns, this array should contain:
	 * 				string split_test The values to segment based on. Currently, one of: "subject", "from_name", "schedule". NOTE, for "schedule", you will need to call campaignSchedule() separately!
	 * 				string pick_winner How the winner will be picked, one of: "opens" (by the open_rate), "clicks" (by the click rate), "manual" (you pick manually)
	 * 				int wait_units [optional] the default time unit to wait before auto-selecting a winner - use "3600" for hours, "86400" for days. Defaults to 86400.
	 * 				int wait_time [optional] the number of units to wait before auto-selecting a winner - defaults to 1, so if not set, a winner will be selected after 1 Day.
	 * 				int split_size [optional] this is a percentage of what size the Campaign's List plus any segmentation options results in. "schedule" type forces 50%, all others default to 10%
	 * 				string from_name_a [optional] sort of, required when split_test is "from_name"
	 * 				string from_name_b [optional] sort of, required when split_test is "from_name"
	 * 				string from_email_a [optional] sort of, required when split_test is "from_name"
	 * 				string from_email_b [optional] sort of, required when split_test is "from_name"
	 * 				string subject_a [optional] sort of, required when split_test is "subject"
	 * 				string subject_b [optional] sort of, required when split_test is "subject"
	 * 			For AutoResponder campaigns, this array should contain:
	 * 				string offset-units one of "day", "week", "month", "year" - required
	 * 				string offset-time the number of units, must be a number greater than 0 - required
	 * 				string offset-dir either "before" or "after"
	 * 				string event [optional] "signup" (default) to base this on double-optin signup, "date" or "annual" to base this on merge field in the list
	 * 				string event-datemerge [optional] sort of, this is required if the event is "date" or "annual"
	 *
	 * @return array including:
	 * 		string - the ID for the created campaign
	 */
	public function campaignCreate( $args = null ) {
		$defaults = array(
			'segment_opts'	=> NULL,
			'type_opts'		=> NULL
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignCreate';

		return $this->callServer( $args );
	}

	/**
	 * Update just about any setting for a campaign that has <em>not</em> been sent. See campaignCreate() for details
	 *
	 *  Caveats:<br/><ul>
	 *		<li>If you set list_id, all segmentation options will be deleted and must be re-added.</li>
	 *		<li>If you set template_id, you need to follow that up by setting it's 'content'</li>
	 *		<li>If you set segment_opts, you should have tested your options against campaignSegmentTest() as campaignUpdate() will not allow you to set a segment that includes no members.</li></ul>
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to update (can be gathered using campaigns())
	 * 		string	name - the parameter name ( see campaignCreate() )
	 * 		mixed	value - an appropriate value for the parameter ( see campaignCreate() )
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignUpdate( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignUpdate';
		return $this->callServer( $args );
	}

	/**
	 * Get the list of campaigns and their details matching the specified filters
	 *
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string filters [optional] - a hash of filters to apply to this query - all are optional:
	 * 			string	campaign_id [optional] - return a single campaign using a know campaign_id
	 * 			string	list_id [optional] - the list to send this campaign to- get lists using lists()
	 * 			int		folder_id [optional] - only show campaigns from this folder id - get folders using campaignFolders()
	 * 			string	type [optional] - return campaigns of a specific type - one of "regular", "plaintext", "absplit", "rss", "trans", "auto"
	 * 			string	from_name [optional] - only show campaigns that have this "From Name"
	 * 			string	from_email [optional] - only show campaigns that have this "Reply-to Email"
	 * 			string	title [optional] - only show campaigns that have this title
	 * 			string	subject [optional] - only show campaigns that have this subject
	 * 			string	sendtime_start [optional] - only show campaigns that have been sent since this date/time (in GMT) - format is YYYY-MM-DD HH:mm:ss (24hr)
	 * 			string	sendtime_end [optional] - only show campaigns that have been sent before this date/time (in GMT) - format is YYYY-MM-DD HH:mm:ss (24hr)
	 * 			bool	exact [optional] - flag for whether to filter on exact values when filtering, or search within content for filter values - defaults to true
	 * 		int start [optional] - control paging of campaigns, start results at this campaign #, defaults to 1st page of data  (page 0)
	 * 		int limit [optional] - control paging of campaigns, number of campaigns to return with each call, defaults to 25 (max=1000)
	 *
	 * @return array list of campaigns and their associated information including:
	 * 		string	id - Campaign Id (used for all other campaign functions)
	 * 		int		web_id - The Campaign id used in our web app, allows you to create a link directly to it
	 * 		string	title - Title of the campaign
	 * 		string	type - The type of campaign this is (regular,plaintext,absplit,rss,inspection,trans,auto)
	 * 		date	create_time - Creation time for the campaign
	 * 		date	send_time - Send time for the campaign
	 * 		int		emails_sent - Number of emails email was sent to
	 * 		string	status - Status of the given campaign (save,paused,schedule,sending,sent)
	 * 		string	from_name - From name of the given campaign
	 * 		string	from_email - Reply-to email of the given campaign
	 * 		string	subject - Subject of the given campaign
	 * 		string	to_email - Custom "To:" email string using merge variables
	 * 		string	archive_url - Archive link for the given campaign
	 * 		bool	inline_css - Whether or not the campaigns content auto-css-lined
	 * 		string	analytics - Either "google" if enabled or "N" if disabled
	 * 		string	analytcs_tag - The name/tag the campaign's links were tagged with if analytics were enabled.
	 * 		bool	track_clicks_text - Whether or not links in the text version of the campaign were tracked
	 * 		bool	track_clicks_html - Whether or not links in the html version of the campaign were tracked
	 * 		bool	track_opens - Whether or not opens for the campaign were tracked
	 * 		array	segment_opts - ???
	 */
	public function campaigns( $args = null ) {
		$defaults = array(
			'filters'	=> array(),
			'start'		=> 0,
			'limit'		=> 25,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaigns';

		return $this->callServer( $args );
	}

	/**
	 * List all the folders for a user account
	 *
	 * @return array Array of folder structs including:
	 * 		int		folder_id - Folder Id for the given folder, this can be used in the campaigns() function to filter on.
	 * 		string	name - Name of the given folder
	 */
	public function campaignFolders() {
		$args = array();
		$args['method'] = 'campaignFolders';
		return $this->callServer( $args );
	}

	/**
	 * Create a new folder to file campaigns in
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string name - a unique name for a folder
	 *
	 * @return int the folder_id of the newly created folder.
	 */
	public function createFolder( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'createFolder';
		return $this->callServer( $args );
	}


	/**
	 * Given a list and a campaign, get all the relevant campaign statistics (opens, bounces, clicks, etc.)
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the campaign id to pull stats for (can be gathered using campaigns())
	 *
	 * @return array - struct of the statistics for this campaign including:
	 * 		int		syntax_errors - Number of email addresses in campaign that had syntactical errors.
	 * 		int		hard_bounces - Number of email addresses in campaign that hard bounced.
	 * 		int		soft_bounces - Number of email addresses in campaign that soft bounced.
	 * 		int		unsubscribes - Number of email addresses in campaign that unsubscribed.
	 * 		int		abuse_reports - Number of email addresses in campaign that reported campaign for abuse.
	 * 		int		forwards - Number of times email was forwarded to a friend.
	 * 		int		forwards_opens - Number of times a forwarded email was opened.
	 * 		int		opens - Number of times the campaign was opened.
	 * 		date	last_open - Date of the last time the email was opened.
	 * 		int		unique_opens - Number of people who opened the campaign.
	 * 		int		clicks - Number of times a link in the campaign was clicked.
	 * 		int		unique_clicks - Number of unique recipient/click pairs for the campaign.
	 * 		date	last_click - Date of the last time a link in the email was clicked.
	 * 		int		users_who_clicked - Number of unique recipients who clicked on a link in the campaign.
	 * 		int		emails_sent - Number of email addresses campaign was sent to.
	 */
	public function campaignStats( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignStats';
		return $this->callServer( $args );
	}

	/**
	 * Get an array of the urls being tracked, and their click counts for a given campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the campaign id to pull stats for (can be gathered using campaigns())
	 *
	 * @return struct urls will be keys and contain their associated statistics:
	 *		int	clicks - Number of times the specific link was clicked
	 *		int	unique - Number of unique people who clicked on the specific link
	 */
	public function campaignClickStats( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignClickStats';
		return $this->callServer( $args );
	}
	/**
	 * Unschedule a campaign that is scheduled to be sent in the future
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the id of the campaign to unschedule (can be gathered using campaigns())
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignUnschedule( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignUnschedule';
		return $this->callServer( $args );
	}

	/**
	 * Schedule a campaign to be sent in the future
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the id of the campaign to schedule (can be gathered using campaigns())
	 *		string schedule_time - the time to schedule the campaign. For A/B Split "schedule" campaigns, the time for Group A - in YYYY-MM-DD HH:II:SS format in <strong>GMT</strong>
	 * 		string $schedule_time_b [optional] - the time to schedule Group B of an A/B Split "schedule" campaign - in YYYY-MM-DD HH:II:SS format in <strong>GMT</strong>
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignSchedule( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignSchedule';
		return $this->callServer( $args );
	}

	/**
	 * Replicate a campaign.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the id of the campaign to replicate (can be gathered using campaigns())
	 *
	 * @return string the id of the replicated Campaign created, otherwise an error will be thrown
	 */
	public function campaignReplicate( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignReplicate';
		return $this->callServer( $args );
	}

	/**
	 * Delete a campaign. Seriously, "poof, gone!" - be careful!
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the id of the campaign to delete (can be gathered using campaigns())
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignDelete( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignDelete';
		return $this->callServer( $args );
	}

	/**
	 * Resume sending an AutoResponder or RSS campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the id of the campaign to pause (can be gathered using campaigns())
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignResume( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignResume';
		return $this->callServer( $args );
	}

	/**
	 * Pause an AutoResponder orRSS campaign from sending
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the id of the campaign to pause (can be gathered using campaigns())
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignPause( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignPause';
		return $this->callServer( $args );
	}

	/**
	 * Send a given campaign immediately
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string cid - the id of the campaign to resume (can be gathered using campaigns())
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignSendNow( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignSendNow';
		return $this->callServer( $args );
	}

	/**
	 * Send a test of this campaign to the provided email address
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the id of the campaign to test (can be gathered using campaigns())
	 * 		array	test_emails - an array of email address to receive the test message
	 * 		string	send_type [optional] - by default (null) both formats are sent - "html" or "text" send just that format
	 *
	 * @return bool - true on success, false on failure
	 */
	public function campaignSendTest( $args = null ) {
		$defaults = array(
			'test_emails'	=> array(),
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignSendTest';
		return $this->callServer( $args );
	}

	/**
	 * Allows one to test their segmentation rules before creating a campaign using them
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	list_id - the list to test segmentation on - get lists using lists()
	 * 		array	options - with 2 keys:
	 * 			string	match - controls whether to use AND or OR when applying your options - expects "<strong>any</strong>" (for OR) or "<strong>all</strong>" (for AND)
	 * 			array	conditions - up to 10 different criteria to apply while segmenting. Each criteria row should contain 3 keys - "<strong>field</strong>", "<strong>op</strong>", or "<strong>value</strong>" based on these definitions:
	 * 				Field = "<strong>date</strong>" : Select based on various dates we track
	 * 					Valid Op(eration): <strong>eq</strong> (is) / <strong>gt</strong> (after) / <strong>lt</strong> (before)
	 * 					Valid Values:
	 * 					string last_campaign_sent  uses the date of the last campaign sent
	 * 					string campaign_id - uses the send date of the campaign that carriers the Id submitted - see campaigns()
	 * 					string YYYY-MM-DD - ny date in the form of YYYY-MM-DD - <em>note:</em> anything that appears to start with YYYY will be treated as a date
	 * 				Field = "<strong>interests</strong>":
	 * 					Valid Op(erations): <strong>one</strong> / <strong>none</strong> / <strong>all</strong>
	 * 					Valid Values: a comma delimited of interest groups for the list - see listInterestGroups()
	 * 				Field = "<strong>aim</strong>"
	 * 					Valid Op(erations): <strong>open</strong> / <strong>noopen</strong> / <strong>click</strong> / <strong>noclick</strong>
	 * 					Valid Values: "<strong>any</strong>" or a valid AIM-enabled Campaign that has been sent
	 * 				Default Field = A Merge Var. Use <strong>Merge0-Merge30</strong> or the <strong>Custom Tag</strong> you've setup for your merge field - see listMergeVars()
	 * 					Valid Op(erations):
	 * 					 <strong>eq</strong> (=)/<strong>ne</strong>(!=)/<strong>gt</strong>(>)/<strong>lt</strong>(<)/<strong>like</strong>(like '%blah%')/<strong>nlike</strong>(not like '%blah%')/<strong>starts</strong>(like 'blah%')/<strong>ends</strong>(like '%blah')
	 * 					Valid Values: any string
	 *
	 * @return int total The total number of subscribers matching your segmentation options
	 */
	public function campaignSegmentTest( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignSegmentTest';
		return $this->callServer( $args );
	}

	/**
	 * Retrieve all templates defined for your user account
	 *
	 * @return array An array of structs, one for each template including:
	 *		int		id - Id of the template
	 *		string	name - Name of the template
	 *		string	layout - Layout of the template - "basic", "left_column", "right_column", or "postcard"
	 *		array	sections - associative array of editable sections in the template that can accept custom HTML when sending a campaign
	 */
	public function campaignTemplates() {
		$args = array();
		$args['method'] = 'campaignTemplates';
		return $this->callServer( $args );
	}

	/**
	 * Get the top 5 performing email domains for this campaign. Users want more than 5 should use campaign campaignEmailStatsAIM()
	 * or campaignEmailStatsAIMAll() and generate any additional stats they require.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull email domain performance for (can be gathered using campaigns())
	 *
	 * @return array - domains email domains and their associated stats
	 *		string	domain - Domain name or special "Other" to roll-up stats past 5 domains
	 *		int		total_sent - Total Email across all domains - this will be the same in every row
	 *		int		emails - Number of emails sent to this domain
	 *		int		bounces - Number of bounces
	 *		int		opens - Number of opens
	 *		int		clicks - Number of clicks
	 *		int		unsubs - Number of unsubs
	 *		int		delivered - Number of deliveries
	 *		int		emails_pct - Percentage of emails that went to this domain (whole number)
	 *		int		bounces_pct - Percentage of bounces from this domain (whole number)
	 *		int		opens_pct - Percentage of opens from this domain (whole number)
	 *		int		clicks_pct - Percentage of clicks from this domain (whole number)
	 *		int		unsubs_pct - Percentage of unsubs from this domain (whole number)
	 */
	public function campaignEmailDomainPerformance( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignEmailDomainPerformance';
		return $this->callServer( $args );
	}

	/**
	 * Get all email addresses with Hard Bounces for a given campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull bounces for (can be gathered using campaigns())
	 * 		int		start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
	 * 		int		limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
	 *
	 * @return array Arrays of email addresses with Hard Bounces
	 */
	public function campaignHardBounces( $args = null ) {
		$defaults = array(
			'start'		=> 0,
			'limit'		=> 1000,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignHardBounces';

		return $this->callServer( $args );
	}

	/**
	 * Get all email addresses with Soft Bounces for a given campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull bounces for (can be gathered using campaigns())
	 * 		int		start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
	 * 		int		limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
	 *
	 * @return array Arrays of email addresses with Soft Bounces
	 */
	public function campaignSoftBounces( $args = null ) {
		$defaults = array(
			'start'		=> 0,
			'limit'		=> 1000,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignSoftBounces';

		return $this->callServer( $args );
	}

	/**
	 * Get all unsubscribed email addresses for a given campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull unsubscribes for (can be gathered using campaigns())
	 * 		int		start optional for large data sets, the page number to start at - defaults to 1st page of data (page 0)
	 * 		int		limit optional for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
	 *
	 * @return array list of email addresses that unsubscribed from this campaign
	 */
	public function campaignUnsubscribes( $args = null ) {
		$defaults = array(
			'start'		=> 0,
			'limit'		=> 1000,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignUnsubscribes';

		return $this->callServer( $args );
	}

	/**
	 * Get all email addresses that complained about a given campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull abuse reports for (can be gathered using campaigns())
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 500, upper limit set at 1000
	 * 		string	since [optional] - pull only messages since this time - use YYYY-MM-DD HH:II:SS format in <strong>GMT</strong>
	 *
	 * @return array reports the abuse reports for this campaign
	 * 		string	date - date/time the abuse report was received and processed
	 * 		string	email - the email address that reported abuse
	 * 		string	type - an internal type generally specifying the orginating mail provider - may not be useful outside of filling report views
	 */
	public function campaignAbuseReports( $args = null ) {
		$defaults = array(
			'start'	=> 0,
			'limit'	=> 500,
			'since'	=> null,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignAbuseReports';

		return $this->callServer( $args );
	}

	/**
	 * Retrieve the text presented in our app for how a campaign performed and any advice we may have for you - best
	 * suited for display in customized reports pages. Note: some messages will contain HTML - clean tags as necessary
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull advice text for (can be gathered using campaigns())
	 *
	 * @return array advice on the campaign's performance
	 * 		string	msg - the advice message
	 * 		string	type - the "type" of the message. one of: negative, positive, or neutral
	 */
	public function campaignAdvice( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignAdvice';

		return $this->callServer( $args );
	}

	/**
	 * Retrieve the Google Analytics data we've collected for this campaign. Note, requires Google Analytics Add-on to be installed and configured.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull bounces for (can be gathered using campaigns())
	 *
	 * @return array analytics we've collected for the passed campaign.
	 * 			int		visits number of visits
	 * 			int		pages number of page views
	 * 			int		new_visits new visits recorded
	 * 			int		bounces vistors who "bounced" from your site
	 * 			double	time_on_site
	 * 			int		goal_conversions number of goals converted
	 * 			double	goal_value value of conversion in dollars
	 * 			double	revenue revenue generated by campaign
	 * 			int		transactions number of transactions tracked
	 * 			int		ecomm_conversions number Ecommerce transactions tracked
	 * 			array	goals an array containing goal names and number of conversions
	 */
	public function campaignAnalytics( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignAnalytics';

		return $this->callServer( $args );
	}

	/**
	 * Retrieve the full bounce messages for the given campaign. Note that this can return very large amounts
	 * of data depending on how large the campaign was and how much cruft the bounce provider returned. Also,
	 * message over 30 days old are subject to being removed
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull bounces for (can be gathered using campaigns())
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 25, upper limit set at 50
	 * 		string	since [optional] - pull only messages since this time - use YYYY-MM-DD HH:II:SS format in <strong>GMT</strong>
	 *
	 * @return array bounces the full bounce messages for this campaign
	 * 		string	date - date/time the bounce was received and processed
	 * 		string	email - the email address that bounced
	 * 		string	message - the entire bounce message received
	 */
	public function campaignBounceMessages( $args = null ) {
		$defaults = array(
			'start'	=> 0,
			'limit'	=> 25,
			'since'	=> null,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignBounceMessages';

		return $this->callServer( $args );
	}

	/**
	 * Get the content (both html and text) for a campaign either as it would appear in the campaign archive or as the raw, original content
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to get content for (can be gathered using campaigns())
	 * 		bool	for_archive [optional] - controls whether we return the Archive version (true) or the Raw version (false), defaults to true
	 *
	 * @return struct Struct containing all content for the campaign including:
	 * 		string	html - The HTML content used for the campgain with merge tags intact
	 * 		string	text - The Text content used for the campgain with merge tags intact
	 */
	public function campaignContent( $args = null ) {
		$defaults = array(
			'for_archive'	=> true,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignContent';

		return $this->callServer( $args );
	}

	/**
	 * Retrieve the list of email addresses that opened a given campaign with how many times they opened - note: this AIM function is free and does
	 * not actually require the AIM module to be installed
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to get opens for (can be gathered using campaigns())
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
	 *
	 * @return array Array of structs containing email addresses and open counts
	 * 		string	email - Email address that opened the campaign
	 * 		int		open_count - Total number of times the campaign was opened by this email address
	 */
	public function campaignOpenedAIM( $args = null ) {
		$defaults = array(
			'start'	=> 0,
			'limit'	=> 1000,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignContent';

		return $this->callServer( $args );
	}

	/**
	 * Retrieve the list of email addresses that did not open a given campaign
	 *
	 * @section Campaign AIM
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to get no opens for (can be gathered using campaigns())
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
	 *
	 * @return array list of email addresses that did not open a campaign
	 */
	public function campaignNotOpenedAIM( $args = null ) {
		$defaults = array(
			'start'	=> 0,
			'limit'	=> 1000,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignNotOpenedAIM';

		return $this->callServer( $args );
	}

	/**
	 * Return the list of email addresses that clicked on a given url, and how many times they clicked
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to get click stats for (can be gathered using campaigns())
	 * 		string	url - the URL of the link that was clicked on
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 1000, upper limit set at 15000
	 *
	 * @return array Array of structs containing email addresses and click counts
	 * 		string	email - Email address that opened the campaign
	 * 		int		clicks - Total number of times the URL was clicked on by this email address
	 */
	public function campaignClickDetailAIM( $args = null ) {
		$defaults = array(
			'start'	=> 0,
			'limit'	=> 1000,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignClickDetailAIM';

		return $this->callServer( $args );
	}

	/**
	 * Given a campaign and email address, return the entire click and open history with timestamps, ordered by time
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to get stats for (can be gathered using campaigns())
	 *
	 * @return array Array of structs containing the actions (opens and clicks) that the email took, with timestamps
	 * 		string	action - The action taken (open or click)
	 * 		date	timestamp - Time the action occurred
	 * 		string	url - For clicks, the URL that was clicked
	 */
	public function campaignEmailStatsAIM( $args = null ) {
		$defaults = array(
			'for_archive'	=> true,
		);
		$args = wp_parse_args( $args );
		$args['method'] = 'campaignEmailStatsAIM';

		return $this->callServer( $args );
	}

	/**
	 * Given a campaign and correct paging limits, return the entire click and open history with timestamps, ordered by time,
	 * for every user a campaign was delivered to.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to get stats for (can be gathered using campaigns())
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 100, upper limit set at 1000
	 *
	 * @return array Array of structs containing actions  (opens and clicks) for each email, with timestamps
	 * 		string	action - The action taken (open or click)
	 * 		date	timestamp - Time the action occurred
	 * 		string	url - For clicks, the URL that was clicked
	 */
	public function campaignEmailStatsAIMAll( $args = null ) {
		$defaults = array(
			'start'	=> 0,
			'limit'	=> 100,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignEmailStatsAIMAll';

		return $this->callServer( $args );
	}

	/**
	 * Attach Ecommerce Order Information to a Campaign. This is used by ecommerce package plugins
	 * provided by MailChimp, but you can use it to help track the profitability of a campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		array	order - an array of information pertaining to the order that has completed. Use the following keys:
	 *	 		string	id - the Order Id
	 *	 		string	campaign_id - the Campaign Id to track this order with (see the "mc_cid" query string variable a campaign passes)
	 *	 		string	email_id - the Email Id of the subscriber we should attach this order to (see the "mc_eid" query string variable a campaign passes)
	 *	 		double	total - The Order Total (ie, the full amount the customer ends up paying)
	 *	 		double	shipping [optional] - the total paid for Shipping Fees
	 *	 		double	tax [optional] - the total tax paid
	 *	 		string	store_id - a unique id for the store sending the order in
	 *	 		string	store_name [optional] - a "nice" name for the store - typically the base web address (ie, "store.mailchimp.com"). We will automatically update this if it changes (based on store_id)
	 *	 		string	plugin_id - the MailChimp assigned Plugin Id. Get yours by <a href="/api/register.php">registering here</a>
	 *	 		array	items - the individual line items for an order using these keys:
	 *	 			int		line_num [optional] - the line number of the item on the order. We will generate these if they are not passed
	 *	 			int		product_id - the store's internal Id for the product. Lines that do no contain this will be skipped
	 *	 			string	product_name - the product name for the product_id associated with this item. We will auto update these as they change (based on product_id)
	 *	 			int		category_id - the store's internal Id for the (main) category associated with this product. Our testing has found this to be a "best guess" scenario
	 *	 			string	category_name - the category name for the category_id this product is in. Our testing has found this to be a "best guess" scenario. Our plugins walk the category heirarchy up and send "Root - SubCat1 - SubCat4", etc.
	 *	 			double	qty - the quantity of the item ordered
	 *	 			double	cost - the cost of a single item (ie, not the extended cost of the line)
	 *
	 * @return bool true if the data is saved, otherwise an error is thrown.
	 */
	public function campaignEcommAddOrder( $args = null ) {
		/**
		 * @todo: Make store_id a setting?
		 */
		$orderDefaults = array(
			'plugin_id'	=> $this->_plugin_id
		);
		$args['order'] = wp_parse_args( $args['order'], $orderDefaults);
		$args['method'] = 'campaignEcommAddOrder';

		return $this->callServer( $args );
	}

	/**
	 * Retrieve the Ecommerce Orders tracked by campaignEcommAddOrder()
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	cid - the campaign id to pull Ecommerce Orders for (can be gathered using campaigns())
	 * 		int		start [optional] for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
	 * 		int		limit [optional] for large data sets, the number of results to return - defaults to 100, upper limit set at 500
	 * 		string	since [optional] pull only messages since this time - use YYYY-MM-DD HH:II:SS format in <strong>GMT</strong>
	 *
	 * @return array orders the orders and their details that we've collected for this campaign
	 * 		string	store_id - the store id generated by the plugin used to uniquely identify a store
	 * 		string	store_name - the store name collected by the plugin - often the domain name
	 * 		string	order_id - the internal order id the store tracked this order by
	 * 		string	email - the email address that received this campaign and is associated with this order
	 * 		double	order_total - the order total
	 * 		double	tax_total - the total tax for the order (if collected)
	 * 		double	ship_total - the shipping total for the order (if collected)
	 * 		string	order_date - the date the order was tracked - from the store if possible, otherwise the GMT time we recieved it
	 * 		array	lines - containing detail of the order - product, category, quantity, item cost
	 */
	public function campaignEcommOrders( $args = null ) {
		$defaults = array(
			'start'		=> 0,
			'limit'		=> 100,
			'since'		=> null,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'campaignEcommOrders';

		return $this->callServer( $args );
	}

	/**
	 * Return the Webhooks configured for the given list
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 *
	 * @return array list of webhooks including:
	 * 		string	url - the URL for this Webhook
	 * 		array	actions - the possible actions and whether they are enabled
	 * 		array	sources - the possible sources and whether they are enabled
	 */
	public function listWebhooks( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listWebhooks';

		return $this->callServer( $args );
	}

	/** Add a new Webhook URL for the given list
	 *
	 * @section List Related
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	url - a valid URL for the Webhook - it will be validated. note that a url may only exist on a list once.
	 * 		array	actions - optional a hash of actions to fire this Webhook for
	 * 			bool subscribe optional as subscribes occur, defaults to true
	 * 			bool unsubscribe optional as subscribes occur, defaults to true
	 * 			bool profile optional as profile updates occur, defaults to true
	 * 			bool cleaned optional as emails are cleaned from the list, defaults to true
	 * 			bool upemail optional when  subscribers change their email address, defaults to true
	 * 		array	sources - optional a hash of sources to fire this Webhook for
	 * 			bool user optional user/subscriber initiated actions, defaults to true
	 * 			bool admin optional admin actions in our web app, defaults to true
	 * 			bool api optional actions that happen via API calls, defaults to false
	 *
	 * @return bool - true on success, false on failure
	 */
	public function listWebhookAdd( $args = null ) {
		$defaults = array(
			'actions'	=> array(),
			'sources'	=> array(),
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listWebhookAdd';

		return $this->callServer( $args );
	}

	/** Delete an existing Webhook URL from a given list
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	url - the URL of a Webhook on this list
	 *
	 * @return bool - true on success, false on failure
	 */
	public function listWebhookDel( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listWebhookDel';

		return $this->callServer( $args );
	}

	/**
	 * Get all of the list members for a list that are of a particular status
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	status - the status to get members for - one of(subscribed, unsubscribed, cleaned, updated), defaults to subscribed
	 * 		string	since [optional] - pull all members whose status (subscribed/unsubscribed/cleaned) has changed or whose profile (updated) has changed since this date/time (in GMT) - format is YYYY-MM-DD HH:mm:ss (24hr)
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 100, upper limit set at 15000
	 *
	 * @return array Array of list member structs containing:
	 * 		string	email - Member email address
	 * 		date	timestamp - timestamp of their associated status date (subscribed, unsubscribed, cleaned, or updated) in GMT
	 */
	public function listMembers( $args = null ) {
		$defaults = array(
			'status'	=> 'subscribed',
			'since'		=> null,
			'start'		=> 0,
			'limit'		=> 100,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listMembers';

		return $this->callServer( $args );
	}

	/**
	 * Get all the information for a particular member of a list
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	email_address - the member email address to get information for
	 *
	 * @return array array of list member info (see Returned Fields for details)
	 * 		string	email - The email address associated with this record
	 * 		string	email_type - The type of emails this customer asked to get: html or text
	 * 		array	merges - An associative array of all the merge tags and the data for those tags for this email address. <em>Note</em>: Interest Groups are returned as comma delimited strings - if a group name contains a comma, it will be escaped with a backslash. ie, "," =&gt; "\,"
	 * 		string	status - The subscription status for this email address, either subscribed, unsubscribed or cleaned
	 * 		string	ip_opt - IP Address this address opted in from.
	 * 		string	ip_signup - IP Address this address signed up from.
	 * 		array	lists - An associative array of the other lists this member belongs to - the key is the list id and the value is their status in that list.
	 * 		date	timestamp - The time this email address was added to the list
	 */
	public function listMemberInfo( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listMemberInfo';

		return $this->callServer( $args );
	}

	/**
	 * Edit the email address, merge fields, and interest groups for a list member
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	email_address - the current email address of the member to update
	 * 		array	merge_vars - array of new field values to update the member with.  Use "EMAIL" to update the email address and "INTERESTS" to update the interest groups
	 * 		string	email_type [optional] - change the email type preference for the member ("html" or "text").  Leave blank to keep the existing preference
	 * 		bool	replace_interests [optional] - flag to determine whether we replace the interest groups with the updated groups provided, or we add the provided groups to the member's interest groups (defaults to true)
	 *
	 * @return bool true on success, false on failure.
	 */
	public function listUpdateMember( $args = null ) {
		$defaults = array(
			'email_type'		=> '',
			'replace_interests'	=> true,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listUpdateMember';

		return $this->callServer( $args );
	}

	/**
	 * Subscribe a batch of email addresses to a list at once
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		array	batch - an array of structs for each address to import with two special keys:
	 * 			string	EMAIL - the email address
	 * 			string	EMAIL_TYPE - for the email type option (html or text)
	 * 		bool	double_optin [optional] - flag to control whether to send an opt-in confirmation email - defaults to true
	 * 		bool	update_existing [optional] - flag to control whether to update members that are already subscribed to the list or to return an error, defaults to false (return error)
	 * 		bool	replace_interests [optional] - flag to determine whether we replace the interest groups with the updated groups provided, or we add the provided groups to the member's interest groups (defaults to true)
	 *
	 * @return struct Array of result counts and any errors that occurred
	 * 		int		success_count - Number of email addresses that were succesfully added/updated
	 * 		int		error_count - Number of email addresses that failed during addition/updating
	 * 		array	errors - Array of error structs. Each error struct will contain "code", "message", and the full struct that failed
	 */
	public function listBatchSubscribe( $args = null ) {
		$defaults = array(
			'double_optin'		=> true,
			'update_existing'	=> false,
			'replace_interests'	=> true,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listBatchSubscribe';

		return $this->callServer( $args );
	}

	/**
	 * Unsubscribe a batch of email addresses to a list
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		array	emails - array of email addresses to unsubscribe
	 * 		bool	delete_member [optional] - flag to completely delete the member from your list instead of just unsubscribing, default to false
	 * 		bool	send_goodbye [optional] - flag to send the goodbye email to the email addresses, defaults to true
	 * 		bool	send_notify [optional] - flag to send the unsubscribe notification email to the address defined in the list email notification settings, defaults to false
	 *
	 * @return struct Array of result counts and any errors that occurred
	 * 		int		success_count - Number of email addresses that were succesfully added/updated
	 * 		int		error_count - Number of email addresses that failed during addition/updating
	 * 		array	errors - Array of error structs. Each error struct will contain "code", "message", and "email"
	 */
	public function listBatchUnsubscribe( $args = null ) {
		$defaults = array(
			'delete_member'	=> false,
			'send_goodbye'	=> true,
			'send_notify'	=> false,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listBatchUnsubscribe';

		return $this->callServer( $args );
	}


	/**
	 * Get all email addresses that complained about a given campaign
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to pull abuse reports for (can be gathered using lists())
	 * 		int		start [optional] - for large data sets, the page number to start at - defaults to 1st page of data  (page 0)
	 * 		int		limit [optional] - for large data sets, the number of results to return - defaults to 500, upper limit set at 1000
	 * 		string	since [optional] - pull only messages since this time - use YYYY-MM-DD HH:II:SS format in <strong>GMT</strong>
	 *
	 * @return array reports the abuse reports for this campaign
	 * 		string	date - date/time the abuse report was received and processed
	 * 		string	email - the email address that reported abuse
	 * 		string	campaign_id - the unique id for the campaign that reporte was made against
	 * 		string	type - an internal type generally specifying the orginating mail provider - may not be useful outside of filling report views
	 */
	public function listAbuseReports( $args = null ) {
		$defaults = array(
			'since'		=> null,
			'start'		=> 0,
			'limit'		=> 500,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listAbuseReports';

		return $this->callServer( $args );
	}

	/**
	 * Access the Growth History by Month for a given list.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 *
	 * @return array array of months and growth
	 * 		string	month - The Year and Month in question using YYYY-MM format
	 * 		int		existing - number of existing subscribers to start the month
	 * 		int		import - number of subscribers imported during the month
	 * 		int		optins - number of subscribers who opted-in during the month
	 */
	public function listGrowthHistory( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listGrowthHistory';

		return $this->callServer( $args );
	}

	/**
	 * Get the list of merge tags for a given list, including their name, tag, and required setting
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 *
	 * @return array list of merge tags for the list
	 * 		string	name - Name of the merge field
	 * 		string	req - Denotes whether the field is required (Y) or not (N)
	 * 		string	tag - The merge tag that's used for forms and listSubscribe() and listUpdateMember()
	 */
	public function listMergeVars( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listMergeVars';

		return $this->callServer( $args );
	}

	/**
	 * Add a new merge tag to a given list
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	tag - The merge tag to add, e.g. FNAME
	 * 		string	name - The long description of the tag being added, used for user displays
	 * 		array	req [optional] - Various options for this merge var. <em>note:</em> for historical purposes this can also take a "boolean"
	 * 			string	field_type [optional] - one of: text, number, radio, dropdownn, date, address, phone, url, imageurl - defaults to text
	 * 			bool	req [optional] - indicates whether the field is required - defaults to false
	 * 			bool	public [optional] - indicates whether the field is displayed in public - defaults to true
	 * 			bool	show [optional] - indicates whether the field is displayed in the app's list member view - defaults to true
	 * 			string	default_value [optional] - the default value for the field. See listSubscribe() for formatting info. Defaults to blank
	 * 			array	choices [optional] - kind of - an array of strings to use as the choices for radio and dropdown type fields
	 *
	 * @return bool true if the request succeeds, otherwise an error will be thrown
	 */
	public function listMergeVarAdd( $args = null ) {
		$defaults = array(
			'req'	=> array(),
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listMergeVarAdd';

		return $this->callServer( $args );
	}

	/**
	 * Update most parameters for a merge tag on a given list. You cannot currently change the merge type
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	tag - The merge tag to update
	 * 		array	options - The options to change for a merge var. See listMergeVarAdd() for valid options
	 *
	 * @return bool true if the request succeeds, otherwise an error will be thrown
	 */
	public function listMergeVarUpdate( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listMergeVarUpdate';

		return $this->callServer( $args );
	}

	/**
	 * Delete a merge tag from a given list and all its members. Seriously - the data is removed from all members as well!
	 * Note that on large lists this method may seem a bit slower than calls you typically make.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	tag - The merge tag to delete
	 *
	 * @return bool true if the request succeeds, otherwise an error will be thrown
	 */
	public function listMergeVarDel( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listMergeVarDel';

		return $this->callServer( $args );
	}

	/**
	 * Get the list of interest groups for a given list, including the label and form information
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 *
	 * @return struct list of interest groups for the list
	 * 		string	name - Name for the Interest groups
	 * 		string	form_field - Gives the type of interest group: checkbox,radio,select
	 * 		array	groups - Array of the group names
	 */
	public function listInterestGroups( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listInterestGroups';

		return $this->callServer( $args );
	}

	/**
	 * Add a single Interest Group - if interest groups for the List are not yet enabled, adding the first
	 * group will automatically turn them on.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	group_name - the interest group to add
	 *
	 * @return bool true if the request succeeds, otherwise an error will be thrown
	 */
	public function listInterestGroupAdd( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listInterestGroupAdd';

		return $this->callServer( $args );
	}

	/**
	 * Delete a single Interest Group - if the last group for a list is deleted, this will also turn groups for the list off.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	group_name - the interest group to delete
	 *
	 * @return bool true if the request succeeds, otherwise an error will be thrown
	 */
	public function listInterestGroupDel( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listInterestGroupDel';

		return $this->callServer( $args );
	}

	/**
	 * Change the name of an Interest Group
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	old_name - the interest group name to be changed
	 * 		string	new_name - the new interest group name to be set
	 *
	 * @return bool true if the request succeeds, otherwise an error will be thrown
	 */
	public function listInterestGroupUpdate( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'listInterestGroupUpdate';

		return $this->callServer( $args );
	}

	/**
	 * Subscribe the provided email to a list. By default this sends a confirmation email - you will not see new members until the link contained in it is clicked!
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	email_address - the email address to subscribe
	 * 		array	merge_vars - array of merges for the email (FNAME, LNAME, etc.) (see examples below for handling "blank" arrays). Note that a merge field can only hold up to 255 characters. Also, there are 2 "special" keys:
	 * 			string INTERESTS - Set Interest Groups by passing a field named "INTERESTS" that contains a comma delimited list of Interest Groups to add. Commas in Interest Group names should be escaped with a backslash. ie, "," =&gt; "\,"
	 * 			string OPTINIP - Set the Opt-in IP fields. <em>Abusing this may cause your account to be suspended.</em> We do validate this and it must not be a private IP address.
	 * 			<strong>Handling Field Data Types</strong> - most fields you can just pass a string and all is well. For some, though, that is not the case...
	 * 				Field values should be formatted as follows:
	 * 					string	address For the string version of an Address, the fields should be delimited by <strong>2</strong> spaces. Address 2 can be skipped. The Country should be a 2 character ISO-3166-1 code and will default to your default country if not set
	 * 					array	address For the array version of an Address, the requirements for Address 2 and Country are the same as with the string version. Then simply pass us an array with the keys <strong>addr1</strong>, <strong>addr2</strong>, <strong>city</strong>, <strong>state</strong>, <strong>zip</strong>, <strong>country</strong> and appropriate values for each
	 * 					string	date use YYYY-MM-DD to be safe. Generally, though, anything strtotime() understands we'll understand - <a href="http://us2.php.net/strtotime" target="_blank">http://us2.php.net/strtotime</a>
	 * 					string	dropdown can be a normal string - we <em>will</em> validate that the value is a valid option
	 * 					string	image must be a valid, existing url. we <em>will</em> check its existence
	 * 					string	multi_choice can be a normal string - we <em>will</em> validate that the value is a valid option
	 * 					double	number pass in a valid number - anything else will turn in to zero (0). Note, this will be rounded to 2 decimal places
	 * 					string	phone If your account has the US Phone numbers option set, this <em>must</em> be in the form of NPA-NXX-LINE (404-555-1212). If not, we assume an International number and will simply set the field with what ever number is passed in.
	 * 					string	website This is a standard string, but we <em>will</em> verify that it looks like a valid URL
	 * 		string	email_type [optional] - email type preference for the email (html or text, defaults to html)
	 * 		bool	double_optin [optional] - flag to control whether a double opt-in confirmation message is sent, defaults to true. <em>Abusing this may cause your account to be suspended.</em>
	 * 		bool	update_existing [optional] - flag to control whether a existing subscribers should be updated instead of throwing and error
	 * 		bool	replace_interests [optional] - flag to determine whether we replace the interest groups with the groups provided, or we add the provided groups to the member's interest groups (defaults to true)
	 * 		bool	send_welcome [optional] - if your double_optin is false and this is true, we will send your lists Welcome Email if this subscribe succeeds - this will *not* fire if we end up updating an existing subscriber. defaults to false
	 *
	 * @return boolean true on success, false on failure.
	 */
	public function listSubscribe( $args = null ) {
		$defaults = array(
			'email_type'		=> 'html',
			'double_optin'		=> true,
			'update_existing'	=> false,
			'replace_interests'	=> true,
			'send_welcome'		=> false,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listSubscribe';

		return $this->callServer( $args );
	}

	/**
	 * Unsubscribe the given email address from the list
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	id - the list id to connect to. Get by calling lists()
	 * 		string	email_address - the email address to unsubscribe
	 * 		bool	delete_member [optional] - flag to completely delete the member from your list instead of just unsubscribing, default to false
	 * 		bool	send_goodbye [optional] - flag to send the goodbye email to the email address, defaults to true
	 * 		bool	send_notify [optional] - flag to send the unsubscribe notification email to the address defined in the list email notification settings, defaults to true
	 *
	 * @return boolean true on success, false on failure.
	 */
	public function listUnsubscribe( $args = null ) {
		$defaults = array(
			'delete_member'	=> false,
			'send_goodbye'	=> true,
			'send_notify'	=> true,
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'listUnsubscribe';

		return $this->callServer( $args );
	}

	/**
	 * Send your HTML content to have the CSS inlined and optionally remove the original styles.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	html - Your HTML content
	 * 		bool	strip_css [optional] - Whether you want the CSS &lt;style&gt; tags stripped from the returned document. Defaults to false.
	 *
	 * @return string Your HTML content with all CSS inlined, just like if we sent it.
	 */
	public function inlineCss( $args = null ) {
		$defaults = array(
			'strip_css'	=> false
		);
		$args = wp_parse_args( $args, $defaults);
		$args['method'] = 'inlineCss';

		return $this->callServer( $args );
	}

	/**
	 * Have HTML content auto-converted to a text-only format. You can send: plain HTML, an array of Template content, an existing Campaign Id, or an existing Template Id. Note that this will <b>not</b> save anything to or update any of your lists, campaigns, or templates.
	 *
	 * @param string|array $args Parameters needed for call such as:
	 * 		string	type - The type of content to parse. Must be one of: "html", "template", "url", "cid" (Campaign Id), or "tid" (Template Id)
	 * 		mixed	content - The content to use. For "html" expects  a single string value, "template" expects an array like you send to campaignCreate, "url" expects a valid & public URL to pull from, "cid" expects a valid Campaign Id, and "tid" expects a valid Template Id on your account.
	 *
	 * @return string the content pass in converted to text.
	 */
	public function generateText( $args = null ) {
		$args = wp_parse_args( $args );
		$args['method'] = 'generateText';

		return $this->callServer( $args );
	}

	/**
	 * Retrieve lots of account information including payments made, plan info, some account stats, installed modules,
	 * contact info, and more. No private information like Credit Card numbers is available.
	 *
	 * @section Helper
	 *
	 * @return array containing the details for the account tied to this API Key
	 * 		string		username The Account username
	 * 		string		user_id The Account user unique id (for building some links)
	 * 		bool		is_trial Whether the Account is in Trial mode (can only send campaigns to less than 100 emails)
	 * 		string		timezone The timezone for the Account - default is "US/Eastern"
	 * 		string		plan_type Plan Type - "monthly", "payasyougo", or "free"
	 * 		int			plan_low <em>only for Monthly plans</em> - the lower tier for list size
	 * 		int			plan_high <em>only for Monthly plans</em> - the upper tier for list size
	 * 		datetime	plan_start_date <em>only for Monthly plans</em> - the start date for a monthly plan
	 * 		int			emails_left <em>only for Free and Pay-as-you-go plans</em> emails credits left for the account
	 * 		bool		pending_monthly Whether the account is finishing Pay As You Go credits before switching to a Monthly plan
	 * 		datetime	first_payment date of first payment
	 * 		datetime	last_payment date of most recent payment
	 * 		int			times_logged_in total number of times the account has been logged into via the web
	 * 		datetime	last_login date/time of last login via the web
	 * 		string		affiliate_link Monkey Rewards link for our Affiliate program
	 * 		array		contact Contact details for the account, including: First & Last name, email, company name, address, phone, and url
	 * 		array		addons Addons installed in the account and the date they were installed.
	 * 		array		orders Order details for the account, include order_id, type, cost, date/time, and any credits applied to the order
	 */
	public function getAccountDetails() {
		$args = array();
		$args['method'] = 'getAccountDetails';

		return $this->callServer( $args );
	}

	public function init_locale() {
		$lang_dir = basename(dirname(__FILE__)) . '/languages';
		load_plugin_textdomain('mailchimp-framework', 'wp-content/plugins/' . $lang_dir, $lang_dir);
	}
}

// Instantiate our class
$wpMailChimpFramework = wpMailChimpFramework::getInstance();
