<?php
/**
 * Plugin Name: HWS Portal
 * Plugin URI: https://hexawebsystems.com
 * Description: Asset portal management system with email functionality, password protection, marketing templates, and Elementor integrations. Provides admin toggles for modular snippet activation.
 * Version: 1.8.1
 * Author: Michael Peres - Hexa Web Systems
 * Author URI: https://michaelperes.com
 * Namespace: hws_portal
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

// =============================================================================
// PLUGIN CONSTANTS (static)
// =============================================================================
define('HWS_PORTAL_VERSION', '1.8.1');
define('HWS_PORTAL_PATH', plugin_dir_path(__FILE__));
define('HWS_PORTAL_URL', plugin_dir_url(__FILE__));
define('HWS_PORTAL_OPTION_KEY', 'hws_portal_settings');

// Portal fields are registered and edited by Hexa WP Core.
$hexa_plugin_core_root = HWS_PORTAL_PATH . 'lib/hexa-wordpress-plugin-core';
require_once $hexa_plugin_core_root . '/bootstrap.php';
\hexa_plugin_core_register_package(
	'hws-asset-delivery-portal',
	$hexa_plugin_core_root,
	[
		'minimum_version' => trim( (string) file_get_contents( $hexa_plugin_core_root . '/VERSION' ) ),
	]
);

/**
 * Portal options page slug.
 * This is the menu slug for the theme options page URL.
 * Note: For get_field() calls, always use 'option' - not this slug.
 */
define('HWS_PORTAL_OPTIONS_SLUG', 'deliverables---settings');

/**
 * Master key for bypassing password protection.
 * Usage: ?master_key=hwsbypass2024
 */
define('HWS_PORTAL_MASTER_KEY', 'hwsbypass2024');

// =============================================================================
// DEFAULT SETTINGS
// =============================================================================

/**
 * Returns default plugin settings array.
 * Core snippets default ON, optional snippets default OFF.
 */
function hws_portal_default_settings(): array {
	return [
		'snippets' => [
			// Core components (default on)
			'register_cpt_asset_portal' => 1,
			'register_taxonomy_portal_type' => 1,
			'acf_options_page' => 1,
			'acf_fields_portal_settings' => 1,
			'acf_fields_portal_podcast' => 1,
			// Optional snippets (default off)
			'frontend_shortcodes' => 0,
			'snippet_asset_portal_email_functionality' => 0,
			'elementor_webhook_gas_force_success' => 0,
			'assets_shortcode' => 0,
			'elementor_preview_hide_header_footer' => 0,
			'display_marketing_templates' => 0,
			'password_protect_portal' => 0,
			'admin_columns_status' => 0,
		],
		'cpt' => [
			'slug'          => 'asset-portal',
			'singular'      => 'Portal',
			'plural'        => 'Portals',
			'menu_name'     => 'Portals',
		],
		'default_term' => 'the-michael-peres-podcast',
	];
}

/**
 * Get merged settings (saved + defaults).
 */
function hws_portal_get_settings(): array {
	$defaults = hws_portal_default_settings();
	$saved = \get_option(HWS_PORTAL_OPTION_KEY, []);
	if (!is_array($saved)) { $saved = []; }
	return array_replace_recursive($defaults, $saved);
}

/**
 * Get CPT slug from settings.
 * Used throughout the plugin instead of hardcoded value.
 */
function hws_portal_get_cpt_slug(): string {
	$settings = hws_portal_get_settings();
	$slug = $settings['cpt']['slug'] ?? 'asset-portal';
	return sanitize_key($slug) ?: 'asset-portal';
}

/**
 * Get CPT labels from settings.
 */
function hws_portal_get_cpt_labels(): array {
	$settings = hws_portal_get_settings();
	$singular = $settings['cpt']['singular'] ?? 'Portal';
	$plural = $settings['cpt']['plural'] ?? 'Portals';
	$menu = $settings['cpt']['menu_name'] ?? 'Portals';
	
	return [
		'singular' => $singular,
		'plural'   => $plural,
		'menu'     => $menu,
	];
}

/**
 * Get default taxonomy term from settings.
 */
function hws_portal_get_default_term(): string {
	$settings = hws_portal_get_settings();
	return $settings['default_term'] ?? 'the-michael-peres-podcast';
}

