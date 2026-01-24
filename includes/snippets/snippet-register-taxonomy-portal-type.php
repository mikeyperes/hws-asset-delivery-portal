<?php
/**
 * Snippet: Register Taxonomy - Portal Type
 * 
 * Registers the 'portal_type' hierarchical taxonomy for asset-portal CPT.
 * 
 * Labels: Portal Types / Portal Type (displayed as "Source")
 * Hierarchical: Yes
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register function - called by loader when snippet is enabled.
 * Sets up the init hook for taxonomy registration.
 */
function hws_portal_register_snippet_register_taxonomy_portal_type(): void {
	\add_action('init', __NAMESPACE__ . '\\hws_portal_register_taxonomy_portal_type', 5);
}

/**
 * Register the Portal Type taxonomy.
 * Hierarchical taxonomy labeled as "Source" in the admin menu.
 */
function hws_portal_register_taxonomy_portal_type(): void {
	\register_taxonomy('portal_type', array(HWS_PORTAL_CPT), array(
		'labels' => array(
			'name'                  => 'Portal Types',
			'singular_name'         => 'Portal Type',
			'menu_name'             => 'Source',
			'all_items'             => 'All Source',
			'edit_item'             => 'Edit Source',
			'view_item'             => 'View Source',
			'update_item'           => 'Update Source',
			'add_new_item'          => 'Add New Source',
			'new_item_name'         => 'New Source Name',
			'parent_item'           => 'Parent Source',
			'parent_item_colon'     => 'Parent Source:',
			'search_items'          => 'Search Source',
			'not_found'             => 'No source found',
			'no_terms'              => 'No source',
			'filter_by_item'        => 'Filter by source',
			'items_list_navigation' => 'Source list navigation',
			'items_list'            => 'Source list',
			'back_to_items'         => '← Go to source',
			'item_link'             => 'Source Link',
			'item_link_description' => 'A link to a source',
		),
		'public' => true,
		'hierarchical' => true,
		'show_in_menu' => true,
		'show_in_rest' => true,
	));
}
