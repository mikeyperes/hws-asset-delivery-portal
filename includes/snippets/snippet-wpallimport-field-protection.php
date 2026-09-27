<?php
/**
 * Snippet: WP All Import Field Protection
 * 
 * Protects specified ACF fields from being overwritten during WP All Import.
 * If the incoming value is "__SKIP__" or blank, the existing value is preserved.
 * 
 * Usage in WP All Import template:
 * - Set field value to "__SKIP__" to always keep existing value
 * - Leave blank to keep existing value (if field is in protected list)
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register function - called by loader when snippet is enabled.
 */
function hws_portal_register_snippet_wpallimport_field_protection(): void {
	// Only run if WP All Import is active
	if (!function_exists('is_plugin_active')) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	
	// Hook into WP All Import ACF field processing
	\add_filter('pmxi_acf_custom_field', __NAMESPACE__ . '\\hws_portal_protect_acf_field_on_import', 10, 3);
}

/**
 * Get list of protected ACF field names.
 * These fields will not be overwritten if incoming value is "__SKIP__" or blank.
 * 
 * @return array List of field names to protect
 */
function hws_portal_get_protected_import_fields(): array {
	$protected_fields = array(
		// Audit - Knowledge Panel fields
		'detailed_feedback',                           // Direct field name (nested)
		'audit_knowledge_panel_detailed_feedback',     // Flat reference
		'report',                                      // Audit report content
		
		// Audit - Website fields  
		'audit_website_detailed_feedback',
		
		// Add more field names as needed
	);
	
	// Allow filtering to add/remove protected fields
	return \apply_filters('hws_portal_protected_import_fields', $protected_fields);
}

/**
 * Protect ACF fields from being overwritten during WP All Import.
 * 
 * @param mixed  $value   The incoming value from import
 * @param int    $post_id The post ID being imported
 * @param string $name    The ACF field name
 * @return mixed The value to save (original or existing)
 */
function hws_portal_protect_acf_field_on_import($value, $post_id, $name) {
	// Get list of protected fields
	$protected_fields = hws_portal_get_protected_import_fields();
	
	// Check if this field should be protected
	// Match by exact name or by field name ending (for nested fields)
	$is_protected = false;
	foreach ($protected_fields as $protected) {
		if ($name === $protected || str_ends_with($name, '_' . $protected)) {
			$is_protected = true;
			break;
		}
	}
	
	if (!$is_protected) {
		return $value;
	}
	
	// Check if value should be skipped
	$should_skip = false;
	
	// Skip if value is literally "__SKIP__"
	if (is_string($value) && trim($value) === '__SKIP__') {
		$should_skip = true;
	}
	
	// Skip if value is blank (null, empty string, or whitespace only)
	if ($value === null || $value === '' || (is_string($value) && trim($value) === '')) {
		$should_skip = true;
	}
	
	// If skipping, return the existing value
	if ($should_skip) {
		// Use ACF's get_field for proper value retrieval (handles groups, arrays, etc.)
		if (\Hexa\PluginCore\Fields\Field::available()) {
			$existing = \Hexa\PluginCore\Fields\Field::get($name, $post_id);
			if ($existing !== null && $existing !== false) {
				return $existing;
			}
		}
		
		// Fallback to post meta if ACF function doesn't work
		$existing_meta = \get_post_meta($post_id, $name, true);
		if (!empty($existing_meta)) {
			return $existing_meta;
		}
		
		// For nested group fields, try to get from parent
		// e.g., for "detailed_feedback" in "audit > knowledge_panel" group
		if (\Hexa\PluginCore\Fields\Field::available()) {
			$audit = \Hexa\PluginCore\Fields\Field::get('audit', $post_id);
			if (is_array($audit)) {
				// Try knowledge_panel group
				if (isset($audit['knowledge_panel'][$name])) {
					return $audit['knowledge_panel'][$name];
				}
				// Try website group
				if (isset($audit['website'][$name])) {
					return $audit['website'][$name];
				}
			}
		}
		
		// Return empty string if no existing value found (don't overwrite with import value)
		return '';
	}
	
	// Value is not empty and not __SKIP__, allow normal import
	return $value;
}

/**
 * Helper function for PHP < 8.0 compatibility
 */
if (!function_exists('str_ends_with')) {
	function str_ends_with(string $haystack, string $needle): bool {
		$length = strlen($needle);
		if ($length === 0) {
			return true;
		}
		return substr($haystack, -$length) === $needle;
	}
}