// Define constants that need to be available early (for backward compatibility)
// These use functions so they're dynamic
if (!defined('HWS_PORTAL_CPT')) {
	// Get saved settings directly to avoid circular dependency
	$_hws_saved = \get_option(HWS_PORTAL_OPTION_KEY, []);
	$_hws_cpt_slug = isset($_hws_saved['cpt']['slug']) && $_hws_saved['cpt']['slug'] 
		? sanitize_key($_hws_saved['cpt']['slug']) 
		: 'asset-portal';
	define('HWS_PORTAL_CPT', $_hws_cpt_slug);
	
	$_hws_default_term = isset($_hws_saved['default_term']) && $_hws_saved['default_term']
		? sanitize_key($_hws_saved['default_term'])
		: 'the-michael-peres-podcast';
	define('HWS_PORTAL_DEFAULT_TERM', $_hws_default_term);
}

// =============================================================================
// LOAD INCLUDES
// =============================================================================
require_once HWS_PORTAL_PATH . 'includes/snippets/loader.php';

if (is_admin()) {
	require_once HWS_PORTAL_PATH . 'includes/admin/settings-page.php';
}

// =============================================================================
// SMART NAME PARSING - Extract first_name and last_name from full name
// =============================================================================

/**
 * Parse a full name into first_name and last_name components.
 * Handles titles (Dr., Mr., Mrs., Ms., etc.), suffixes (Jr., Sr., III, etc.),
 * and various name formats intelligently.
 * 
 * @param string $full_name The full name to parse
 * @return array ['first_name' => string, 'last_name' => string, 'title' => string, 'suffix' => string]
 */
function hws_portal_parse_full_name(string $full_name): array {
	$result = [
		'first_name' => '',
		'last_name'  => '',
		'title'      => '',
		'suffix'     => '',
	];
	
	$full_name = trim($full_name);
	if (empty($full_name)) {
		return $result;
	}
	
	// Normalize whitespace
	$full_name = preg_replace('/\s+/', ' ', $full_name);
	
	// Common titles/prefixes (case insensitive)
	$titles = [
		'dr', 'dr.', 'doctor',
		'mr', 'mr.', 'mister',
		'mrs', 'mrs.', 'missus',
		'ms', 'ms.', 'miss',
		'prof', 'prof.', 'professor',
		'rev', 'rev.', 'reverend',
		'fr', 'fr.', 'father',
		'sr', 'sr.', 'sister',
		'rabbi', 'imam', 'pastor',
		'hon', 'hon.', 'honorable',
		'sir', 'dame', 'lord', 'lady',
		'capt', 'capt.', 'captain',
		'col', 'col.', 'colonel',
		'gen', 'gen.', 'general',
		'lt', 'lt.', 'lieutenant',
		'sgt', 'sgt.', 'sergeant',
		'cpl', 'cpl.', 'corporal',
		'maj', 'maj.', 'major',
		'adm', 'adm.', 'admiral',
	];
	
	// Common suffixes
	$suffixes = [
		'jr', 'jr.', 'junior',
		'sr', 'sr.', 'senior',
		'i', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x',
		'1st', '2nd', '3rd', '4th', '5th',
		'phd', 'ph.d', 'ph.d.',
		'md', 'm.d', 'm.d.',
		'esq', 'esq.', 'esquire',
		'cpa', 'c.p.a', 'c.p.a.',
		'dds', 'd.d.s', 'd.d.s.',
		'do', 'd.o', 'd.o.',
		'jd', 'j.d', 'j.d.',
		'llb', 'l.l.b', 'l.l.b.',
		'llm', 'l.l.m', 'l.l.m.',
		'mba', 'm.b.a', 'm.b.a.',
		'rn', 'r.n', 'r.n.',
		'ret', 'ret.', 'retired',
	];
	
	// Split name into parts
	$parts = explode(' ', $full_name);
	$clean_parts = [];
	
	// Extract title from beginning
	if (count($parts) > 0) {
		$first_part_lower = strtolower(rtrim($parts[0], '.'));
		$first_part_with_dot = strtolower($parts[0]);
		
		foreach ($titles as $title) {
			if ($first_part_lower === rtrim($title, '.') || $first_part_with_dot === $title) {
				$result['title'] = $parts[0];
				array_shift($parts);
				break;
			}
		}
	}
	
	// Extract suffix from end
	if (count($parts) > 0) {
		$last_part_lower = strtolower(rtrim(end($parts), '.'));
		$last_part_with_dot = strtolower(end($parts));
		
		foreach ($suffixes as $suffix) {
			if ($last_part_lower === rtrim($suffix, '.') || $last_part_with_dot === $suffix) {
				$result['suffix'] = array_pop($parts);
				break;
			}
		}
	}
	
	// Also check for comma-separated suffix (e.g., "Smith, Jr.")
	if (count($parts) > 0) {
		$last_part = end($parts);
		if (strpos($last_part, ',') !== false) {
			$comma_parts = explode(',', $last_part);
			$parts[count($parts) - 1] = trim($comma_parts[0]);
			if (isset($comma_parts[1])) {
				$potential_suffix = strtolower(trim($comma_parts[1], '. '));
				foreach ($suffixes as $suffix) {
					if ($potential_suffix === rtrim($suffix, '.')) {
						$result['suffix'] = trim($comma_parts[1]);
						break;
					}
				}
			}
		}
	}
	
	// Now parse the remaining name parts
	$parts = array_values(array_filter($parts)); // Re-index and remove empty
	$count = count($parts);
	
	if ($count === 0) {
		// No name parts left
		return $result;
	} elseif ($count === 1) {
		// Only one name - treat as first name
		$result['first_name'] = $parts[0];
	} elseif ($count === 2) {
		// Standard "First Last" format
		$result['first_name'] = $parts[0];
		$result['last_name'] = $parts[1];
	} else {
		// Multiple parts - need to determine split point
		// Check for compound last names (von, van, de, del, della, di, da, le, la, el, al, bin, ibn, etc.)
		$compound_prefixes = ['von', 'van', 'de', 'del', 'della', 'di', 'da', 'le', 'la', 'el', 'al', 'bin', 'ibn', 'mac', 'mc', 'o\'', 'st', 'st.', 'san', 'santa'];
		
		// Find where the last name starts
		$last_name_start = $count - 1; // Default: last word only
		
		for ($i = 1; $i < $count - 1; $i++) {
			$part_lower = strtolower($parts[$i]);
			if (in_array($part_lower, $compound_prefixes)) {
				$last_name_start = $i;
				break;
			}
		}
		
		// If no compound prefix found, use last two words if the second-to-last is lowercase
		// (might be a compound like "Van Dyke")
		if ($last_name_start === $count - 1 && $count > 2) {
			$second_to_last = $parts[$count - 2];
			if ($second_to_last === strtolower($second_to_last) || in_array(strtolower($second_to_last), $compound_prefixes)) {
				$last_name_start = $count - 2;
			}
		}
		
		// Split into first and last
		$first_parts = array_slice($parts, 0, $last_name_start);
		$last_parts = array_slice($parts, $last_name_start);
		
		$result['first_name'] = implode(' ', $first_parts);
		$result['last_name'] = implode(' ', $last_parts);
	}
	
	return $result;
}

