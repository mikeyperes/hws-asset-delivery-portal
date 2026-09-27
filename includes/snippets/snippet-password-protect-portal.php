<?php
/**
 * Snippet: Password Protect Portal Pages
 * 
 * Protects single asset-portal pages with the ACF "password" field.
 * Shows a clean, standalone password form until correct password is entered.
 * Stores unlock state in a cookie per post.
 * Logs all login attempts to history field.
 * Sends email notification after 2 failed attempts.
 * 
 * Master key bypass: Add ?master_key=YOUR_KEY to URL
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register function - called by loader when snippet is enabled.
 * Uses template_redirect with high priority to intercept before theme loads.
 */
function hws_portal_register_snippet_password_protect_portal(): void {
	// Use high priority (1) to run before anything else
	\add_action('template_redirect', __NAMESPACE__ . '\\hws_portal_protect_single_page_with_password', 1);
}

/**
 * Get visitor information for logging.
 */
function hws_portal_get_visitor_info(): array {
	// IP Address - check various headers for proxied requests
	$ip = 'Unknown';
	if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
		// Cloudflare
		$ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
	} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
		// Proxy - get first IP in chain
		$ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
		$ip = trim($ips[0]);
	} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
		$ip = $_SERVER['HTTP_X_REAL_IP'];
	} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
		$ip = $_SERVER['REMOTE_ADDR'];
	}
	
	// User Agent / Browser
	$user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown';
	$browser = hws_portal_parse_browser($user_agent);
	
	// Referrer
	$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'Direct';
	
	// Request URI
	$request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
	
	// Hostname
	$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '';
	
	// Accept Language
	$language = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 50) : 'Unknown';
	
	return [
		'ip'         => $ip,
		'user_agent' => $user_agent,
		'browser'    => $browser,
		'referrer'   => $referrer,
		'request'    => $request_uri,
		'host'       => $host,
		'language'   => $language,
	];
}

/**
 * Parse browser name from user agent.
 */
function hws_portal_parse_browser(string $user_agent): string {
	$browser = 'Unknown Browser';
	$os = 'Unknown OS';
	
	// Detect browser
	if (preg_match('/MSIE|Trident/i', $user_agent)) {
		$browser = 'Internet Explorer';
	} elseif (preg_match('/Edg/i', $user_agent)) {
		$browser = 'Microsoft Edge';
	} elseif (preg_match('/Firefox/i', $user_agent)) {
		$browser = 'Firefox';
	} elseif (preg_match('/Chrome/i', $user_agent) && !preg_match('/Edg/i', $user_agent)) {
		$browser = 'Chrome';
	} elseif (preg_match('/Safari/i', $user_agent) && !preg_match('/Chrome/i', $user_agent)) {
		$browser = 'Safari';
	} elseif (preg_match('/Opera|OPR/i', $user_agent)) {
		$browser = 'Opera';
	}
	
	// Detect OS
	if (preg_match('/Windows NT 10/i', $user_agent)) {
		$os = 'Windows 10/11';
	} elseif (preg_match('/Windows/i', $user_agent)) {
		$os = 'Windows';
	} elseif (preg_match('/Macintosh|Mac OS/i', $user_agent)) {
		$os = 'macOS';
	} elseif (preg_match('/Linux/i', $user_agent)) {
		$os = 'Linux';
	} elseif (preg_match('/Android/i', $user_agent)) {
		$os = 'Android';
	} elseif (preg_match('/iPhone|iPad|iPod/i', $user_agent)) {
		$os = 'iOS';
	}
	
	// Check if mobile
	$device = 'Desktop';
	if (preg_match('/Mobile|Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i', $user_agent)) {
		$device = 'Mobile';
	} elseif (preg_match('/Tablet|iPad/i', $user_agent)) {
		$device = 'Tablet';
	}
	
	return sprintf('%s on %s (%s)', $browser, $os, $device);
}

/**
 * Log portal access to history.
 */
