<?php
/**
 * Snippet: Front-end Shortcode Processing
 * 
 * Processes custom shortcodes in the_content for asset-portal CPT on the front-end.
 * Supports parameterized shortcodes for URLs and images.
 * 
 * URL Parameters:
 * - format="pretty" - strips https://www. from URLs
 * 
 * Image Parameters:
 * - size="thumbnail|medium|medium_large|large|full"
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

function hws_portal_register_snippet_frontend_shortcodes(): void {
	// Only run on front-end, not admin
	if (is_admin()) {
		return;
	}
	
	\add_filter('the_content', __NAMESPACE__ . '\\hws_portal_process_content_shortcodes', 15);
}

/**
 * Process shortcodes in the_content for asset-portal posts.
 */
function hws_portal_process_content_shortcodes($content) {
	// Only process on singular asset-portal pages
	if (!is_singular(HWS_PORTAL_CPT)) {
		return $content;
	}
	
	$post_id = get_the_ID();
	if (!$post_id) {
		return $content;
	}
	
	// ==========================================================================
	// NORMALIZE CONTENT - Handle encoded brackets and quotes from WYSIWYG
	// ==========================================================================
	// Decode HTML entities (brackets, quotes)
	$content = str_replace(
		array('&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;', '&quot;', '&#34;'),
		array('[', ']', '[', ']', '[', ']', '"', '"'),
		$content
	);
	// Also decode Unicode brackets if present
	$content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	
	// Get ACF field values
	$recipient = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get('recipient', $post_id) : [];
	$message = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get('message', $post_id) : [];
	$password = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get('password', $post_id) : '';
	$deliverables = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get('delivertables', $post_id) : '';
	$audit = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get('audit', $post_id) : [];
	
	// Build recipient values
	$recipient_name = '';
	$first_name = '';
	$last_name = '';
	$email = '';
	
	if (is_array($recipient)) {
		$recipient_name = trim($recipient['name'] ?? '');
		$first_name = trim($recipient['first_name'] ?? '');
		$last_name = trim($recipient['last_name'] ?? '');
		$email = trim($recipient['email'] ?? '');
		
		// Parse name if first/last not provided
		if (empty($first_name) && empty($last_name) && !empty($recipient_name)) {
			$name_parts = explode(' ', $recipient_name, 2);
			$first_name = $name_parts[0] ?? '';
			$last_name = $name_parts[1] ?? '';
		}
	}
	
	// Get message name
	$message_name = '';
	if (is_array($message)) {
		$message_name = trim($message['name'] ?? '');
	}
	
	// Build audit values - NESTED STRUCTURE
	// Knowledge Panel group
	$audit_kp_report = '';
	$audit_kp_search_results = '';
	$audit_kp_before_image = '';
	$audit_kp_kgmid_image = '';
	$audit_kp_kgmid = '';
	$audit_kp_detailed_feedback = '';
	// Website group
	$audit_ws_url_personal = '';
	$audit_ws_url_company = '';
	$audit_ws_report = '';
	$audit_ws_image = '';
	$audit_ws_image_personal = '';
	$audit_ws_image_company = '';
	$audit_ws_detailed_feedback = '';
	$audit_ws_schema_url_personal = '';
	$audit_ws_schema_url_company = '';
	$audit_ws_schema_image_personal = '';
	$audit_ws_schema_image_company = '';
	
	if (is_array($audit)) {
		// Knowledge Panel (now a group)
		if (isset($audit['knowledge_panel']) && is_array($audit['knowledge_panel'])) {
			$audit_kp_report = $audit['knowledge_panel']['report'] ?? '';
			$audit_kp_search_results = $audit['knowledge_panel']['search_results'] ?? '';
			$audit_kp_before_image = $audit['knowledge_panel']['before_image'] ?? '';
			$audit_kp_kgmid_image = $audit['knowledge_panel']['kgmid_image'] ?? '';
			$audit_kp_kgmid = $audit['knowledge_panel']['kgmid'] ?? '';
			$audit_kp_detailed_feedback = $audit['knowledge_panel']['detailed_feedback'] ?? '';
		}
		// Website (now a group)
		if (isset($audit['website']) && is_array($audit['website'])) {
			$audit_ws_url_personal = $audit['website']['url_personal'] ?? '';
			$audit_ws_url_company = $audit['website']['url_company'] ?? '';
			$audit_ws_report = $audit['website']['report'] ?? '';
			$audit_ws_image = $audit['website']['image'] ?? '';
			$audit_ws_image_personal = $audit['website']['image_personal'] ?? '';
			$audit_ws_image_company = $audit['website']['image_company'] ?? '';
			$audit_ws_detailed_feedback = $audit['website']['detailed_feedback'] ?? '';
			// Schema fields (stored in ACF)
			$audit_ws_schema_url_personal = $audit['website']['report_schema_url_personal'] ?? '';
			$audit_ws_schema_url_company = $audit['website']['report_schema_url_company'] ?? '';
			$audit_ws_schema_image_personal = $audit['website']['report_schema_image_personal'] ?? '';
			$audit_ws_schema_image_company = $audit['website']['report_schema_image_company'] ?? '';
		}
	}
	
	// If schema URLs not stored in ACF, build dynamically from website URLs
	if (empty($audit_ws_schema_url_personal) && !empty($audit_ws_url_personal)) {
		$audit_ws_schema_url_personal = 'https://validator.schema.org/#url=' . rawurlencode($audit_ws_url_personal);
	}
	if (empty($audit_ws_schema_url_company) && !empty($audit_ws_url_company)) {
		$audit_ws_schema_url_company = 'https://validator.schema.org/#url=' . rawurlencode($audit_ws_url_company);
	}
	
	// Calculate full name (falls back through options)
	$full_name = trim($message_name);
	if (empty($full_name)) {
		$full_name = trim($recipient_name);
	}
	if (empty($full_name)) {
		$full_name = trim($first_name . ' ' . $last_name);
	}
	
	$permalink = \get_permalink($post_id);
	
	// ==========================================================================
	// GET THEME OPTION VALUES (from Portal Settings → Email Templates → Audit)
	// ACF stores options as: options_email_templates_audit_fieldname
	// ==========================================================================
	$audit_kp_example_image = '';
	$audit_ws_schema_example_image = '';
	$audit_ws_schema_example_url = '';
	
	// Get via ACF first
	if (\Hexa\PluginCore\Fields\Field::available()) {
		$email_templates = \Hexa\PluginCore\Fields\Field::get('email_templates', 'option');
		if (is_array($email_templates) && isset($email_templates['audit']) && is_array($email_templates['audit'])) {
			$audit_settings = $email_templates['audit'];
			$audit_kp_example_image = $audit_settings['knowledge_panel_example_image'] ?? '';
			$audit_ws_schema_example_image = $audit_settings['website_schema_example_image'] ?? '';
			$audit_ws_schema_example_url = $audit_settings['website_schema_example_url'] ?? '';
		}
	}
	
	// Fallback to direct option retrieval if ACF returned empty
	if (empty($audit_kp_example_image)) {
		$audit_kp_example_image = \get_option('options_email_templates_audit_knowledge_panel_example_image') ?: '';
	}
	if (empty($audit_ws_schema_example_image)) {
		$audit_ws_schema_example_image = \get_option('options_email_templates_audit_website_schema_example_image') ?: '';
	}
	if (empty($audit_ws_schema_example_url)) {
		$audit_ws_schema_example_url = \get_option('options_email_templates_audit_website_schema_example_url') ?: '';
	}
	
	// ==========================================================================
	// BUILD COMPLETE SHORTCODE DATA (needed for nested shortcode processing)
	// ==========================================================================
	$shortcode_data = [
		// Knowledge Panel
		'audit_kp_before_image'   => $audit_kp_before_image,
		'audit_kp_kgmid_image'    => $audit_kp_kgmid_image,
		'audit_kp_kgmid'          => $audit_kp_kgmid,
		'audit_kp_search_results' => $audit_kp_search_results,
		'audit_kp_example_image'  => $audit_kp_example_image,
		// Website
		'audit_ws_image'          => $audit_ws_image,
		'audit_ws_image_personal' => $audit_ws_image_personal,
		'audit_ws_image_company'  => $audit_ws_image_company,
		'audit_ws_url_personal'   => $audit_ws_url_personal,
		'audit_ws_url_company'    => $audit_ws_url_company,
		// Schema - images
		'audit_ws_schema_image_personal' => $audit_ws_schema_image_personal,
		'audit_ws_schema_image_company'  => $audit_ws_schema_image_company,
		// Schema - dynamic URLs
		'audit_ws_schema_url_personal' => $audit_ws_schema_url_personal,
		'audit_ws_schema_url_company'  => $audit_ws_schema_url_company,
		// Schema - theme options examples
		'audit_ws_schema_example_image' => $audit_ws_schema_example_image,
		'audit_ws_schema_example_url'   => $audit_ws_schema_example_url,
	];
	
	// ==========================================================================
	// DECODE WYSIWYG CONTENT (ACF may encode brackets in shortcodes)
	// This is CRITICAL for nested shortcodes to work!
	// ==========================================================================
	$decode_brackets = function($text) {
		if (empty($text)) return '';
		// Decode HTML entities
		$text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
		// Also handle specific encodings
		$text = str_replace(
			array('&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;', '&quot;', '&#34;', '&#039;'),
			array('[', ']', '[', ']', '[', ']', '"', '"', "'"),
			$text
		);
		return $text;
	};
	
	// Decode all WYSIWYG content that may contain shortcodes
	$audit_kp_report = $decode_brackets($audit_kp_report);
	$audit_kp_detailed_feedback = $decode_brackets($audit_kp_detailed_feedback);
	$audit_ws_report = $decode_brackets($audit_ws_report);
	$audit_ws_detailed_feedback = $decode_brackets($audit_ws_detailed_feedback);
	
	// ==========================================================================
	// WRAP CONTENT FIELDS (no shortcode processing inside yet)
	// ==========================================================================
	$audit_kp_report_wrapped = '';
	if (!empty($audit_kp_report)) {
		$audit_kp_report_wrapped = '<div class="shortcode shortcode_audit_knowledge_panel">' . $audit_kp_report . '</div>';
	}
	
	$audit_kp_detailed_feedback_wrapped = '';
	if (!empty($audit_kp_detailed_feedback)) {
		$audit_kp_detailed_feedback_wrapped = '<div class="shortcode shortcode_audit_knowledge_panel_detailed_feedback">' . $audit_kp_detailed_feedback . '</div>';
	}
	
	$audit_ws_report_wrapped = '';
	if (!empty($audit_ws_report)) {
		$audit_ws_report_wrapped = '<div class="shortcode shortcode_audit_website">' . $audit_ws_report . '</div>';
	}
	
	$audit_ws_detailed_feedback_wrapped = '';
	if (!empty($audit_ws_detailed_feedback)) {
		$audit_ws_detailed_feedback_wrapped = '<div class="shortcode shortcode_audit_website_detailed_feedback">' . $audit_ws_detailed_feedback . '</div>';
	}
	
	// ==========================================================================
	// SIMPLE REPLACEMENTS FIRST (inject report content which may contain shortcodes)
	// ==========================================================================
	$replacements = array(
		// Basic shortcodes
		'[name]'                  => $full_name,
		'[first_name]'            => $first_name,
		'[last_name]'             => $last_name,
		'[recipient_name]'        => $recipient_name ?: $full_name,
		'[recipient_first_name]'  => $first_name,
		'[recipient_last_name]'   => $last_name,
		'[message_name]'          => $message_name,
		'[email]'                 => $email,
		'[password]'              => $password,
		'[permalink]'             => $permalink,
		'[deliverables]'          => $deliverables,
		// Audit - Knowledge Panel (these inject content that may have more shortcodes)
		'[audit_knowledge_panel]'                     => $audit_kp_report_wrapped,
		'[audit_knowledge_panel_report]'              => $audit_kp_report_wrapped,
		'[audit_knowledge_panel_search_results]'      => $audit_kp_search_results,
		'[audit_knowledge_panel_kgmid]'               => $audit_kp_kgmid,
		'[audit_knowledge_panel_detailed_feedback]'   => $audit_kp_detailed_feedback_wrapped,
		// Audit - Website (these inject content that may have more shortcodes)
		'[audit_website]'                             => $audit_ws_report_wrapped,
		'[audit_website_report]'                      => $audit_ws_report_wrapped,
		'[audit_website_detailed_feedback]'           => $audit_ws_detailed_feedback_wrapped,
		// Schema URLs (from ACF text fields or dynamically built)
		'[audit_website_report_schema_url_personal]'  => $audit_ws_schema_url_personal,
		'[audit_website_report_schema_url_company]'   => $audit_ws_schema_url_company,
		// Schema examples from theme options
		'[audit_website_schema_example_url]'          => $audit_ws_schema_example_url,
		'[audit_website_schema_example_link]'         => $audit_ws_schema_example_url, // Alias
	);
	
	// Allow filtering for additional shortcodes
	$replacements = \apply_filters('hws_portal_content_shortcodes', $replacements, $post_id);
	
	// First pass: Process simple replacements (injects report content)
	$content = strtr($content, $replacements);
	
	// ==========================================================================
	// NOW PROCESS PARAMETERIZED SHORTCODES (including those inside injected content)
	// Uses $shortcode_data defined earlier with all necessary values
	// ==========================================================================
	$content = hws_portal_process_parameterized_shortcodes_frontend($content, $shortcode_data);
	
	// ==========================================================================
	// DECODE HTML ENTITIES FOR BRACKETS (WYSIWYG may encode them)
	// ==========================================================================
	$content = str_replace(array('&#91;', '&#93;', '&lbrack;', '&rbrack;'), array('[', ']', '[', ']'), $content);
	
	// Second pass: Process simple replacements again (catch any remaining shortcodes)
	$content = strtr($content, $replacements);
	
	// ==========================================================================
	// THIRD PASS: Comprehensive cleanup for any remaining shortcodes
	// Uses very permissive regex to catch any encoding variations
	// ==========================================================================
	
	// Schema example URL/link (very permissive matching)
	if (!empty($audit_ws_schema_example_url)) {
		$content = preg_replace('/\[\s*audit_website_schema_example_url[^\]]*\]/i', $audit_ws_schema_example_url, $content);
		$content = preg_replace('/\[\s*audit_website_schema_example_link[^\]]*\]/i', $audit_ws_schema_example_url, $content);
	} else {
		$content = preg_replace('/\[\s*audit_website_schema_example_url[^\]]*\]/i', '', $content);
		$content = preg_replace('/\[\s*audit_website_schema_example_link[^\]]*\]/i', '', $content);
	}
	
	// Schema example IMAGE (theme options)
	if (!empty($audit_ws_schema_example_image)) {
		$img_url = $audit_ws_schema_example_image;
		// If it's an ID, get the URL
		if (is_numeric($img_url)) {
			$img_src = \wp_get_attachment_image_src((int) $img_url, 'medium_large');
			$img_url = $img_src ? $img_src[0] : '';
		}
		if (!empty($img_url)) {
			$img_html = '<div class="shortcode shortcode_audit_website_schema_example_image"><img src="' . \esc_url($img_url) . '" alt="Schema Example" class="shortcode_image shortcode_audit_website_schema_example_image" /></div>';
			$content = preg_replace('/\[\s*audit_website_schema_example_image[^\]]*\]/i', $img_html, $content);
		} else {
			$content = preg_replace('/\[\s*audit_website_schema_example_image[^\]]*\]/i', '', $content);
		}
	} else {
		$content = preg_replace('/\[\s*audit_website_schema_example_image[^\]]*\]/i', '', $content);
	}
	
	// Schema URLs from per-post fields
	if (!empty($audit_ws_schema_url_personal)) {
		$content = preg_replace('/\[\s*audit_website_report_schema_url_personal[^\]]*\]/i', $audit_ws_schema_url_personal, $content);
	} else {
		$content = preg_replace('/\[\s*audit_website_report_schema_url_personal[^\]]*\]/i', '', $content);
	}
	if (!empty($audit_ws_schema_url_company)) {
		$content = preg_replace('/\[\s*audit_website_report_schema_url_company[^\]]*\]/i', $audit_ws_schema_url_company, $content);
	} else {
		$content = preg_replace('/\[\s*audit_website_report_schema_url_company[^\]]*\]/i', '', $content);
	}
	
	// Schema images from per-post fields
	$schema_images = [
		'audit_website_report_schema_image_personal' => $audit_ws_schema_image_personal,
		'audit_website_report_schema_image_company'  => $audit_ws_schema_image_company,
	];
	foreach ($schema_images as $shortcode => $img_value) {
		if (!empty($img_value)) {
			$img_url = $img_value;
			if (is_numeric($img_url)) {
				$img_src = \wp_get_attachment_image_src((int) $img_url, 'medium_large');
				$img_url = $img_src ? $img_src[0] : '';
			}
			if (!empty($img_url)) {
				$img_html = '<div class="shortcode shortcode_' . esc_attr($shortcode) . '"><img src="' . \esc_url($img_url) . '" alt="Schema Validation" class="shortcode_image shortcode_' . esc_attr($shortcode) . '" /></div>';
				$content = preg_replace('/\[\s*' . preg_quote($shortcode, '/') . '[^\]]*\]/i', $img_html, $content);
			} else {
				$content = preg_replace('/\[\s*' . preg_quote($shortcode, '/') . '[^\]]*\]/i', '', $content);
			}
		} else {
			$content = preg_replace('/\[\s*' . preg_quote($shortcode, '/') . '[^\]]*\]/i', '', $content);
		}
	}
	
	return $content;
}