/**
 * Get first name from a full name string.
 * 
 * @param string $full_name The full name
 * @return string The first name
 */
function hws_portal_get_first_name(string $full_name): string {
	$parsed = hws_portal_parse_full_name($full_name);
	return $parsed['first_name'];
}

/**
 * Get last name from a full name string.
 * 
 * @param string $full_name The full name
 * @return string The last name
 */
function hws_portal_get_last_name(string $full_name): string {
	$parsed = hws_portal_parse_full_name($full_name);
	return $parsed['last_name'];
}

// =============================================================================
// ASSET PORTAL: DEFAULT TEMPLATES (seed + admin import button)
// =============================================================================

/**
 * Pull raw template IDs from ACF Options field NAME: default_marketing_templates
 * Returns IDs in exact order as stored in theme options.
 */
function hws_portal_get_default_marketing_template_ids(): array {
	if (!\Hexa\PluginCore\Fields\Field::available()) return [];

	// Get raw value (unformatted) to preserve order
	$defaults = \Hexa\PluginCore\Fields\Field::get('default_marketing_templates', 'option', false);
	
	// Fallback to other option page slugs
	if (empty($defaults)) {
		$defaults = \Hexa\PluginCore\Fields\Field::get('default_marketing_templates', 'option', false);
	}
	if (empty($defaults)) {
		$defaults = \Hexa\PluginCore\Fields\Field::get('default_marketing_templates', 'options', false);
	}

	if (empty($defaults)) return [];
	
	// Handle if ACF returned objects instead of IDs
	if (is_array($defaults)) {
		$ids = [];
		foreach ($defaults as $item) {
			if (is_object($item) && isset($item->ID)) {
				$ids[] = (int) $item->ID;
			} elseif (is_array($item) && isset($item['ID'])) {
				$ids[] = (int) $item['ID'];
			} elseif (is_numeric($item)) {
				$ids[] = (int) $item;
			}
		}
		// Filter out zeros but preserve order
		return array_values(array_filter($ids));
	}

	return [];
}