function hws_portal_log_access(int $post_id, string $event_type, array $extra_data = []): void {
	$visitor = hws_portal_get_visitor_info();
	
	// Choose emoji based on event type
	$emojis = [
		'login_success'    => '🔓✅',
		'login_failed'     => '🔒❌',
		'master_key_used'  => '🔑🔓',
	];
	$emoji = $emojis[$event_type] ?? '📝';
	
	// Event titles
	$titles = [
		'login_success'    => 'PORTAL LOGIN - SUCCESS',
		'login_failed'     => 'PORTAL LOGIN - FAILED',
		'master_key_used'  => 'MASTER KEY ACCESS',
	];
	$title = $emoji . ' ' . ($titles[$event_type] ?? 'PORTAL ACCESS');
	
	// Build log data
	$log_data = [
		'🌐 IP Address'    => $visitor['ip'],
		'💻 Browser'       => $visitor['browser'],
		'📱 User Agent'    => substr($visitor['user_agent'], 0, 150),
		'🔗 Referrer'      => $visitor['referrer'],
		'🌍 Language'      => $visitor['language'],
	];
	
	// Add any extra data
	foreach ($extra_data as $key => $value) {
		$log_data[$key] = $value;
	}
	
	// Use the main plugin's log function
	if (function_exists(__NAMESPACE__ . '\\hws_portal_log_to_history')) {
		\hws_portal\hws_portal_log_to_history($post_id, $title, $log_data);
	} else {
		// Fallback - log directly
		hws_portal_log_to_history_fallback($post_id, $title, $log_data);
	}
}

/**
 * Fallback log function if main one not available.
 */
function hws_portal_log_to_history_fallback(int $post_id, string $title, array $data = []): void {
	$history = '';
	if (\Hexa\PluginCore\Fields\Field::available()) {
		$history = \Hexa\PluginCore\Fields\Field::get('history', $post_id);
	}
	if (!is_string($history)) {
		$history = \get_post_meta($post_id, 'history', true);
	}
	if (!is_string($history)) {
		$history = '';
	}
	
	$log = "════════════════════════════════════════\n";
	$log .= sprintf("📅 [%s]\n", \current_time('mysql'));
	$log .= sprintf("📌 %s\n", $title);
	$log .= "────────────────────────────────────────\n";
	foreach ($data as $key => $value) {
		$log .= sprintf("%s: %s\n", $key, $value);
	}
	$log .= "════════════════════════════════════════";
	
	$new_history = $history ? ($history . "\n\n" . $log) : $log;
	
	if (\Hexa\PluginCore\Fields\Field::available()) {
		\Hexa\PluginCore\Fields\Field::update('history', $new_history, $post_id);
	} else {
		\update_post_meta($post_id, 'history', $new_history);
	}
}

/**
 * Send email notification on successful portal login.
 * Only sends if login_email_notifications is enabled in settings.
 * Uses theme options for email settings.
 */
function hws_portal_send_login_notification_email(int $post_id, string $event_type = 'success'): void {
	// Check if login email notifications are enabled
	$settings = \get_option( HWS_PORTAL_OPTION_KEY, [] );
	if ( empty( $settings['snippets']['login_email_notifications'] ) ) {
		return; // Feature disabled
	}

	$visitor = hws_portal_get_visitor_info();
	$post_title = \get_the_title($post_id);
	$ip = $visitor['ip'];
	
	// Get email settings from theme options
	$email_templates = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get('email_templates', 'option') : [];
	
	// Get "from" email - use first from theme options or fallback to admin
	$from = 'no-reply@' . wp_parse_url(home_url(), PHP_URL_HOST);
	if ( ! empty( $email_templates['froms'] ) && is_array( $email_templates['froms'] ) ) {
		$first_from = reset( $email_templates['froms'] );
		if ( ! empty( $first_from['from'] ) && is_email( $first_from['from'] ) ) {
			$from = $first_from['from'];
		}
	}
	
	// Get "to" email - use first reply-to from theme options or fallback to admin
	$to = \get_bloginfo('admin_email');
	if ( ! empty( $email_templates['reply_tos'] ) && is_array( $email_templates['reply_tos'] ) ) {
		$first_reply_to = reset( $email_templates['reply_tos'] );
		if ( ! empty( $first_reply_to['reply_to'] ) && is_email( $first_reply_to['reply_to'] ) ) {
			$to = $first_reply_to['reply_to'];
		}
	}
	
	// Get CC emails from theme options
	$cc_emails = [];
	if ( ! empty( $email_templates['ccs'] ) && is_array( $email_templates['ccs'] ) ) {
		foreach ( $email_templates['ccs'] as $row ) {
			if ( ! empty( $row['cc'] ) && is_email( $row['cc'] ) ) {
				$cc_emails[] = $row['cc'];
			}
		}
	}
	
	// Get current time in EST
	$est_tz = new \DateTimeZone('America/New_York');
	$now = new \DateTime('now', $est_tz);
	$timestamp_est = $now->format('Y-m-d H:i:s') . ' EST';
	
	// Different subject based on event type
	$site_name = wp_parse_url(home_url(), PHP_URL_HOST);
	if ($event_type === 'failed_attempts') {
		$subject = sprintf('⚠️ %s, FAILED LOGIN ATTEMPTS: %s <%s>', $site_name, $post_title, $ip);
	} else {
		$subject = sprintf('%s, portal login: %s <%s>', $site_name, $post_title, $ip);
	}
	
	// Build email body
	if ($event_type === 'failed_attempts') {
		$body = "⚠️ Multiple Failed Login Attempts Detected\n";
		$body .= "═══════════════════════════════════════════════\n\n";
		$body .= "Someone has attempted to access this portal multiple times with incorrect passwords.\n\n";
	} else {
		$body = "Portal Login Notification\n";
		$body .= "═══════════════════════════════════════════════\n\n";
	}
	
	$body .= sprintf("📄 Post: %s\n", $post_title);
	$body .= sprintf("🔗 URL: %s\n", \get_permalink($post_id));
	$body .= sprintf("📅 Time: %s\n\n", $timestamp_est);
	$body .= "Visitor Details:\n";
	$body .= "───────────────────────────────────────────────\n";
	$body .= sprintf("🌐 IP Address: %s\n", $visitor['ip']);
	$body .= sprintf("💻 Browser: %s\n", $visitor['browser']);
	$body .= sprintf("📱 User Agent: %s\n", $visitor['user_agent']);
	$body .= sprintf("🔗 Referrer: %s\n", $visitor['referrer']);
	$body .= sprintf("🌍 Language: %s\n", $visitor['language']);
	$body .= "\n═══════════════════════════════════════════════\n";
	
	// Set headers
	$headers = [
		'From: ' . $from,
		'Content-Type: text/plain; charset=UTF-8',
	];
	
	// Add CC headers if available
	if ( ! empty( $cc_emails ) ) {
		$headers[] = 'Cc: ' . implode( ', ', $cc_emails );
	}
	
	// Send email
	\wp_mail($to, $subject, $body, $headers);
}

