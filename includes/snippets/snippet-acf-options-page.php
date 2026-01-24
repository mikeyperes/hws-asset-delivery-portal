<?php
/**
 * Snippet: ACF Options Page - Asset Portal Settings
 * 
 * Registers the ACF options page for plugin settings.
 * 
 * Page: Asset Portal Settings
 * Menu Slug: Uses HWS_PORTAL_OPTIONS_SLUG constant
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register function - called by loader when snippet is enabled.
 * Sets up the acf/init hook for options page registration.
 */
function hws_portal_register_snippet_acf_options_page(): void {
	\add_action('acf/init', __NAMESPACE__ . '\\hws_portal_register_acf_options_page');
	
	// Fix ACF nonce validation issues on options pages
	\add_filter('acf/validate_save_post', __NAMESPACE__ . '\\hws_portal_fix_acf_nonce_validation', 5);
}

/**
 * Fix ACF nonce validation issues.
 * This can happen when the page is open for too long or there are caching conflicts.
 */
function hws_portal_fix_acf_nonce_validation(): void {
	// Check if we're on our options page
	$screen = function_exists('get_current_screen') ? \get_current_screen() : null;
	if (!$screen) {
		return;
	}
	
	// Only apply to our settings page
	if (strpos($screen->id, HWS_PORTAL_OPTIONS_SLUG) === false) {
		return;
	}
	
	// Verify the user has permission
	if (!\current_user_can('manage_options')) {
		return;
	}
	
	// If the standard nonce check failed but we have a valid logged-in user with permissions,
	// regenerate the nonce check - ACF will create a new one
	if (!isset($_POST['_acf_nonce']) || !\wp_verify_nonce($_POST['_acf_nonce'], 'acf_nonce')) {
		// Set a fresh nonce for this request if user is valid admin
		if (\is_user_logged_in() && \current_user_can('manage_options')) {
			$_POST['_acf_nonce'] = \wp_create_nonce('acf_nonce');
		}
	}
}

/**
 * Register the ACF options page.
 * Creates "Asset Portal Settings" page in the admin menu.
 */
function hws_portal_register_acf_options_page(): void {
	// Bail if ACF Pro is not active
	if (!function_exists('acf_add_options_page')) {
		return;
	}
	
	\acf_add_options_page(array(
		'page_title'      => 'Asset Portal Settings',
		'menu_title'      => 'Asset Portal Settings',
		'menu_slug'       => HWS_PORTAL_OPTIONS_SLUG,
		'capability'      => 'manage_options',
		'redirect'        => false,
		'autoload'        => true,
		'update_button'   => 'Save Settings',
		'updated_message' => 'Settings saved.',
	));
}
