<?php

namespace hws_portal;
if (!defined('ABSPATH')) { exit; }

function hws_portal_register_snippet_elementor_preview_hide_header_footer(): void {
	\add_action('\wp_enqueue_scripts', function () {
		if (!isset($_GET['elementor-preview'])) return;

		$css = '
			header, #site-header, .site-header,
			footer, #site-footer, .site-footer {
				display: none !important;
			}
			body { margin-top: 0 !important; padding-top: 0 !important; }
		';

		\wp_register_style('mp-el-preview-clean', false);
		\wp_enqueue_style('mp-el-preview-clean');
		\wp_add_inline_style('mp-el-preview-clean', $css);
	}, 99);
}