/**
 * Get failed attempt count for this IP/post combo.
 */
function hws_portal_get_failed_attempts(int $post_id): int {
	$visitor = hws_portal_get_visitor_info();
	$ip = $visitor['ip'];
	$transient_key = 'hws_portal_failed_' . md5($post_id . '_' . $ip);
	$count = \get_transient($transient_key);
	return $count ? (int) $count : 0;
}

/**
 * Increment failed attempt count.
 */
function hws_portal_increment_failed_attempts(int $post_id): int {
	$visitor = hws_portal_get_visitor_info();
	$ip = $visitor['ip'];
	$transient_key = 'hws_portal_failed_' . md5($post_id . '_' . $ip);
	$count = hws_portal_get_failed_attempts($post_id);
	$count++;
	// Store for 1 hour
	\set_transient($transient_key, $count, HOUR_IN_SECONDS);
	return $count;
}

/**
 * Clear failed attempt count.
 */
function hws_portal_clear_failed_attempts(int $post_id): void {
	$visitor = hws_portal_get_visitor_info();
	$ip = $visitor['ip'];
	$transient_key = 'hws_portal_failed_' . md5($post_id . '_' . $ip);
	\delete_transient($transient_key);
}

/**
 * Protect single asset-portal pages with ACF password field.
 * 
 * Bypasses:
 * - Logged-in admins (edit_posts capability) - can use ?debug=true to see full unlock link
 * - Master key via URL parameter
 */
