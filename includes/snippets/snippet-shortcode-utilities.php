<?php
/**
 * Snippet: Shortcode Utilities
 * 
 * Centralized shortcode documentation and utility functions.
 * Provides consistent shortcode reference across all ACF field instructions.
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register function - called by loader when snippet is enabled.
 */
function hws_portal_register_snippet_shortcode_utilities(): void {
	// This snippet provides utility functions, no hooks needed
}

/**
 * Generate shortcode documentation HTML for ACF field instructions.
 * 
 * @param string $context Context: 'full', 'basic', 'audit_kp', 'audit_ws', 'audit', 'all'
 * @return string HTML formatted shortcode documentation
 */
function hws_portal_get_shortcode_docs( string $context = 'full' ): string {
	$docs = [];
	
	// ==========================================================================
	// BASIC SHORTCODES
	// ==========================================================================
	$basic = [
		'<strong>Basic Shortcodes:</strong>',
		'<code>[name]</code> = Message Name (falls back to Recipient Name)',
		'<code>[first_name]</code> = Recipient First Name',
		'<code>[last_name]</code> = Recipient Last Name',
		'<code>[recipient_name]</code> = Recipient Full Name',
		'<code>[message_name]</code> = Message Name field',
		'<code>[email]</code> = Comma-separated recipient emails',
		'<code>[password]</code> = Post password',
		'<code>[permalink]</code> = Post permalink',
		'<code>[deliverables]</code> = Deliverables field content',
	];
	
	// ==========================================================================
	// AUDIT - KNOWLEDGE PANEL
	// ==========================================================================
	$audit_kp = [
		'<strong>Audit - Knowledge Panel:</strong>',
		'<code>[audit_knowledge_panel]</code> = Report',
		'<code>[audit_knowledge_panel_search_results]</code> = Search Results URL',
		'<code>[audit_knowledge_panel_before_image]</code> = Before Image',
		'<code>[audit_knowledge_panel_before_image size=&quot;thumbnail|medium|medium_large|large|full&quot;]</code>',
		'<code>[audit_knowledge_panel_before_image link=&quot;media_file|none&quot;]</code>',
		'<code>[audit_knowledge_panel_kgmid_image]</code> = KGMID Image',
		'<code>[audit_knowledge_panel_kgmid_image size=&quot;thumbnail|medium|medium_large|large|full&quot;]</code>',
		'<code>[audit_knowledge_panel_kgmid_image link=&quot;media_file|none&quot;]</code>',
		'<code>[audit_knowledge_panel_example_image]</code> = Example KP Image (from Theme Options)',
		'<code>[audit_knowledge_panel_kgmid]</code> = KGMID',
		'<code>[audit_knowledge_panel_kgmid format=&quot;url&quot;]</code> = Full Google search URL',
		'<code>[audit_knowledge_panel_detailed_feedback]</code> = Detailed Feedback',
	];
	
	// ==========================================================================
	// AUDIT - WEBSITE
	// ==========================================================================
	$audit_ws = [
		'<strong>Audit - Website:</strong>',
		'<code>[audit_website]</code> = Report',
		'<code>[audit_website_url_personal]</code> = URL (Personal)',
		'<code>[audit_website_url_personal format=&quot;pretty&quot;]</code> = strips https://www.',
		'<code>[audit_website_url_company]</code> = URL (Company)',
		'<code>[audit_website_url_company format=&quot;pretty&quot;]</code> = strips https://www.',
		'<code>[audit_website_image]</code> = Image (default: medium_large, lightbox)',
		'<code>[audit_website_image size=&quot;...&quot;]</code>',
		'<code>[audit_website_image link=&quot;media_file|none&quot;]</code>',
		'<code>[audit_website_detailed_feedback]</code> = Detailed Feedback',
		'<strong>Schema Validation:</strong>',
		'<code>[audit_website_report_schema_url_personal]</code> = Schema URL (Personal)',
		'<code>[audit_website_report_schema_url_company]</code> = Schema URL (Company)',
		'<code>[audit_website_report_schema_image_personal]</code> = Schema Image (Personal)',
		'<code>[audit_website_report_schema_image_company]</code> = Schema Image (Company)',
		'<code>[audit_website_schema_example_image]</code> = Example Image (Theme Options)',
		'<code>[audit_website_schema_example_url]</code> = Example URL (Theme Options)',
		'<code>[audit_website_schema_example_link]</code> = Example URL (alias)',
	];
	
	// Build output based on context
	switch ( $context ) {
		case 'basic':
			$docs = $basic;
			break;
		case 'audit_kp':
			$docs = $audit_kp;
			break;
		case 'audit_ws':
			$docs = $audit_ws;
			break;
		case 'audit':
			$docs = array_merge( $audit_kp, [''], $audit_ws );
			break;
		case 'full':
		case 'all':
		default:
			$docs = array_merge( $basic, [''], $audit_kp, [''], $audit_ws );
			break;
	}
	
	return implode( '<br>', $docs );
}
