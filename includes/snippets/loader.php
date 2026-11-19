<?php
/**
 * Snippet Loader
 * 
 * Loads core snippets (CPT, taxonomy, ACF) first, then enabled optional snippets.
 * Each snippet file contains its own register function that sets up WordPress hooks.
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Load a snippet file and call its register function if it exists.
 *
 * @param string $file        Filename relative to snippets directory
 * @param string $register_fn Function name to call after loading (without namespace)
 */
function hws_portal_load_snippet_file(string $file, string $register_fn = ''): void {
	if (!defined('HWS_PORTAL_PATH')) { return; }

	$base = HWS_PORTAL_PATH . 'includes/snippets/';
	$path = $base . ltrim($file, '/');

	if (!file_exists($path)) { return; }

	require_once $path;

	// Call the register function if provided and exists
	if ($register_fn && function_exists(__NAMESPACE__ . '\\' . $register_fn)) {
		call_user_func(__NAMESPACE__ . '\\' . $register_fn);
	}
}

/**
 * Load an optional snippet (only if enabled in settings).
 *
 * @param array  $snips       Current snippet settings array
 * @param string $key         Settings key for this snippet
 * @param string $file        Filename to load
 * @param string $register_fn Register function to call
 */
function hws_portal_load_snippet(array $snips, string $key, string $file, string $register_fn = ''): void {
	// Only load if snippet is enabled in settings
	if (empty($snips[$key])) { return; }
	hws_portal_load_snippet_file($file, $register_fn);
}

/**
 * Loads all enabled snippets based on settings.
 * Called immediately when plugin loads (before init hook).
 */
function hws_portal_load_enabled_snippets(): void {
	$settings = hws_portal_get_settings();
	$snips = $settings['snippets'] ?? [];

	// =========================================================================
	// UTILITY SNIPPETS - Always load first (no settings check)
	// =========================================================================
	hws_portal_load_snippet_file('snippet-shortcode-utilities.php', 'hws_portal_register_snippet_shortcode_utilities');

	// =========================================================================
	// CORE SNIPPETS - CPT, taxonomy, ACF structures (check settings)
	// =========================================================================
	$core_snippets = [
		'register_cpt_asset_portal' => [
			'file' => 'snippet-register-cpt-asset-portal.php',
			'fn'   => 'hws_portal_register_snippet_register_cpt_asset_portal',
		],
		'register_taxonomy_portal_type' => [
			'file' => 'snippet-register-taxonomy-portal-type.php',
			'fn'   => 'hws_portal_register_snippet_register_taxonomy_portal_type',
		],
		'acf_options_page' => [
			'file' => 'snippet-options-page.php',
			'fn'   => 'hws_portal_register_snippet_acf_options_page',
		],
		'acf_fields_portal_settings' => [
			'file' => 'snippet-fields-portal-settings.php',
			'fn'   => 'hws_portal_register_snippet_acf_fields_portal_settings',
		],
		'acf_fields_portal_podcast' => [
			'file' => 'snippet-fields-portal-podcast.php',
			'fn'   => 'hws_portal_register_snippet_acf_fields_portal_podcast',
		],
	];

	// Load core snippets based on settings
	foreach ($core_snippets as $key => $def) {
		if (!empty($snips[$key])) {
			hws_portal_load_snippet_file($def['file'], $def['fn']);
		}
	}

	// =========================================================================
	// OPTIONAL SNIPPETS - Loaded based on settings
	// =========================================================================
	$optional_map = [
		'frontend_shortcodes' => [
			'file' => 'snippet-frontend-shortcodes.php',
			'fn'   => 'hws_portal_register_snippet_frontend_shortcodes',
		],
		'elementor_webhook_gas_force_success' => [
			'file' => 'snippet-elementor-webhook-gas-force-success.php',
			'fn'   => 'hws_portal_register_snippet_elementor_webhook_gas_force_success',
		],
		'assets_shortcode' => [
			'file' => 'snippet-display-marketing-sections.php',
			'fn'   => 'hws_portal_register_snippet_assets_shortcode',
		],
		'elementor_preview_hide_header_footer' => [
			'file' => 'snippet-disable-header-footer-elementor-preview-clean.php',
			'fn'   => 'hws_portal_register_snippet_elementor_preview_hide_header_footer',
		],
		'display_marketing_templates' => [
			'file' => 'snippet-display-marketing-templates.php',
			'fn'   => 'hws_portal_register_snippet_display_marketing_templates',
		],
		'snippet_asset_portal_email_functionality' => [
			'file' => 'snippet-asset-portal-email-functionality.php',
			'fn'   => 'hws_portal_register_snippet_asset_portal_email_functionality',
		],
		'password_protect_portal' => [
			'file' => 'snippet-password-protect-portal.php',
			'fn'   => 'hws_portal_register_snippet_password_protect_portal',
		],
		'wpallimport_field_protection' => [
			'file' => 'snippet-wpallimport-field-protection.php',
			'fn'   => 'hws_portal_register_snippet_wpallimport_field_protection',
		],
		'admin_columns_status' => [
			'file' => 'snippet-admin-columns-status.php',
			'fn'   => 'hws_portal_register_snippet_admin_columns_status',
		],
	];

	// Allow filtering for extensibility
	$optional_map = \apply_filters('hws_portal_snippet_map', $optional_map);

	// Load each enabled optional snippet
	foreach ($optional_map as $key => $def) {
		$file = is_array($def) && !empty($def['file']) ? (string) $def['file'] : '';
		$fn   = is_array($def) && !empty($def['fn'])   ? (string) $def['fn']   : '';
		if ($file === '') { continue; }
		hws_portal_load_snippet($snips, (string) $key, $file, $fn);
	}
}