function hws_portal_protect_single_page_with_password() {
	// Front end only, single portal CPT only
	if (is_admin() || !\is_singular(HWS_PORTAL_CPT)) {
		return;
	}

	$post_id = \get_queried_object_id();
	if (!$post_id) {
		return;
	}

	// Get the ACF password for this post
	$stored_password = '';
	
	if (\Hexa\PluginCore\Fields\Field::available()) {
		$stored_password = \Hexa\PluginCore\Fields\Field::get('password', $post_id);
	}
	
	// Ensure it's a string and trim
	$stored_password = is_string($stored_password) ? trim($stored_password) : '';

	// If no password is set, do not protect
	if ($stored_password === '') {
		return;
	}

	// ==========================================================================
	// ADMIN BYPASS - Logged-in users with edit_posts capability skip protection
	// ==========================================================================
	if (\is_user_logged_in() && \current_user_can('edit_posts')) {
		// Admins can access without password
		// If ?debug=true is set, show debug info in admin bar or console
		if (isset($_GET['debug']) && $_GET['debug'] === 'true') {
			\add_action('wp_footer', function() use ($post_id, $stored_password) {
				$permalink = \get_permalink($post_id);
				echo '<!-- HWS Portal Debug Info -->';
				echo '<script>console.log("🔐 Portal Debug Info:");';
				echo 'console.log("Post ID: ' . \esc_js($post_id) . '");';
				echo 'console.log("Password: ' . \esc_js($stored_password) . '");';
				echo 'console.log("Permalink: ' . \esc_js($permalink) . '");';
				echo '</script>';
			});
		}
		return; // Allow access for admins
	}

	// ==========================================================================
	// MASTER KEY BYPASS
	// ==========================================================================
	if (defined('HWS_PORTAL_MASTER_KEY') && HWS_PORTAL_MASTER_KEY !== '') {
		if (isset($_GET['master_key']) && $_GET['master_key'] === HWS_PORTAL_MASTER_KEY) {
			// Log master key access
			hws_portal_log_access($post_id, 'master_key_used');
			return; // Allow access
		}
	}

	$cookie_name = 'asset_portal_unlocked_' . $post_id;
	$hash_key    = \wp_salt();
	$expected    = hash_hmac('sha256', $stored_password, $hash_key);

	// Check cookie
	$has_access = (isset($_COOKIE[$cookie_name]) && hash_equals($expected, $_COOKIE[$cookie_name]));

	// If already unlocked, allow through
	if ($has_access) {
		return;
	}

	$error_message = '';

	// Handle password submission
	if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['portal_password'])) {
		if (!isset($_POST['portal_unlock_nonce']) || !\wp_verify_nonce($_POST['portal_unlock_nonce'], 'portal_unlock_' . $post_id)) {
			$error_message = 'Security check failed. Please try again.';
			// Log failed attempt - bad nonce
			hws_portal_log_access($post_id, 'login_failed', ['⚠️ Reason' => 'Invalid security token (nonce)']);
		} else {
			$entered = (string) \wp_unslash($_POST['portal_password']);

			if (hash_equals($stored_password, $entered)) {
				// Log successful login
				hws_portal_log_access($post_id, 'login_success');
				
				// Send email notification
				hws_portal_send_login_notification_email($post_id, 'success');
				
				// Log to activity log
				if (function_exists(__NAMESPACE__ . '\\hws_portal_log_activity')) {
					\hws_portal\hws_portal_log_activity(
						'login_success',
						'Portal login: ' . \get_the_title($post_id),
						'IP: ' . hws_portal_get_visitor_info()['ip']
					);
				}
				
				// Clear failed attempts
				hws_portal_clear_failed_attempts($post_id);
				
				// Set cookie BEFORE any output
				setcookie(
					$cookie_name,
					$expected,
					[
						'expires'  => time() + (7 * DAY_IN_SECONDS), // 7 days instead of 1
						'path'     => '/',
						'domain'   => '',
						'secure'   => \is_ssl(),
						'httponly' => true,
						'samesite' => 'Lax',
					]
				);
				
				// Also set in $_COOKIE superglobal for immediate access
				$_COOKIE[$cookie_name] = $expected;
				
				// Redirect to same page to avoid form resubmission and ensure cookie is sent
				\wp_safe_redirect(\get_permalink($post_id));
				exit;

			} else {
				// Wrong password
				$error_message = 'Incorrect password. Please try again.';
				
				// Increment failed attempts
				$fail_count = hws_portal_increment_failed_attempts($post_id);
				
				// Log failed attempt with entered password (masked)
				$masked_password = strlen($entered) > 0 ? str_repeat('*', strlen($entered)) . ' (' . strlen($entered) . ' chars)' : '(empty)';
				hws_portal_log_access($post_id, 'login_failed', [
					'⚠️ Reason' => 'Wrong password entered',
					'🔐 Attempt' => $fail_count,
					'❌ Entered' => $masked_password,
				]);
				
				// Send email after 2 failed attempts
				if ($fail_count >= 2) {
					hws_portal_send_login_notification_email($post_id, 'failed_attempts');
					
					// Log to activity log
					if (function_exists(__NAMESPACE__ . '\\hws_portal_log_activity')) {
						\hws_portal\hws_portal_log_activity(
							'login_failed',
							'Failed login attempts: ' . \get_the_title($post_id),
							'IP: ' . hws_portal_get_visitor_info()['ip'] . ' | Attempts: ' . $fail_count
						);
					}
				}
			}
		}
	}

	// Show lock screen - completely standalone, no theme
	hws_portal_render_password_page($post_id, $error_message);
	exit;
}

