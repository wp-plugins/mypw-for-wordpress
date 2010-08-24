<?php
/*
Plugin Name: MyPW Authentication
Plugin URI: http://www.mypw.com/
Author: MyPW / Lars Nilsen
Version: 0.21 
*/


/* Add default values */

function mypwauth_plugin_activate() {
	add_option('mypw_site_id', 'wordpress');
	add_option('mypw_auth_key', 'dcb0f636f626c0448959e9cb95011f11');
	add_option('mypw_method', 'differentfields');
}
register_activation_hook(__FILE__, 'mypwauth_plugin_activate');


/* Add menu alternative in wp-admin => Settings */

function mypwauth_admin_menu() {
	if (current_user_can('administrator')) {
		add_options_page('MyPW Authentication Options', 'MyPW Auth', 'administrator', 'mypwauth-config-page', 'mypwauth_config_page');
		add_action('admin_init', 'mypwauth_register_settings');
	}
}
add_action('admin_menu', 'mypwauth_admin_menu');

function mypwauth_config_page() {
	$mypw_method = get_option('mypw_method');

	echo '<form method="post" action="options.php">';
	settings_fields('mypwauth-settings-group');
	echo '<table class="form-table">';
	echo '<tr>';
	echo '<th><label for="mypw_site_id">MyPW Site ID</label></th>';
	echo '<td><input type="text" name="mypw_site_id" id="mypw_site_id" value="' . get_option('mypw_site_id') . '" class="regular-text code" /></td>';
	echo '</tr>';
	echo '<tr>';
	echo '<th><label for="mypw_auth_key">MyPW Auth Key</label></th>';
	echo '<td><input type="text" name="mypw_auth_key" id="mypw_auth_key" value="' . get_option('mypw_auth_key') . '" class="regular-text code" /></td>';
	echo '</tr>';
	echo '<tr>';
	echo '<th>MyPW Method</th>';
	echo '<td>';
	echo '<input type="radio" value="differentfields" name="mypw_method" id="mypw_method_differentfields"' . ($mypw_method == 'differentfields' ? 'checked="checked"' : '') . ' /> <label for="mypw_method_differentfields">Standard password + MyPW one time password in different fields</label><br />';
	echo '<input type="radio" value="samefield" name="mypw_method" id="mypw_method_samefield"' . ($mypw_method == 'samefield' ? 'checked="checked"' : '') . ' /> <label for="mypw_method_samefield">Standard password + MyPW one time password in the same field</label><br />';
	echo '<input type="radio" value="mypwonly" name="mypw_method" id="mypw_method_mypwonly"' . ($mypw_method == 'mypwonly' ? 'checked="checked"' : '') . ' /> <label for="mypw_method_mypwonly">MyPW one time password only</label>';
	echo '</td>';
	echo '</tr>';
	echo '</table>';
	echo '<p class="submit">';
	echo '<input type="submit" class="button-primary" value="' . __('Save Changes') . '" />';
	echo '</p>';
	echo '</form>';
}

function mypwauth_register_settings() {
	register_setting('mypwauth-settings-group', 'mypw_site_id');
	register_setting('mypwauth-settings-group', 'mypw_auth_key');
	register_setting('mypwauth-settings-group', 'mypw_method');
}


/* Add token id field in wp-admin => Users */

function mypwauth_user_profile($user) {
	echo '<h3>MyPW</h3>';
	echo '<table class="form-table">';
	echo '<tr>';
	echo '<th><label for="mypw_token_id">Token ID</label></th>';
	echo '<td><input type="text" name="mypw_token_id" id="mypw_token_id" value="' . get_user_meta($user->ID, 'mypw_token_id', true) . '" class="code" /> <a href="https://www.mypw.com/picktoken/" target="_blank">Get your own MyPW token here</a></td>';
	echo '</tr>';
	echo '</table>';
	echo '';
}
add_action('show_user_profile', 'mypwauth_user_profile');
add_action('edit_user_profile', 'mypwauth_user_profile');