/**
 * Set ACF field NAME `templates` for a given post.
 * Ensures order is preserved exactly as passed.
 */
function hws_portal_set_asset_portal_templates(int $post_id, array $template_ids): bool {
	// Keep exact order - don't use array_values which re-indexes
	$template_ids = array_map('intval', $template_ids);
	$template_ids = array_filter($template_ids); // Remove zeros
	$template_ids = array_values($template_ids); // Re-index from 0
	
	if (!$post_id || empty($template_ids)) return false;

	// Delete existing first to ensure clean state
	\delete_post_meta($post_id, 'templates');
	\delete_post_meta($post_id, '_templates');

	// Use direct meta update to ensure order - ACF will read this correctly
	\update_post_meta($post_id, 'templates', $template_ids);
	\update_post_meta($post_id, '_templates', 'field_69373d01d65ce');
	
	return true;
}

/**
 * =============================================================================
 * PASSWORD AUTO-GENERATION
 * =============================================================================
 * 
 * Generates a password for portal posts if one doesn't exist.
 * Runs on save_post when status is draft or publish.
 */

/**
 * Generate password for a portal post if empty.
 * Also logs to history field.
 *
 * @param int $post_id The post ID
 * @return bool True if password was generated
 */
function hws_portal_generate_password(int $post_id): bool {
	// Get current password value
	$current_pw = '';
	
	// Try ACF first
	if (\Hexa\PluginCore\Fields\Field::available()) {
		$current_pw = \Hexa\PluginCore\Fields\Field::get('password', $post_id);
	}
	
	// Fallback to post meta
	if (!is_string($current_pw) || trim($current_pw) === '') {
		$current_pw = \get_post_meta($post_id, 'password', true);
	}
	
	// If password exists, do nothing
	if (is_string($current_pw) && trim($current_pw) !== '') {
		return false;
	}
	
	// Generate new password
	$new_pw = \wp_generate_password(12, false, false);
	
	// Save password (prefer ACF if available)
	if (\Hexa\PluginCore\Fields\Field::available()) {
		\Hexa\PluginCore\Fields\Field::update('password', $new_pw, $post_id);
	} else {
		\update_post_meta($post_id, 'password', $new_pw);
	}
	
	// Log to history
	hws_portal_log_to_history($post_id, '🔐 PASSWORD GENERATED', [
		'🆔 Post ID' => $post_id,
		'🔑 Password' => $new_password,
	]);
	
	return true;
}

/**
 * Append a log entry to the history field.
 *
 * @param int    $post_id The post ID
 * @param string $title   Log entry title
 * @param array  $data    Key-value pairs to log
 */
function hws_portal_log_to_history(int $post_id, string $title, array $data = []): void {
	// Get current history
	$history = '';
	if (\Hexa\PluginCore\Fields\Field::available()) {
		$history = \Hexa\PluginCore\Fields\Field::get('history', $post_id);
	}
	if (!is_string($history)) {
		$history = \get_post_meta($post_id, 'history', true);
	}
	if (!is_string($history)) {
		$history = '';
	}
	
	// Build log block with emoji formatting
	$log = "════════════════════════════════════════\n";
	$log .= sprintf("📅 [%s]\n", \current_time('mysql'));
	$log .= sprintf("📌 %s\n", $title);
	$log .= "────────────────────────────────────────\n";
	foreach ($data as $key => $value) {
		$log .= sprintf("%s: %s\n", $key, $value);
	}
	$log .= "════════════════════════════════════════";
	
	// Append to history with spacing
	$new_history = $history ? ($history . "\n\n" . $log) : $log;
	
	// Save history (prefer ACF if available)
	if (\Hexa\PluginCore\Fields\Field::available()) {
		\Hexa\PluginCore\Fields\Field::update('history', $new_history, $post_id);
	} else {
		\update_post_meta($post_id, 'history', $new_history);
	}
}

/**
 * Hook: Run password generation when portal post is saved as draft or published.
 * Multiple hooks to catch different save scenarios including imports.
 */

