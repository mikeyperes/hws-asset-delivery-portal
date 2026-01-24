<?php
/**
 * Snippet: Register CPT - Asset Portal
 * 
 * Registers the custom post type using settings from plugin options.
 * 
 * Default: asset-portal / Portal / Portals
 * Supports: title, author, editor, revisions, thumbnail, custom-fields
 * Taxonomies: category, portal_type
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register function - called by loader when snippet is enabled.
 * Sets up the init hook for CPT registration.
 */
function hws_portal_register_snippet_register_cpt_asset_portal(): void {
	\add_action('init', __NAMESPACE__ . '\\hws_portal_register_cpt_asset_portal', 5);
}

/**
 * Register the Asset Portal custom post type.
 * Uses dynamic labels from plugin settings.
 */
function hws_portal_register_cpt_asset_portal(): void {
	// Get labels from settings
	$labels = hws_portal_get_cpt_labels();
	$singular = $labels['singular'];
	$plural = $labels['plural'];
	$menu = $labels['menu'];
	
	\register_post_type(HWS_PORTAL_CPT, array(
		'labels' => array(
			'name'                     => $plural,
			'singular_name'            => $singular,
			'menu_name'                => $menu,
			'all_items'                => 'All ' . $plural,
			'edit_item'                => 'Edit ' . $singular,
			'view_item'                => 'View ' . $singular,
			'view_items'               => 'View ' . $plural,
			'add_new_item'             => 'Add New ' . $singular,
			'add_new'                  => 'Add New ' . $singular,
			'new_item'                 => 'New ' . $singular,
			'parent_item_colon'        => 'Parent ' . $singular . ':',
			'search_items'             => 'Search ' . $plural,
			'not_found'                => 'No ' . strtolower($plural) . ' found',
			'not_found_in_trash'       => 'No ' . strtolower($plural) . ' found in Trash',
			'archives'                 => $singular . ' Archives',
			'attributes'               => $singular . ' Attributes',
			'insert_into_item'         => 'Insert into ' . strtolower($singular),
			'uploaded_to_this_item'    => 'Uploaded to this ' . strtolower($singular),
			'filter_items_list'        => 'Filter ' . strtolower($plural) . ' list',
			'filter_by_date'           => 'Filter ' . strtolower($plural) . ' by date',
			'items_list_navigation'    => $plural . ' list navigation',
			'items_list'               => $plural . ' list',
			'item_published'           => $singular . ' published.',
			'item_published_privately' => $singular . ' published privately.',
			'item_reverted_to_draft'   => $singular . ' reverted to draft.',
			'item_scheduled'           => $singular . ' scheduled.',
			'item_updated'             => $singular . ' updated.',
			'item_link'                => $singular . ' Link',
			'item_link_description'    => 'A link to a ' . strtolower($singular) . '.',
		),
		'public' => true,
		'show_in_rest' => true,
		'menu_icon' => 'dashicons-admin-post',
		'supports' => array(
			'title',
			'author',
			'editor',
			'revisions',
			'thumbnail',
			'custom-fields',
		),
		'taxonomies' => array(
			'category',
			'portal_type',
		),
		'delete_with_user' => false,
	));
}
