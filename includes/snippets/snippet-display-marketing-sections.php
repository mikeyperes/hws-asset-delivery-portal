<?php
/**
 * Snippet: Marketing Sections Shortcode [assets]
 * 
 * Shortcode: [assets field="..."]
 * 
 * Examples:
 * - [assets field="imdb"]
 * - [assets field="press_releases.hexa_pr_wire"]
 * - [assets field="press_releases.grit_daily"]
 * - [assets field="press_releases.her_forward"]
 * - [assets field="press_releases.additional_urls"]
 * - [assets field="press_releases.celebrity_leader"]
 * - [assets field="press_releases.rich_reporter"]
 * - [assets field="press_releases.block_telegraph"]
 * - [assets field="podcast_urls.additional_urls"]
 * - [assets field="podcast_urls.youtube"]
 * - [assets field="podcast_urls.website"]
 * - [assets field="podcast_urls.google_drive_contents"]
 * - [assets field="verified_profile"]
 * 
 * Note: Both "." and "_" work as separators
 * Optional: post_id="123" (defaults to current post)
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register [assets] shortcode.
 */
function hws_portal_register_snippet_assets_shortcode(): void {
	\add_shortcode('assets', __NAMESPACE__ . '\\hws_portal_assets_shortcode_handler');
}

/**
 * [assets] shortcode handler.
 */
function hws_portal_assets_shortcode_handler($atts): string {
	$atts = \shortcode_atts(
		[
			'field'   => '',
			'post_id' => 0,
		],
		$atts,
		'assets'
	);

	$field = trim((string) $atts['field']);
	if ($field === '') { return ''; }

	if (!\Hexa\PluginCore\Fields\Field::available()) { return ''; }

	$post_id = !empty($atts['post_id']) ? absint($atts['post_id']) : (int) \get_the_ID();
	if (!$post_id) { return ''; }

	// Get the ACF group data
	$assets = \Hexa\PluginCore\Fields\Field::get('assets', $post_id);
	if (!is_array($assets) || empty($assets)) { return ''; }

	// Normalize separators to underscores
	$raw = str_replace(['.', '->', '/', '\\'], '_', $field);
	$tokens = preg_split('/_+/', strtolower($raw), -1, PREG_SPLIT_NO_EMPTY);
	if (empty($tokens)) { return ''; }

	// Normalizer for fuzzy matching
	$normalize = static function($s) {
		return preg_replace('/[^a-z0-9]/', '', strtolower((string) $s));
	};

	// Recursive resolver
	$resolve = null;
	$resolve = static function($current, $tokens, $i = 0) use (&$resolve, $normalize) {
		if ($i >= count($tokens)) { return $current; }
		if (!is_array($current)) { return null; }

		$remaining = count($tokens) - $i;
		for ($len = $remaining; $len >= 1; $len--) {
			$candidate = implode('_', array_slice($tokens, $i, $len));

			// Exact match
			if (array_key_exists($candidate, $current)) {
				return $resolve($current[$candidate], $tokens, $i + $len);
			}

			// Fuzzy match
			$cand_norm = $normalize($candidate);
			foreach ($current as $k => $v) {
				if ($normalize($k) === $cand_norm) {
					return $resolve($v, $tokens, $i + $len);
				}
			}
		}

		return null;
	};

	$value = $resolve($assets, $tokens, 0);
	if ($value === null) { return ''; }

	// Handle arrays
	if (is_array($value)) {
		$flat = [];
		array_walk_recursive($value, function($x) use (&$flat) {
			if (is_scalar($x) && (string) $x !== '') { $flat[] = (string) $x; }
		});
		return implode(', ', $flat);
	}

	// Convert to string
	$output = is_bool($value) ? ($value ? '1' : '') : (string) $value;

	// ==========================================================================
	// YOUTUBE URL EXPANSION
	// If field is podcast_urls_youtube and value looks like just an ID, expand it
	// ==========================================================================
	$field_lower = strtolower($field);
	if (strpos($field_lower, 'podcast_urls_youtube') !== false || strpos($field_lower, 'podcasturlsyoutube') !== false || strpos($field_lower, 'podcast_urls.youtube') !== false) {
		$output = trim($output);
		if ($output !== '') {
			// If it's NOT already a full URL, prepend YouTube watch URL
			if (strpos($output, 'http') !== 0 && strpos($output, '//') !== 0) {
				$output = 'https://www.youtube.com/watch?v=' . $output;
			}
		}
	}

	return $output;
}