/**
 * Render a completely standalone password page.
 * No wp_head, no wp_footer, no theme interference.
 */
function hws_portal_render_password_page(int $post_id, string $error_message = ''): void {
	// Prevent any caching
	\nocache_headers();
	\status_header(200);
	
	// Get post info
	$post_title = \get_the_title($post_id);
	$site_name = \get_bloginfo('name');
	$charset = \get_bloginfo('charset');
	$lang = \get_bloginfo('language');
	$nonce = \wp_create_nonce('portal_unlock_' . $post_id);
	
	// Get failed attempts for this visitor
	$fail_count = hws_portal_get_failed_attempts($post_id);
	
	// Output clean HTML - no WordPress hooks
	header('Content-Type: text/html; charset=' . $charset);
	
	echo '<!DOCTYPE html>
<html lang="' . esc_attr($lang) . '">
<head>
<meta charset="' . esc_attr($charset) . '">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title>' . esc_html($post_title) . ' - ' . esc_html($site_name) . '</title>
<style>
* {
	margin: 0;
	padding: 0;
	box-sizing: border-box;
}
html, body {
	height: 100%;
	width: 100%;
}
body {
	font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	display: flex;
	align-items: center;
	justify-content: center;
	min-height: 100vh;
	padding: 20px;
}
.password-container {
	background: #fff;
	border-radius: 12px;
	box-shadow: 0 20px 60px rgba(0,0,0,0.3);
	padding: 50px 40px;
	max-width: 420px;
	width: 100%;
	text-align: center;
}
.password-container h1 {
	font-size: 28px;
	font-weight: 700;
	color: #1a1a2e;
	margin-bottom: 12px;
	line-height: 1.3;
}
.password-container .subtitle {
	color: #6c757d;
	font-size: 16px;
	margin-bottom: 30px;
	line-height: 1.5;
}
.password-container label {
	display: block;
	text-align: left;
	font-weight: 600;
	color: #1a1a2e;
	margin-bottom: 8px;
	font-size: 14px;
}
.password-container input[type="password"] {
	width: 100%;
	padding: 14px 16px;
	font-size: 16px;
	border: 2px solid #e1e5eb;
	border-radius: 8px;
	margin-bottom: 20px;
	transition: border-color 0.2s, box-shadow 0.2s;
	outline: none;
}
.password-container input[type="password"]:focus {
	border-color: #667eea;
	box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.2);
}
.password-container input[type="password"].error {
	border-color: #dc2626;
}
.password-container button {
	width: 100%;
	background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
	color: #fff;
	border: none;
	padding: 14px 24px;
	font-size: 16px;
	font-weight: 600;
	cursor: pointer;
	border-radius: 8px;
	transition: transform 0.2s, box-shadow 0.2s;
}
.password-container button:hover {
	transform: translateY(-2px);
	box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}
.password-container button:active {
	transform: translateY(0);
}
.error-message {
	background: #fee2e2;
	border: 1px solid #fecaca;
	color: #dc2626;
	padding: 12px 16px;
	border-radius: 8px;
	margin-bottom: 20px;
	font-size: 14px;
	text-align: left;
}
.site-name {
	margin-top: 30px;
	font-size: 12px;
	color: #9ca3af;
}
.attempt-warning {
	background: #fef3c7;
	border: 1px solid #fcd34d;
	color: #92400e;
	padding: 10px 14px;
	border-radius: 8px;
	margin-bottom: 20px;
	font-size: 13px;
	text-align: left;
}
</style>
</head>
<body>
<div class="password-container">
	<h1>' . esc_html($post_title) . '</h1>
	<p class="subtitle">This page is protected. Enter the password to continue.</p>';
	
	if ($error_message !== '') {
		echo '<div class="error-message">❌ ' . esc_html($error_message) . '</div>';
	}
	
	if ($fail_count >= 2) {
		echo '<div class="attempt-warning">⚠️ Multiple failed attempts detected. The site administrator has been notified.</div>';
	}
	
	$input_class = $error_message !== '' ? 'error' : '';
	
	echo '<form method="post" autocomplete="off">
		<label for="portal-password">Password</label>
		<input type="password" name="portal_password" id="portal-password" class="' . $input_class . '" placeholder="Enter password" autofocus required>
		<input type="hidden" name="portal_unlock_nonce" value="' . esc_attr($nonce) . '">
		<button type="submit">Unlock</button>
	</form>
	<div class="site-name">Powered by Michael Peres</div>
</div>
</body>
</html>';
}
