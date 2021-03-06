<?php 

// Pandora FMS - http://pandorafms.com
// ==================================================
// Copyright (c) 2005-2010 Artica Soluciones Tecnologicas
// Please see http://pandorafms.org for full contribution list

// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation for version 2.
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.

// Load global vars
global $config;

check_login ();

if (! check_acl ($config['id_user'], 0, "PM") && ! is_user_admin ($config['id_user'])) {
	db_pandora_audit("ACL Violation", "Trying to access Setup Management");
	require ("general/noaccess.php");
	return;
}

include_once($config['homedir'] . "/include/functions_profile.php");

// Load enterprise extensions
enterprise_include ('godmode/setup/setup_auth.php');


$table->data = array ();
$table->width = '98%';
$table->size[0] = '30%';

$table->data[0][0] = __('Authentication method');
$auth_methods = array ('mysql' => __('Local Pandora FMS'), 'ldap' => __('ldap'));
if (enterprise_installed()) {
	add_enterprise_auth_methods($auth_methods);
}
$table->data[0][1] = html_print_select ($auth_methods, 'auth', $config['auth'], 'show_selected_rows ();', '', 0, true);

$table->data[1][0] = __('Fallback to local authentication') . ui_print_help_tip(__("Enable this option if you want to fallback to local authentication when remote (ldap etc...) authentication failed."), true);
$table->data[1][1] = __('Yes').'&nbsp;'.html_print_radio_button ('fallback_local_auth', 1, '', $config['fallback_local_auth'], true).'&nbsp;&nbsp;';
$table->data[1][1] .= __('No').'&nbsp;'.html_print_radio_button ('fallback_local_auth', 0, '', $config['fallback_local_auth'], true);

$table->data[2][0] = __('Autocreate remote users');
$table->data[2][1] = __('Yes').'&nbsp;'.html_print_radio_button_extended ('autocreate_remote_users', 1, '', $config['autocreate_remote_users'], false, 'enable_profile_options ();', '', true).'&nbsp;&nbsp;';
$table->data[2][1] .= __('No').'&nbsp;'.html_print_radio_button_extended ('autocreate_remote_users', 0, '', $config['autocreate_remote_users'], false, 'enable_profile_options ();', '', true);
$table->rowstyle[1] = $config['auth'] != 'mysql' ? '' : 'display: none;';
$table->data[3][0] = __('Autocreate profile');
$profile_list = profile_get_profiles ();
if ($profile_list === false) {
	$profile_list = array ();
}
$table->data[3][1] = html_print_select ($profile_list, 'default_remote_profile', $config['default_remote_profile'], '', '', '', true, false, true, '', $config['autocreate_remote_users'] == 0);
$table->data[4][0] = __('Autocreate profile group');
$table->data[4][1] = html_print_select_groups ($config['id_user'], "AR", true, 'default_remote_group', $config['default_remote_group'], '', '', '', true, false, true, '', $config['autocreate_remote_users'] == 0);
$table->data[5][0] = __('Autocreate blacklist') . ui_print_help_icon ('autocreate_blacklist', true);
$table->data[5][1] = html_print_input_text ('autocreate_blacklist', $config['autocreate_blacklist'], '', 60, 100, true);
for ($i = 1; $i <= 4; $i++) {
	$table->rowstyle[$i] = $config['auth'] != 'mysql' ? '' : 'display: none;';
	$table->rowclass[$i] = 'remote';
}