/**
 * Process parameterized shortcodes for frontend.
 * Handles:
 * - URL format="pretty"
 * - KGMID format="url"
 * - Image size="..." and link="media_file|none" parameters
 */
function hws_portal_process_parameterized_shortcodes_frontend(string $content, array $data): string {
	// ==========================================================================
	// NORMALIZE QUOTES - Handle curly quotes, HTML entities, and Unicode variants
	// WYSIWYG editors love to convert straight quotes to fancy ones
	// ==========================================================================
	// HTML entities first
	$content = str_replace(
		array('&quot;', '&#34;', '&#x22;', '&ldquo;', '&rdquo;', '&lsquo;', '&rsquo;'),
		array('"', '"', '"', '"', '"', "'", "'"),
		$content
	);
	// Unicode curly quotes and prime marks (using hex codes to avoid encoding issues)
	$content = str_replace(
		array("\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\xb3", "\xe2\x80\xb2", "\xc2\xab", "\xc2\xbb", "\xe2\x80\xb9", "\xe2\x80\xba"),
		array('"', '"', "'", "'", '"', "'", '"', '"', "'", "'"),
		$content
	);
	// Decode HTML-encoded brackets (WYSIWYG may encode them)
	$content = str_replace(
		array('&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;'),
		array('[', ']', '[', ']', '[', ']'),
		$content
	);
	
	// ==========================================================================
	// URL SHORTCODES WITH format="pretty" PARAMETER
	// ==========================================================================
	$url_shortcodes = [
		'audit_website_url_personal' => $data['audit_ws_url_personal'] ?? '',
		'audit_website_url_company'  => $data['audit_ws_url_company'] ?? '',
	];
	
	foreach ($url_shortcodes as $shortcode => $value) {
		// Match with format parameter: [shortcode format="pretty"]
		$pattern = '/\[' . preg_quote($shortcode, '/') . '\s+format\s*=\s*["\']?pretty["\']?\s*\]/i';
		if (preg_match($pattern, $content)) {
			$pretty = trim($value);
			$pretty = preg_replace('/^https?:\/\//', '', $pretty);
			$pretty = preg_replace('/^www\./', '', $pretty);
			$pretty = rtrim($pretty, '/');
			$content = preg_replace($pattern, $pretty, $content);
		}
		
		// Simple replacement without parameter
		$content = str_replace('[' . $shortcode . ']', $value, $content);
	}
	
	// ==========================================================================
	// KGMID SHORTCODE WITH format="url" PARAMETER
	// ==========================================================================
	$kgmid = $data['audit_kp_kgmid'] ?? '';
	
	// Match with format="url" parameter - very flexible pattern
	// Matches: [audit_knowledge_panel_kgmid format="url"], [audit_knowledge_panel_kgmid format=url], etc.
	$kgmid_pattern = '/\[audit_knowledge_panel_kgmid\s+format\s*=\s*["\']?url["\']?\s*\]/i';
	$content = preg_replace_callback($kgmid_pattern, function($match) use ($kgmid) {
		if (!empty($kgmid)) {
			return 'https://www.google.com/search?kgmid=' . urlencode($kgmid);
		}
		return '';
	}, $content);
	
	// Also try without quotes in case they got stripped
	$kgmid_pattern_noquote = '/\[audit_knowledge_panel_kgmid\s+format\s*=\s*url\s*\]/i';
	$content = preg_replace_callback($kgmid_pattern_noquote, function($match) use ($kgmid) {
		if (!empty($kgmid)) {
			return 'https://www.google.com/search?kgmid=' . urlencode($kgmid);
		}
		return '';
	}, $content);
	
	// Simple KGMID replacement without parameter
	$content = str_replace('[audit_knowledge_panel_kgmid]', $kgmid, $content);
	
	// ==========================================================================
	// SEARCH RESULTS - simple replacement
	// ==========================================================================
	$search_results = $data['audit_kp_search_results'] ?? '';
	$content = str_replace('[audit_knowledge_panel_search_results]', $search_results, $content);
	
	// ==========================================================================
	// ALL SIMPLE SHORTCODES - ensure they work in nested content too
	// These are also in the main replacements array but we process them here
	// for nested shortcode support
	// ==========================================================================
	// These simple shortcodes need to be processed here for nested content
	$content = str_replace('[audit_website_url_personal]', $data['audit_ws_url_personal'] ?? '', $content);
	$content = str_replace('[audit_website_url_company]', $data['audit_ws_url_company'] ?? '', $content);
	// Schema URLs
	$content = str_replace('[audit_website_report_schema_url_personal]', $data['audit_ws_schema_url_personal'] ?? '', $content);
	$content = str_replace('[audit_website_report_schema_url_company]', $data['audit_ws_schema_url_company'] ?? '', $content);
	// Schema example URL and alias
	$content = str_replace('[audit_website_schema_example_url]', $data['audit_ws_schema_example_url'] ?? '', $content);
	$content = str_replace('[audit_website_schema_example_link]', $data['audit_ws_schema_example_url'] ?? '', $content);
	
	// ==========================================================================
	// IMAGE SHORTCODES WITH size AND link PARAMETERS
	// Default: size="medium_large", link="media_file" (lightbox popup)
	// link="none" = just image, no link
	// link="media_file" = link to full image with lightbox class
	// ==========================================================================
	$image_shortcodes = [
		'audit_knowledge_panel_before_image' => [
			'value' => $data['audit_kp_before_image'] ?? '',
			'alt'   => 'Knowledge Panel Before',
			'class' => 'shortcode_audit_knowledge_panel_before_image',
		],
		'audit_knowledge_panel_kgmid_image' => [
			'value' => $data['audit_kp_kgmid_image'] ?? '',
			'alt'   => 'Knowledge Panel KGMID',
			'class' => 'shortcode_audit_knowledge_panel_kgmid_image',
		],
		'audit_knowledge_panel_example_image' => [
			'value' => $data['audit_kp_example_image'] ?? '',
			'alt'   => 'Knowledge Panel Example',
			'class' => 'shortcode_audit_knowledge_panel_example_image',
		],
		'audit_website_image' => [
			'value' => $data['audit_ws_image'] ?? '',
			'alt'   => 'Website Screenshot',
			'class' => 'shortcode_audit_website_image',
		],
		'audit_personal_website_image' => [
			'value' => $data['audit_ws_image_personal'] ?? '',
			'alt'   => 'Personal Website Screenshot',
			'class' => 'shortcode_audit_personal_website_image',
		],
		'audit_company_website_image' => [
			'value' => $data['audit_ws_image_company'] ?? '',
			'alt'   => 'Company Website Screenshot',
			'class' => 'shortcode_audit_company_website_image',
		],
		'audit_website_report_schema_image_personal' => [
			'value' => $data['audit_ws_schema_image_personal'] ?? '',
			'alt'   => 'Schema Validation Personal',
			'class' => 'shortcode_audit_website_schema_image_personal',
		],
		'audit_website_report_schema_image_company' => [
			'value' => $data['audit_ws_schema_image_company'] ?? '',
			'alt'   => 'Schema Validation Company',
			'class' => 'shortcode_audit_website_schema_image_company',
		],
		'audit_website_schema_example_image' => [
			'value' => $data['audit_ws_schema_example_image'] ?? '',
			'alt'   => 'Schema Example',
			'class' => 'shortcode_audit_website_schema_example_image',
		],
	];
	
	$valid_sizes = ['thumbnail', 'medium', 'medium_large', 'large', 'full'];
	$valid_links = ['media_file', 'none'];
	
	foreach ($image_shortcodes as $shortcode => $img_data) {
		// Pattern matches [shortcode] and [shortcode with any parameters]
		// Very permissive - matches anything inside the brackets
		$pattern = '/\[' . preg_quote($shortcode, '/') . '([^\]]*)\]/i';
		
		if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER)) {
			foreach ($matches as $match) {
				$full_match = $match[0];
				$params_str = $match[1] ?? '';
				
				// Parse parameters (very flexible - handles any quote style)
				$size = 'medium_large'; // Default size
				$link = 'media_file';   // Default link (lightbox)
				
				// Extract size parameter - match size= followed by value
				if (preg_match('/size\s*=\s*[^a-z]*(thumbnail|medium_large|medium|large|full)/i', $params_str, $size_match)) {
					$size = strtolower($size_match[1]);
				}
				
				// Extract link parameter
				if (preg_match('/link\s*=\s*[^a-z]*(media_file|none)/i', $params_str, $link_match)) {
					$link = strtolower($link_match[1]);
				}
				
				// Get image URLs
				$display_url = hws_portal_get_image_url_by_size($img_data['value'], $size);
				$full_url = hws_portal_get_image_url_by_size($img_data['value'], 'full');
				
				$replacement = '';
				if (!empty($display_url)) {
					$img_tag = '<img src="' . \esc_url($display_url) . '" alt="' . \esc_attr($img_data['alt']) . '" class="shortcode_image ' . \esc_attr($img_data['class']) . '" />';
					
					if ($link === 'media_file' && !empty($full_url)) {
						// Wrap in lightbox-enabled link (WordPress gallery style)
						$replacement = '<div class="shortcode ' . \esc_attr($img_data['class']) . '">';
						$replacement .= '<a href="' . \esc_url($full_url) . '" class="hws-lightbox" data-lightbox="portal-gallery" data-title="' . \esc_attr($img_data['alt']) . '">';
						$replacement .= $img_tag;
						$replacement .= '</a>';
						$replacement .= '</div>';
					} else {
						// No link, just the image
						$replacement = '<div class="shortcode ' . \esc_attr($img_data['class']) . '">' . $img_tag . '</div>';
					}
				}
				
				$content = str_replace($full_match, $replacement, $content);
			}
		}
	}
	
	// ==========================================================================
	// PROCESS SIMPLE SHORTCODES WITH VALUES (in case they weren't caught earlier)
	// ==========================================================================
	$simple_shortcode_values = [
		'audit_website_schema_example_url'  => $data['audit_ws_schema_example_url'] ?? '',
		'audit_website_schema_example_link' => $data['audit_ws_schema_example_url'] ?? '', // Alias
		'audit_website_report_schema_url_personal' => $data['audit_ws_schema_url_personal'] ?? '',
		'audit_website_report_schema_url_company'  => $data['audit_ws_schema_url_company'] ?? '',
	];
	
	foreach ($simple_shortcode_values as $shortcode => $value) {
		// Use regex to match shortcode with optional whitespace
		$pattern = '/\[\s*' . preg_quote($shortcode, '/') . '\s*\]/i';
		$content = preg_replace($pattern, $value, $content);
	}
	
	return $content;
}

