<?php
/**
 * Snippet: Admin Columns - Status
 * 
 * Adds Status column to Asset Portal CPT admin list
 * and moves Status field to side metabox
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

function hws_portal_register_snippet_admin_columns_status(): void {
	// Add Status column to admin list
	\add_filter('manage_' . HWS_PORTAL_CPT . '_posts_columns', __NAMESPACE__ . '\\add_status_column');
	\add_action('manage_' . HWS_PORTAL_CPT . '_posts_custom_column', __NAMESPACE__ . '\\render_status_column', 10, 2);
	
	// Make Status column sortable
	\add_filter('manage_edit-' . HWS_PORTAL_CPT . '_sortable_columns', __NAMESPACE__ . '\\make_status_column_sortable');
	\add_action('pre_get_posts', __NAMESPACE__ . '\\sort_by_status');
	
	// Register a separate field group for Status in side metabox
	\add_action('acf/include_fields', __NAMESPACE__ . '\\register_status_side_metabox');
}

/**
 * Add Status column after Title
 */
function add_status_column($columns) {
	$new_columns = array();
	foreach ($columns as $key => $value) {
		$new_columns[$key] = $value;
		if ($key === 'title') {
			$new_columns['portal_status'] = 'Status';
		}
	}
	return $new_columns;
}

/**
 * Render Status column content
 */
function render_status_column($column, $post_id) {
	if ($column !== 'portal_status') return;
	
	$status = '';
	if (function_exists('get_field')) {
		$status = \get_field('status', $post_id);
	}
	if (empty($status)) {
		$status = 'pending';
	}
	
	switch ($status) {
		case 'email_sent':
			echo '<span style="color:#2e7d32;">🟢 Email Sent</span>';
			break;
		case 'meeting_booked':
			echo '<span style="color:#1565c0;">🔵 Meeting Booked</span>';
			break;
		case 'pending':
		default:
			echo '<span style="color:#c62828;">🔴 Pending</span>';
			break;
	}
}

/**
 * Make Status column sortable
 */
function make_status_column_sortable($columns) {
	$columns['portal_status'] = 'portal_status';
	return $columns;
}

/**
 * Handle sorting by status
 */
function sort_by_status($query) {
	if (!is_admin() || !$query->is_main_query()) return;
	
	if ($query->get('post_type') !== HWS_PORTAL_CPT) return;
	
	if ($query->get('orderby') === 'portal_status') {
		$query->set('meta_key', 'status');
		$query->set('orderby', 'meta_value');
	}
}

/**
 * Register Status field in side metabox (separate from main field group)
 */
function register_status_side_metabox() {
	if (!function_exists('acf_add_local_field_group')) return;
	
	\acf_add_local_field_group(array(
		'key' => 'group_status_side_metabox',
		'title' => 'Status',
		'fields' => array(
			array(
				'key' => 'field_status_side_display',
				'label' => '',
				'name' => 'status',
				'type' => 'select',
				'instructions' => '',
				'required' => 0,
				'conditional_logic' => 0,
				'wrapper' => array(
					'width' => '',
					'class' => '',
					'id' => '',
				),
				'choices' => array(
					'pending' => '🔴 Pending',
					'email_sent' => '🟢 Email Sent',
					'meeting_booked' => '🔵 Meeting Booked',
				),
				'default_value' => 'pending',
				'return_format' => 'value',
				'multiple' => 0,
				'allow_null' => 0,
				'ui' => 0,
				'ajax' => 0,
				'placeholder' => '',
			),
		),
		'location' => array(
			array(
				array(
					'param' => 'post_type',
					'operator' => '==',
					'value' => HWS_PORTAL_CPT,
				),
			),
		),
		'menu_order' => 0,
		'position' => 'side',
		'style' => 'default',
		'label_placement' => 'top',
		'instruction_placement' => 'label',
		'hide_on_screen' => '',
		'active' => true,
		'description' => '',
		'show_in_rest' => 0,
	));
}