// Hook 1: save_post_{cpt} - fires when post is saved via admin
\add_action('save_post_' . HWS_PORTAL_CPT, function ($post_id, $post, $update) {
	// Skip autosaves and revisions
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
		return;
	}
	
	// Only run for draft or publish status
	if (!in_array($post->post_status, ['draft', 'publish'], true)) {
		return;
	}
	
	// Generate password if needed
	hws_portal_generate_password($post_id);
	
}, 20, 3);

// Hook 2: wp_insert_post - fires when post is inserted (catches imports)
\add_action('wp_insert_post', function ($post_id, $post, $update) {
	// Only our CPT
	if (!$post || $post->post_type !== HWS_PORTAL_CPT) {
		return;
	}
	
	// Skip autosaves and revisions
	if (\wp_is_post_autosave($post_id) || \wp_is_post_revision($post_id)) {
		return;
	}
	
	// Only run for draft or publish status
	if (!in_array($post->post_status, ['draft', 'publish'], true)) {
		return;
	}
	
	// Generate password if needed
	hws_portal_generate_password($post_id);
	
}, 20, 3);

// Hook 3: transition_post_status - fires when status changes (catches imports that set status)
\add_action('transition_post_status', function ($new_status, $old_status, $post) {
	// Only our CPT
	if (!$post || $post->post_type !== HWS_PORTAL_CPT) {
		return;
	}
	
	// Only run for draft or publish status
	if (!in_array($new_status, ['draft', 'publish'], true)) {
		return;
	}
	
	// Generate password if needed
	hws_portal_generate_password($post->ID);
	
}, 20, 3);

/**
 * Admin UI: Import Default Templates button + Auto-check taxonomy
 * Button imports via jQuery/Select2 - NO page refresh
 */