/**
 * Enqueue lightbox script and styles for image shortcodes.
 */
function hws_portal_enqueue_lightbox_assets(): void {
	if (!\is_singular(HWS_PORTAL_CPT)) {
		return;
	}
	
	// Add inline CSS for lightbox
	$lightbox_css = '
	/* HWS Portal Image Shortcode Styles */
	.shortcode img {
		max-width: 100%;
		height: auto;
		display: block;
	}
	.shortcode a.hws-lightbox {
		display: block;
		cursor: pointer;
	}
	.shortcode a.hws-lightbox:hover {
		opacity: 0.9;
	}
	.shortcode a.hws-lightbox img {
		cursor: zoom-in;
	}
	
	/* HWS Portal Lightbox Overlay */
	.hws-lightbox-overlay {
		position: fixed;
		top: 0;
		left: 0;
		width: 100%;
		height: 100%;
		background: rgba(0,0,0,0.92);
		z-index: 999999;
		display: flex;
		align-items: center;
		justify-content: center;
		cursor: zoom-out;
		animation: hwsLightboxFadeIn 0.2s ease-out;
	}
	@keyframes hwsLightboxFadeIn {
		from { opacity: 0; }
		to { opacity: 1; }
	}
	.hws-lightbox-overlay img {
		max-width: 90%;
		max-height: 90%;
		object-fit: contain;
		cursor: default;
		box-shadow: 0 5px 40px rgba(0,0,0,0.5);
		animation: hwsLightboxZoomIn 0.2s ease-out;
	}
	@keyframes hwsLightboxZoomIn {
		from { transform: scale(0.9); opacity: 0; }
		to { transform: scale(1); opacity: 1; }
	}
	.hws-lightbox-close {
		position: absolute;
		top: 15px;
		right: 20px;
		color: #fff;
		font-size: 32px;
		font-weight: 300;
		cursor: pointer;
		width: 40px;
		height: 40px;
		line-height: 40px;
		text-align: center;
		border-radius: 50%;
		transition: background 0.2s;
	}
	.hws-lightbox-close:hover {
		background: rgba(255,255,255,0.1);
	}
	';
	\wp_add_inline_style('wp-block-library', $lightbox_css);
	
	// Add inline JS for lightbox
	$lightbox_js = "
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('.hws-lightbox').forEach(function(link) {
			link.addEventListener('click', function(e) {
				e.preventDefault();
				var imgSrc = this.href;
				var title = this.getAttribute('data-title') || '';
				
				var overlay = document.createElement('div');
				overlay.className = 'hws-lightbox-overlay';
				overlay.innerHTML = '<span class=\"hws-lightbox-close\">&times;</span><img src=\"' + imgSrc + '\" alt=\"' + title + '\">';
				document.body.appendChild(overlay);
				document.body.style.overflow = 'hidden';
				
				overlay.addEventListener('click', function(e) {
					if (e.target === overlay || e.target.classList.contains('hws-lightbox-close')) {
						overlay.remove();
						document.body.style.overflow = '';
					}
				});
				
				document.addEventListener('keydown', function escHandler(e) {
					if (e.key === 'Escape') {
						overlay.remove();
						document.body.style.overflow = '';
						document.removeEventListener('keydown', escHandler);
					}
				});
			});
		});
	});
	";
	\wp_add_inline_script('jquery', $lightbox_js);
}
\add_action('wp_enqueue_scripts', __NAMESPACE__ . '\\hws_portal_enqueue_lightbox_assets');

/**
 * Get image URL by size from attachment ID or URL.
 * 
 * @param mixed  $image Attachment ID or URL
 * @param string $size  WordPress image size (default: medium_large)
 * @return string Image URL
 */
function hws_portal_get_image_url_by_size($image, string $size = 'medium_large'): string {
	if (empty($image)) {
		return '';
	}
	
	// If it's an attachment ID
	if (is_numeric($image)) {
		$image_src = \wp_get_attachment_image_src((int) $image, $size);
		return $image_src ? $image_src[0] : '';
	}
	
	// If it's a URL
	if (is_string($image) && filter_var($image, FILTER_VALIDATE_URL)) {
		// Try to get attachment ID from URL
		$attachment_id = \attachment_url_to_postid($image);
		if ($attachment_id) {
			$image_src = \wp_get_attachment_image_src($attachment_id, $size);
			return $image_src ? $image_src[0] : $image;
		}
		// Can't resize, return original
		return $image;
	}
	
	return '';
}