$table->data[6][0] = __('LDAP server');
$table->data[6][1] = html_print_input_text ('ldap_server', $config['ldap_server'], '', 30, 100, true);
$table->data[7][0] = __('LDAP port');
$table->data[7][1] = html_print_input_text ('ldap_port', $config['ldap_port'], '', 10, 100, true);
$table->data[8][0] = __('LDAP version');
$ldap_versions = array (1 => 'LDAPv1', 2 => 'LDAPv2', 3 => 'LDAPv3');
$table->data[8][1] = html_print_select ($ldap_versions, 'ldap_version', $config['ldap_version'], '', '', 0, true);
$table->data[9][0] = __('Start TLS');
$table->data[9][1] = __('Yes').'&nbsp;'.html_print_radio_button ('ldap_start_tls', 1, '', $config['ldap_start_tls'], true).'&nbsp;&nbsp;';
$table->data[9][1] .= __('No').'&nbsp;'.html_print_radio_button ('ldap_start_tls', 0, '', $config['ldap_start_tls'], true);
$table->data[10][0] = __('Base DN');
$table->data[10][1] = html_print_input_text ('ldap_base_dn', $config['ldap_base_dn'], '', 60, 100, true);
$table->data[11][0] = __('Login attribute');
$table->data[11][1] = html_print_input_text ('ldap_login_attr', $config['ldap_login_attr'], '', 60, 100, true);

// Hide LDAP configuration options
for ($i = 2; $i <= 11; $i++) {
	$table->rowstyle[$i] = $config['auth'] == 'ldap' ? '' : 'display: none;';
	$table->rowclass[$i] = 'ldap';
}

// Set the rows autocreation for Active Directory
for ($i = 2; $i <= 5; $i++) {
	$table->rowclass[$i] .= ' ' . 'ad';
}

// Hide fallback option when local authentication is selected.
$table->rowstyle[1] = $config['auth'] == 'mysql' ? 'display: none;' : '';
$table->rowclass[1] = 'remote';

// Add enterprise authentication options
if (enterprise_installed()) {
	add_enterprise_auth_options($table, 12);
}

$config_double_auth_enabled = false;
if (isset($config['double_auth_enabled'])) {
	$config_double_auth_enabled = $config['double_auth_enabled'];
}

// Enable double authentication
$row = array();
$row[] = __('Double authentication')
	. ui_print_help_tip(__("If this option is enabled, the users can use double authentication with their accounts"), true);
$row[] = __('Yes') . '&nbsp;' .
	html_print_radio_button('double_auth_enabled', 1, '',
		$config_double_auth_enabled, true)
	.'&nbsp;&nbsp;'
	. __('No') .'&nbsp;' .
	html_print_radio_button('double_auth_enabled', 0, '',
		$config_double_auth_enabled, true);
$table->data[] = $row;

$row_timeout = array();
$row_timeout[] = __('Session timeout (mins)')
	. ui_print_help_tip(__("This is defined in minutes"), true);
if (empty($config["session_timeout"])) $config["session_timeout"] = 90;
$row_timeout[] = html_print_input_text ('session_timeout', $config["session_timeout"], '', 10, 10, true);
$table->data[] = $row_timeout;
	
echo '<form id="form_setup" method="post">';
html_print_input_hidden ('update_config', 1);
html_print_table ($table);
echo '<div class="action-buttons" style="width: '.$table->width.'">';
html_print_submit_button (__('Update'), 'update_button', false, 'class="sub upd"');
echo '</div>';
echo '</form>';
?>

<script type="text/javascript">
	function show_selected_rows () {
		var auth_method = $("#auth").val ();
		
		$(".remote").css("display", "none");
		$(".ldap").css("display", "none");
		$(".ad").css("display", "none");
		$(".pandora").css("display", "none");
		$(".babel").css("display", "none");
		$(".integria").css("display", "none");
		
		if (auth_method != "mysql") {
			$(".remote").css("display", "");
		}
		$("." + auth_method).css('display', '');
	}
	
	function enable_profile_options () {
		var remote_auto = $("input:radio[name=autocreate_remote_users]:checked").val();
		
		if (remote_auto == 0) {
			$("#default_remote_profile").attr("disabled", true);
			$("#default_remote_group").attr("disabled", true);
			$("#text-autocreate_blacklist").attr("disabled", true);
		}
		else {
			$("#default_remote_profile").removeAttr('disabled');
			$("#default_remote_group").removeAttr('disabled');
			$("#text-autocreate_blacklist").removeAttr('disabled');
		}
	}
	
	show_selected_rows();
</script>