\add_action('admin_enqueue_scripts', function ($hook) {
	if (!in_array($hook, ['post.php', 'post-new.php'], true)) return;
	if (!function_exists('get_current_screen')) return;

	$screen = \get_current_screen();
	if (!$screen || ($screen->post_type ?? '') !== HWS_PORTAL_CPT) return;

	\wp_enqueue_script('jquery');

	// Get default templates
	$default_templates = hws_portal_get_default_marketing_template_ids();
	$template_data = [];
	foreach ($default_templates as $id) {
		$title = \get_the_title($id);
		// Decode ALL HTML entities including &#8211; (en-dash)
		$title = html_entity_decode((string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		$title = $title ?: ('Template #' . (int) $id);
		$template_data[] = [
			'id'    => (int) $id,
			'title' => $title,
		];
	}

	// Get default term from settings
	$settings = hws_portal_get_settings();
	$default_term = $settings['default_term'] ?? 'the-michael-peres-podcast';

	\wp_add_inline_script('jquery', 'window.HWS_PORTAL_DEFAULT_TEMPLATES = ' . \wp_json_encode($template_data) . ';');
	\wp_add_inline_script('jquery', 'window.HWS_PORTAL_DEFAULT_TERM = ' . \wp_json_encode($default_term) . ';');

	\wp_add_inline_script('jquery', <<<'JS'
jQuery(function($){

	// =========================================================================
	// IMPORT DEFAULT TEMPLATES - jQuery/Select2 only, NO refresh
	// =========================================================================
	
	function hwsFindTemplatesField() {
		var $f = $('.hexa-field[data-key="field_69373d01d65ce"]').first();
		if ($f.length) return $f;
		return $('.hexa-field[data-name="templates"]').first();
	}
	
	function hwsImportTemplates($select, $status) {
		var templates = window.HWS_PORTAL_DEFAULT_TEMPLATES || [];
		
		if (!templates.length) {
			if ($status) $status.text('No default templates configured.').css('color', 'orange').show();
			return false;
		}
		
		try {
			// Clear existing options
			$select.empty();
			
			// Add each template
			$.each(templates, function(i, tpl) {
				if (tpl && tpl.id && tpl.title) {
					var option = new Option(tpl.title, tpl.id, true, true);
					$select.append(option);
				}
			});
			
			// Trigger Select2 to update
			$select.trigger('change');
			
			if ($status) {
				$status.text(templates.length + ' templates imported.').css('color', 'green').show();
				setTimeout(function() { $status.fadeOut(); }, 3000);
			}
			
			return true;
		} catch (e) {
			if ($status) $status.text('Error: ' + e.message).css('color', 'red').show();
			return false;
		}
	}
	
	function hwsSetupTemplatesButton() {
		var $field = hwsFindTemplatesField();
		if (!$field.length) return;
		if ($field.data('hwsBtnAdded')) return;
		$field.data('hwsBtnAdded', true);
		
		var $select = $field.find('select').first();
		if (!$select.length) return;
		
		// Create button
		var $controls = $('<div class="hws-portal-import-controls" style="margin-top:8px;"></div>');
		var $btn = $('<button type="button" class="button button-secondary">Import Default Templates</button>');
		var $status = $('<span style="margin-left:10px; display:none;"></span>');
		$controls.append($btn).append($status);
		$field.append($controls);
		
		// Button click
		$btn.on('click', function(e) {
			e.preventDefault();
			e.stopPropagation();
			$status.text('').hide();
			hwsImportTemplates($select, $status);
			return false;
		});
		
		// Auto-import on NEW posts if empty (silent)
		var isNewPost = window.location.href.indexOf('post-new.php') !== -1;
		if (isNewPost) {
			setTimeout(function() {
				var hasOptions = $select.find('option').length > 0;
				var hasValue = $select.val() && (Array.isArray($select.val()) ? $select.val().length > 0 : true);
				if (!hasOptions && !hasValue) {
					hwsImportTemplates($select, null);
				}
			}, 1500);
		}
	}
	
	hwsSetupTemplatesButton();

	// =========================================================================
	// AUTO-CHECK DEFAULT TAXONOMY TERM ON NEW POSTS
	// =========================================================================
	function hwsAutoCheckDefaultTaxonomy() {
		if (window.location.href.indexOf('post-new.php') === -1) return;
		
		var $portalTypeBox = $('#portal_typediv');
		if (!$portalTypeBox.length) return;
		
		var defaultTerm = window.HWS_PORTAL_DEFAULT_TERM || 'the-michael-peres-podcast';
		
		var $checkboxes = $portalTypeBox.find('#portal_typechecklist input[type="checkbox"]');
		$checkboxes.each(function() {
			var $cb = $(this);
			var $label = $cb.closest('label');
			var labelText = $label.text().trim().toLowerCase().replace(/\s+/g, '-');
			
			if (labelText === defaultTerm || $label.text().trim() === 'The Michael Peres Podcast') {
				if (!$cb.prop('checked')) {
					$cb.prop('checked', true);
				}
			}
		});
	}
	
	hwsAutoCheckDefaultTaxonomy();

});
JS);

}, 20);

// =============================================================================
// PLUGIN INITIALIZATION
// =============================================================================

/**
 * Load snippets immediately after plugin file loads.
 * The snippets themselves hook into the appropriate actions:
 * - CPT/Taxonomy: hooks into 'init' priority 5
 * - ACF fields: hooks into 'acf/include_fields'
 * - ACF options: hooks into 'acf/init'
 * 
 * We must call load_enabled_snippets() NOW (not on 'init') so the hooks 
 * are registered before WordPress fires them.
 */
hws_portal_load_enabled_snippets();

\add_action('init', function () {
	if (is_admin()) {
		hws_portal_register_settings_page();
	}
}, 10);

// =============================================================================
// ACTIVITY LOGGING
// =============================================================================

/**
 * Log activity to the settings page log.
 * @param string $type Type of activity (login_success, login_failed, email_sent, etc.)
 * @param string $message Main message
 * @param string $details Additional details
 */
function hws_portal_log_activity(string $type, string $message, string $details = ''): void {
	$log = \get_option('hws_portal_activity_log', array());
	
	// Get current time in EST
	$est_tz = new \DateTimeZone('America/New_York');
	$now = new \DateTime('now', $est_tz);
	$timestamp = $now->format('Y-m-d H:i:s') . ' EST';
	
	// Add new entry
	$log[] = array(
		'timestamp' => $timestamp,
		'type'      => $type,
		'message'   => $message,
		'details'   => $details,
	);
	
	// Keep only last 500 entries
	if (count($log) > 500) {
		$log = array_slice($log, -500);
	}
	
	\update_option('hws_portal_activity_log', $log, false);
}

/**
 * AJAX handler to clear activity log.
 */
\add_action('wp_ajax_hws_portal_clear_activity_log', function () {
	if (!\wp_verify_nonce($_POST['nonce'] ?? '', 'hws_portal_clear_log')) {
		\wp_die('Invalid nonce');
	}
	if (!\current_user_can('manage_options')) {
		\wp_die('Permission denied');
	}
	\delete_option('hws_portal_activity_log');
	\wp_send_json_success();
});
