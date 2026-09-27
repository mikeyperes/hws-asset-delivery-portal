<?php

namespace hws_portal;
if (!defined('ABSPATH')) { exit; }

/**
 * Shortcode: [display_marketing_templates]
 *
 * Usage:
 * - Place on single "asset-portal" CPT.
 * - Renders each selected Elementor template (ACF post_object field "templates") as a full-width section,
 *   one after another.
 *
 * Notes:
 * - When Elementor templates are rendered via shortcode, Elementor Pro widget assets (Slides, Gallery, etc)
 *   may not be enqueued automatically. This file forces Elementor + Elementor Pro frontend assets to load
 *   on asset-portal singles that use this shortcode / have templates selected.
 */

/**
 * Determine whether we should enqueue Elementor assets on this request.
 */
function hws_portal_marketing_templates_should_enqueue(): bool {
	if (!\is_singular(HWS_PORTAL_CPT)) {
		return false;
	}

	$post = \get_post();
	if (!$post || empty($post->ID)) {
		return false;
	}

	$has_sc = function_exists('has_shortcode') && \has_shortcode((string) ($post->post_content ?? ''), 'display_marketing_templates');

	// Also enqueue if templates are selected, even if shortcode lives in a theme builder template.
	$has_templates = false;
	if (\Hexa\PluginCore\Fields\Field::available()) {
		$templates = \Hexa\PluginCore\Fields\Field::get('templates', (int) $post->ID, false);
		$has_templates = !empty($templates);
	}

	return $has_sc || $has_templates;
}

/**
 * Enqueue Elementor + Elementor Pro frontend assets when needed.
 */
function hws_portal_marketing_templates_enqueue_elementor_assets(): void {
	if (!hws_portal_marketing_templates_should_enqueue()) {
		return;
	}

	// Elementor core assets.
	if (class_exists('\\Elementor\\Plugin')) {
		$frontend = \Elementor\Plugin::instance()->frontend;
		if (is_object($frontend)) {
			if (method_exists($frontend, 'enqueue_styles')) {
				$frontend->enqueue_styles();
			}
			if (method_exists($frontend, 'enqueue_scripts')) {
				$frontend->enqueue_scripts();
			}
		}
	}

	// Elementor Pro assets (Slides, Gallery, etc).
	if (class_exists('\\ElementorPro\\Plugin')) {
		$pro = \ElementorPro\Plugin::instance();

		// Newer versions.
		if (method_exists($pro, 'enqueue_frontend_styles')) {
			$pro->enqueue_frontend_styles();
		}
		if (method_exists($pro, 'enqueue_frontend_scripts')) {
			$pro->enqueue_frontend_scripts();
		}

		// Older versions exposed a ->frontend object.
		if (property_exists($pro, 'frontend') && is_object($pro->frontend)) {
			if (method_exists($pro->frontend, 'enqueue_styles')) {
				$pro->frontend->enqueue_styles();
			}
			if (method_exists($pro->frontend, 'enqueue_scripts')) {
				$pro->frontend->enqueue_scripts();
			}
		}
	}

	// Slides/Carousel widgets often rely on Swiper.
	if (function_exists('wp_script_is') && \wp_script_is('swiper', 'registered')) {
		\wp_enqueue_script('swiper');
	}
	if (function_exists('wp_style_is') && \wp_style_is('swiper', 'registered')) {
		\wp_enqueue_style('swiper');
	}
}

function hws_portal_register_snippet_display_marketing_templates(): void {
	// Register shortcode only once.
	\add_shortcode('display_marketing_templates', __NAMESPACE__ . '\\hws_portal_display_marketing_templates_shortcode');

	// Ensure assets are queued early enough to land in the head/footer properly.
	\add_action('\wp_enqueue_scripts', __NAMESPACE__ . '\\hws_portal_marketing_templates_enqueue_elementor_assets', 20);
}

if (!function_exists(__NAMESPACE__ . '\\hws_portal_display_marketing_templates_shortcode')) {
	function hws_portal_display_marketing_templates_shortcode($atts = array(), $content = null) {
		// Only run on single portal CPT posts.
		if (!\is_singular(HWS_PORTAL_CPT)) {
			return '';
		}

		// ACF required.
		if (!\Hexa\PluginCore\Fields\Field::available()) {
			return '';
		}

		$post_id   = (int) \get_the_ID();
		$templates = \Hexa\PluginCore\Fields\Field::get('templates', $post_id);

		if (empty($templates)) {
			return '';
		}

		// Normalize to array.
		if (!is_array($templates)) {
			$templates = array($templates);
		}

		$output = '';

		foreach ($templates as $template) {
			$template_id = 0;

			if (is_numeric($template)) {
				$template_id = (int) $template;
			} elseif (is_object($template) && !empty($template->ID)) {
				$template_id = (int) $template->ID;
			}

			if (!$template_id) {
				continue;
			}

			// Ensure template CSS is enqueued (helps when the host page is not an Elementor-built page).
			if (class_exists('\\Elementor\\Core\\Files\\CSS\\Post')) {
				$css_class = '\\Elementor\\Core\\Files\\CSS\\Post';
				$css_obj = null;
				if (method_exists($css_class, 'create')) {
					$css_obj = $css_class::create($template_id);
				} else {
					$css_obj = new $css_class($template_id);
				}
				if (is_object($css_obj) && method_exists($css_obj, 'enqueue')) {
					$css_obj->enqueue();
				}
			}

			$template_html = '';

			/**
			 * Prefer Elementor's official embedding shortcode first.
			 * This path is specifically designed to pull in the right widget assets.
			 */
			$template_html = \do_shortcode('[elementor-template id="' . (int) $template_id . '"]');

			// Fallback to Elementor API if the shortcode path returns empty.
			if ($template_html === '' && class_exists('\\Elementor\\Plugin')) {
				$template_html = \Elementor\Plugin::instance()->frontend->get_builder_content_for_display($template_id, true);
			}

			if (!empty($template_html)) {
				$output .= '<div class="marketing-template-row">';
				$output .= $template_html;
				$output .= '</div>';
			}
		}

		return $output;
	}
}