function mypwauth_user_update($user_id) {
 
	if (!current_user_can('edit_user', $user_id)) {
		return false;
	}

	update_user_meta($user_id, 'mypw_token_id', $_POST['mypw_token_id']);
}
add_action('personal_options_update', 'mypwauth_user_update');
add_action('edit_user_profile_update', 'mypwauth_user_update');


/* If MyPW Token ID is set, then handle MyPW password authentication here */

function mypwauth_check_password($check, $password, $hash, $user_id) {
	function mypwauth_do_xmlrpc_call($token_id, $token_value) {
		require_once 'IXR_Library.inc.php';

		$auth_struct = array (
			'siteid'     => get_option('mypw_site_id'),
			'authkey'    => get_option('mypw_auth_key'),
			'tokenid'    => $token_id,
			'tokenvalue' => $token_value,
			'userip'     => preg_replace('/[^0-9a-fA-F:., ]/', '', $_SERVER['REMOTE_ADDR'])
		);

		$client = new IXR_Client('https://services.mypw.com/RPC2');
		if ($client->query('auth.auth', $auth_struct)) {
			$response = $client->getResponse();
			return $response['code'] == 0;
		}
		else {
			global $mypwauth_error;
			$mypwauth_error = new WP_Error('mypwauth_error_xmlrpc', 'An error occured while contacting the MyPW server. Please contact <a href="mailto:sitesupport@mypw.com">sitesupport@mypw.com</a>.');
			return false;
		}
	}

	$token_id = get_user_meta($user_id, 'mypw_token_id', true);
	if (empty($token_id)) {
		return $check; // Token ID not set, proceed with standard authentication only
	}

	switch (get_option('mypw_method'))
	{
	case 'differentfields':
		if (!$check || !isset($_POST["mypw_pass"])) {
			return false;
		}

		$token_value = $_POST["mypw_pass"];
		break;

	case 'samefield':
		$len = strlen($password);
		if ($len <= 6) {
			return false; // If equal to or less than 6 characters, something is wrong
		}

		// When coming here, we can assume the new Wordpress password format
		global $wp_hasher;
		if (empty($wp_hasher)) {
			require_once(ABSPATH . WPINC . '/class-phpass.php');
			$wp_hasher = new PasswordHash(8, true);
		}

		// Test standard authentication first, since this is less expensive
		$password6 = substr($password, 0, $len - 6);
		$password8 = substr($password, 0, $len - 8);
		if (!$wp_hasher->CheckPassword($password6, $hash) && !$wp_hasher->CheckPassword($password8, $hash)) {
			return false;
		}

		$token_value6 = substr($password, -6);
		$token_value8 = substr($password, -8);
		return mypwauth_do_xmlrpc_call($token_id, $token_value6) || mypwauth_do_xmlrpc_call($token_id, $token_value8);

	case 'mypwonly':
		$token_value = $password;
		break;

	default:
		return false;
	}

	$len = strlen($token_value);
	if ($len != 6 && $len != 8) {
		return false; // If neither 6 nor 8 characters long, something is wrong
	}

	return mypwauth_do_xmlrpc_call($token_id, $token_value);
}
add_filter('check_password', 'mypwauth_check_password', 10, 4);


/* Catch any serious errors and return for output */

function mypwauth_authenticate($user, $username, $password) {
	global $mypwauth_error;
	return (is_wp_error($mypwauth_error) ? $mypwauth_error : $user);
}
add_filter('authenticate', 'mypwauth_authenticate', 25, 3);


/* Add an extra field to the login form for MyPW token */

function mypwauth_login_head() {
	if (get_option('mypw_method') == 'differentfields') {
		echo '<link rel="stylesheet" type="text/css" href="' . plugins_url(basename(dirname(__FILE__))) . '/login.css" />';
	}
}
add_action('login_head', 'mypwauth_login_head');

function mypwauth_login_form() {
	if (get_option('mypw_method') == 'differentfields') {
		echo '<p>';
		echo '<label>MyPW password<br /><input type="password" name="mypw_pass" id="mypw_pass" class="input" value="" size="20" tabindex="20" /></label>';
		echo '</p>';
	}
}
add_action('login_form', 'mypwauth_login_form');
