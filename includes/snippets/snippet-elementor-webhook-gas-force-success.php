<?php

namespace hws_portal;
if (!defined('ABSPATH')) { exit; }

/** Set false if you do not want response body previews in logs */
if (!defined('HWS_WEBHOOK_LOG_BODY_PREVIEW')) {
	define('HWS_WEBHOOK_LOG_BODY_PREVIEW', true);
}

function hws_portal_register_snippet_elementor_webhook_gas_force_success(): void {

	/**
	 * (Optional but recommended)
	 * Helps the webhook actually reach Google by following redirects AND preserving POST.
	 * This does NOT change Elementor's UI decision by itself, it just improves delivery.
	 */
	\add_action('http_api_curl', function($handle, $r, $url) {
		if (!hws_is_elementor_form_ajax()) return;
		if (!hws_is_google_apps_script_url($url)) return;

		// Follow redirects
		curl_setopt($handle, CURLOPT_FOLLOWLOCATION, true);
		curl_setopt($handle, CURLOPT_MAXREDIRS, 5);

		// Preserve POST on redirect when supported
		if (defined('CURLOPT_POSTREDIR') && defined('CURL_REDIR_POST_ALL')) {
			curl_setopt($handle, CURLOPT_POSTREDIR, CURL_REDIR_POST_ALL);
		}

		// Slightly more forgiving
		curl_setopt($handle, CURLOPT_TIMEOUT, 15);
	}, 10, 3);

	/**
	 * Rewrite the HTTP response to 200 OK so Elementor ALWAYS treats webhook as success.
	 * This is the main "overwrite fail, write success" behavior.
	 */
	\add_filter('http_response', function ($response, $args, $url) {

		if (!hws_is_elementor_form_ajax()) return $response;
		if (!hws_is_google_apps_script_url($url)) return $response;

		$request_id = hws_req_id();

		$orig_code = isset($response['response']['code']) ? (int)$response['response']['code'] : 0;
		$orig_msg  = isset($response['response']['message']) ? (string)$response['response']['message'] : '';

		$headers = $response['headers'] ?? [];
		$location = hws_get_header_value($headers, 'location');

		$body_preview = '';
		if (HWS_WEBHOOK_LOG_BODY_PREVIEW && isset($response['body'])) {
			$body_preview = hws_preview((string)$response['body'], 2000);
		}

		hws_log($request_id, 'GAS_ORIGINAL_RESPONSE', [
			'url'       => $url,
			'orig_code' => $orig_code,
			'orig_msg'  => $orig_msg,
			'location'  => $location,
			'body_prev' => $body_preview,
		]);

		// Force Elementor to see success
		$response['response']['code'] = 200;
		$response['response']['message'] = 'OK';

		// Marker headers so you can prove the rewrite happened
		if (!isset($response['headers']) || !is_array($response['headers'])) {
			$response['headers'] = [];
		}
		$response['headers']['x-hws-forced-success'] = '1';
		$response['headers']['x-hws-original-code']  = (string)$orig_code;

		hws_log($request_id, 'GAS_FORCED_SUCCESS', [
			'url' => $url,
			'forced_code' => 200,
		]);

		return $response;

	}, 999, 3);

	/**
	 * Log WP_Error cases too (timeouts, SSL, DNS).
	 * Note: WP_Error cannot be rewritten by http_response because there is no array response.
	 * The curl redirect settings above reduce how often WP_Error happens with Apps Script.
	 */
	\add_action('http_api_debug', function ($resp, $type, $class, $args, $url) {

		if (!hws_is_elementor_form_ajax()) return;
		if (!hws_is_google_apps_script_url($url)) return;

		$request_id = hws_req_id();

		if ($type === 'response' && \is_wp_error($resp)) {
			hws_log($request_id, 'GAS_WP_ERROR', [
				'url'     => $url,
				'code'    => $resp->get_error_code(),
				'message' => $resp->get_error_message(),
				'data'    => $resp->get_error_data(),
			]);
		}

	}, 10, 5);
}

/** Helpers */

function hws_is_elementor_form_ajax(): bool {
	if (!defined('DOING_AJAX') || !DOING_AJAX) return false;

	$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
	// Elementor Pro Forms submit action
	return ($action === 'elementor_pro_forms_send_form');
}

function hws_is_google_apps_script_url(string $url): bool {
	$parts = \wp_parse_url($url);
	if (!$parts || empty($parts['host'])) return false;

	$host = strtolower((string)$parts['host']);
	if ($host !== 'script.google.com' && $host !== 'script.googleusercontent.com') {
		return false;
	}

	$path = (string)($parts['path'] ?? '');
	// Apps Script web app paths live under /macros/...
	return (strpos($path, '/macros/') === 0);
}

function hws_req_id(): string {
	static $id = '';
	if ($id !== '') return $id;
	$id = 'ELEMWH_' . gmdate('Ymd_His') . '_' . \wp_generate_password(6, false, false);
	return $id;
}

function hws_get_header_value($headers, string $key): string {
	$key = strtolower($key);

	if (is_array($headers)) {
		foreach ($headers as $k => $v) {
			if (strtolower((string)$k) === $key) return (string)$v;
		}
		return '';
	}

	if ($headers instanceof \Requests_Response_Headers) {
		return isset($headers[$key]) ? (string)$headers[$key] : '';
	}

	return '';
}

function hws_preview(string $s, int $max): string {
	$s = preg_replace('/\s+/', ' ', $s);
	if (strlen($s) > $max) return substr($s, 0, $max) . '...[truncated]';
	return $s;
}

function hws_log(string $request_id, string $label, $data = null): void {
	$uploads = \wp_upload_dir();
	$dir = \trailingslashit($uploads['basedir']) . 'elementor-webhook-force-success';

	if (!file_exists($dir)) {
		\wp_mkdir_p($dir);
	}

	$file = \trailingslashit($dir) . 'requests-' . gmdate('Y-m-d') . '.log';

	$ts = gmdate('c');
	$line = "[{$ts}] [{$request_id}] {$label}";

	if ($data !== null) {
		$line .= ' :: ' . (is_string($data) ? $data : \wp_json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
	}

	$line .= "\n";

	@file_put_contents($file, $line, FILE_APPEND);
	error_log(trim($line));
}
