<?php
/**
 * Admin Settings Page
 * 
 * Provides the settings UI under Settings > HWS Portal Settings.
 * Allows toggling of core and optional snippets, CPT configuration,
 * and displays GAS reference code.
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register the settings page and settings.
 */
function hws_portal_register_settings_page(): void {
	// Add submenu under Settings
	\add_action('admin_menu', function () {
		\add_options_page(
			'HWS Portal Settings',
			'HWS Portal Settings',
			'manage_options',
			'hws-portal-settings',
			__NAMESPACE__ . '\\hws_portal_render_settings_page'
		);
	});

	// Register settings
	\add_action('admin_init', function () {
		\register_setting('hws_portal_settings_group', HWS_PORTAL_OPTION_KEY, [
			'type' => 'array',
			'sanitize_callback' => __NAMESPACE__ . '\\hws_portal_sanitize_settings',
			'default' => hws_portal_default_settings(),
		]);
	});
}

/**
 * Core field snippets definitions - these can be toggled on/off.
 */
function hws_portal_core_snippet_definitions(): array {
	return [
		'register_cpt_asset_portal' => [
			'title' => 'CPT: Asset Portal',
			'desc'  => 'Custom post type "asset-portal" with title, editor, thumbnail support.',
		],
		'register_taxonomy_portal_type' => [
			'title' => 'Taxonomy: Portal Type',
			'desc'  => 'Hierarchical taxonomy "portal_type" for categorizing portals.',
		],
		'acf_options_page' => [
			'title' => 'Field Options Page',
			'desc'  => 'Options page "Deliverables - Settings" for global settings.',
		],
		'acf_fields_portal_settings' => [
			'title' => 'Fields: Portal Settings',
			'desc'  => <<<HTML
<details style="margin-top:4px;">
<summary style="cursor:pointer;color:#2271b1;">View field structure</summary>
<pre style="margin:8px 0 0;padding:8px;background:#f6f7f7;font-size:11px;line-height:1.4;overflow-x:auto;">
email_templates (group)
├── names (repeater)
│   └── name (text)
├── subjects (repeater)
│   └── subject (text)
├── bodys (repeater)
│   └── body (wysiwyg)
└── signatures (repeater)
    └── signature (wysiwyg)
contents (repeater)
└── content (wysiwyg)
settings (group)
└── google_sheet_submissions (text)
default_marketing_templates (post_object → elementor_library)
</pre>
</details>
HTML,
		],
		'acf_fields_portal_podcast' => [
			'title' => 'Fields: Portal Podcast Fields',
			'desc'  => <<<HTML
<details style="margin-top:4px;">
<summary style="cursor:pointer;color:#2271b1;">View field structure</summary>
<pre style="margin:8px 0 0;padding:8px;background:#f6f7f7;font-size:11px;line-height:1.4;overflow-x:auto;">
recipient (group)
├── name (text)
├── email (text)
├── first_name (text)
└── last_name (text)
pr_agent (group)
├── name (text)
├── email (text)
└── company (text)
message (group)
├── name (text)
├── subject (text)
├── body (wysiwyg)
└── signature (wysiwyg)
password (text)
templates (post_object → elementor_library, multiple)
status (text)
order_info (textarea)
history (textarea)
notes (wysiwyg)
delivertables (wysiwyg)
assets (group)
├── imdb (text)
├── press_releases (group)
│   ├── hexa_pr_wire (text)
│   ├── grit_daily (text)
│   ├── her_forward (text)
│   ├── additional_urls (wysiwyg)
│   ├── celebrity_leader (text)
│   ├── rich_reporter (text)
│   └── block_telegraph (text)
├── podcast_urls (group)
│   ├── additional_urls (wysiwyg)
│   ├── youtube (text)
│   └── website (text)
└── verified_profile (text)
</pre>
</details>
HTML,
		],
	];
}

/**
 * Optional snippet definitions.
 */
function hws_portal_snippet_definitions(): array {
	return [
		'frontend_shortcodes' => [
			'title' => 'Front-end Shortcode Processing',
			'desc'  => 'Processes [name], [password], [deliverables], etc. in post content on front-end.',
		],
		'elementor_webhook_gas_force_success' => [
			'title' => 'Elementor Webhook: GAS Force Success',
			'desc'  => 'Forces webhook calls to Apps Script to appear successful.',
		],
		'assets_shortcode' => [
			'title' => 'Marketing Sections Shortcode',
			'desc'  => 'Shortcode [assets field="..."] for the "assets" group.',
		],
		'elementor_preview_hide_header_footer' => [
			'title' => 'Elementor Preview: Hide Header/Footer',
			'desc'  => 'Hides header/footer when ?elementor-preview is present.',
		],
		'display_marketing_templates' => [
			'title' => 'Marketing Templates Shortcode',
			'desc'  => '[display_marketing_templates] renders Elementor templates.',
		],
		'snippet_asset_portal_email_functionality' => [
			'title' => 'Asset Portal Email Functionality',
			'desc'  => 'Email sending, templates, password generation, history.',
		],
		'password_protect_portal' => [
			'title' => 'Password Protect Portal',
			'desc'  => 'Protects portal pages with the "password" field.',
		],
		'login_email_notifications' => [
			'title' => 'Login Email Notifications',
			'desc'  => 'Sends email notifications on portal login success and failed attempts (2+ failures). Uses theme options email settings.',
		],
		'wpallimport_field_protection' => [
			'title' => 'WP All Import Field Protection',
			'desc'  => 'Protects fields from being overwritten during imports. Use "__SKIP__" or leave blank to keep existing values.',
		],
		'admin_columns_status' => [
			'title' => 'Admin Columns - Status',
			'desc'  => 'Adds Status column to portal list and Status field in side metabox.',
		],
	];
}

/**
 * All snippet definitions combined for sanitization.
 */
function hws_portal_all_snippet_keys(): array {
	return array_merge(
		array_keys(hws_portal_core_snippet_definitions()),
		array_keys(hws_portal_snippet_definitions())
	);
}

/**
 * Sanitize settings on save.
 */
function hws_portal_sanitize_settings($input): array {
	$defaults = hws_portal_default_settings();
	$out = $defaults;

	// Snippets - convert to 1/0
	$snips = is_array($input['snippets'] ?? null) ? $input['snippets'] : [];
	foreach (hws_portal_all_snippet_keys() as $key) {
		$out['snippets'][$key] = !empty($snips[$key]) ? 1 : 0;
	}

	// CPT settings
	if (isset($input['cpt']) && is_array($input['cpt'])) {
		$out['cpt']['slug'] = \sanitize_key($input['cpt']['slug'] ?? 'asset-portal') ?: 'asset-portal';
		$out['cpt']['singular'] = \sanitize_text_field($input['cpt']['singular'] ?? 'Portal') ?: 'Portal';
		$out['cpt']['plural'] = \sanitize_text_field($input['cpt']['plural'] ?? 'Portals') ?: 'Portals';
		$out['cpt']['menu_name'] = \sanitize_text_field($input['cpt']['menu_name'] ?? 'Portals') ?: 'Portals';
	}

	// Default term
	if (isset($input['default_term'])) {
		$out['default_term'] = \sanitize_key($input['default_term']) ?: 'the-michael-peres-podcast';
	}

	return $out;
}

/**
 * Render the settings page HTML.
 */
function hws_portal_render_settings_page(): void {
	if (!\current_user_can('manage_options')) return;

	$settings = hws_portal_get_settings();
	$snips = $settings['snippets'] ?? [];
	$cpt = $settings['cpt'] ?? [];
	$default_term = $settings['default_term'] ?? 'the-michael-peres-podcast';
	$acf_url = \admin_url('admin.php?page=' . HWS_PORTAL_OPTIONS_SLUG);

	// Load GAS reference code
	$gas_code = '';
	$gas_file = HWS_PORTAL_PATH . 'includes/admin/gas-reference-code.php';
	if (file_exists($gas_file)) {
		$gas_code = include $gas_file;
	}

	?>
	<div class="wrap">
		<h1>HWS Portal Settings</h1>

		<form method="post" action="options.php">
			<?php \settings_fields('hws_portal_settings_group'); ?>

			<h2>Custom Post Type Configuration</h2>
			<p>Configure the CPT slug and labels. <strong>Note:</strong> Changing the slug requires a permalink flush (visit Settings > Permalinks and save).</p>
			
			<table class="form-table" style="max-width:600px;">
				<tr>
					<th scope="row"><label for="cpt_slug">CPT Slug</label></th>
					<td>
						<input type="text" id="cpt_slug" name="<?php echo HWS_PORTAL_OPTION_KEY; ?>[cpt][slug]" value="<?php echo \esc_attr($cpt['slug'] ?? 'asset-portal'); ?>" class="regular-text">
						<p class="description">URL slug (lowercase, hyphens only). Current: <code><?php echo HWS_PORTAL_CPT; ?></code></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cpt_singular">Singular Name</label></th>
					<td><input type="text" id="cpt_singular" name="<?php echo HWS_PORTAL_OPTION_KEY; ?>[cpt][singular]" value="<?php echo \esc_attr($cpt['singular'] ?? 'Portal'); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="cpt_plural">Plural Name</label></th>
					<td><input type="text" id="cpt_plural" name="<?php echo HWS_PORTAL_OPTION_KEY; ?>[cpt][plural]" value="<?php echo \esc_attr($cpt['plural'] ?? 'Portals'); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="cpt_menu">Menu Name</label></th>
					<td><input type="text" id="cpt_menu" name="<?php echo HWS_PORTAL_OPTION_KEY; ?>[cpt][menu_name]" value="<?php echo \esc_attr($cpt['menu_name'] ?? 'Portals'); ?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="default_term">Default Taxonomy Term</label></th>
					<td>
						<input type="text" id="default_term" name="<?php echo HWS_PORTAL_OPTION_KEY; ?>[default_term]" value="<?php echo \esc_attr($default_term); ?>" class="regular-text">
						<p class="description">Auto-checked term slug when creating new posts.</p>
					</td>
				</tr>
			</table>

			<h2 style="margin-top:30px;">Core Components</h2>
			<p>These register the CPT, taxonomy, and field structures.</p>
			
			<table class="widefat striped" style="max-width:1000px;">
				<thead>
					<tr>
						<th style="width:80px;">Enable</th>
						<th style="width:220px;">Component</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach (hws_portal_core_snippet_definitions() as $key => $def): 
					$checked = !empty($snips[$key]) ? 'checked' : '';
					$name = \esc_attr(HWS_PORTAL_OPTION_KEY . '[snippets][' . $key . ']');
				?>
					<tr>
						<td><label><input type="checkbox" name="<?php echo $name; ?>" value="1" <?php echo $checked; ?>> On</label></td>
						<td><strong><?php echo \esc_html($def['title']); ?></strong></td>
						<td><?php echo $def['desc']; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<h2 style="margin-top:30px;">Optional Snippets</h2>
			<p>Enable or disable additional functionality.</p>

			<table class="widefat striped" style="max-width:1000px;">
				<thead>
					<tr>
						<th style="width:80px;">Enable</th>
						<th style="width:220px;">Snippet</th>
						<th>Description</th>
					</tr>
				</thead>
				<tbody>
				<?php foreach (hws_portal_snippet_definitions() as $key => $def): 
					$checked = !empty($snips[$key]) ? 'checked' : '';
					$name = \esc_attr(HWS_PORTAL_OPTION_KEY . '[snippets][' . $key . ']');
					$desc = \esc_html($def['desc']);
					
					// Add master key link for password protect
					if ($key === 'password_protect_portal' && defined('HWS_PORTAL_MASTER_KEY')) {
						$desc .= ' <a href="' . \esc_url(\home_url('/?master_key=' . HWS_PORTAL_MASTER_KEY)) . '" target="_blank">Bypass link</a>';
					}
				?>
					<tr>
						<td><label><input type="checkbox" name="<?php echo $name; ?>" value="1" <?php echo $checked; ?>> On</label></td>
						<td><strong><?php echo \esc_html($def['title']); ?></strong></td>
						<td><?php echo $desc; ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<?php \submit_button('Save Changes'); ?>
		</form>

		<hr style="margin:30px 0;">

		<h2>Theme Options</h2>
		<p><a class="button button-secondary" href="<?php echo \esc_url($acf_url); ?>" target="_blank">Open Portal Theme Options</a></p>

		<?php if ($gas_code): ?>
		<hr style="margin:30px 0;">

		<h2>Google Apps Script Reference</h2>
		<p>Copy/paste into Apps Script.</p>
		<p><button type="button" class="button" id="hws_portal_copy_gas">Copy Code</button></p>

		<textarea id="hws_portal_gas_code" readonly style="width:100%;max-width:1000px;height:400px;font-family:monospace;font-size:12px;background:#1e1e1e;color:#d4d4d4;padding:12px;border-radius:6px;"><?php echo \esc_textarea($gas_code); ?></textarea>

		<script>
		(function(){
			var btn = document.getElementById('hws_portal_copy_gas');
			var ta = document.getElementById('hws_portal_gas_code');
			if(!btn||!ta)return;
			btn.onclick = async function(){
				try {
					ta.select();
					await navigator.clipboard.writeText(ta.value);
					btn.textContent = 'Copied!';
					setTimeout(function(){ btn.textContent = 'Copy Code'; }, 1200);
				} catch(e) {
					document.execCommand('copy');
					btn.textContent = 'Copied!';
					setTimeout(function(){ btn.textContent = 'Copy Code'; }, 1200);
				}
			};
		})();
		</script>
		<?php endif; ?>

		<hr style="margin:30px 0;">

		<h2>Activity Log</h2>
		<p>Recent portal activity including logins and emails sent.</p>
		<?php
		// Get activity log from options
		$activity_log = \get_option('hws_portal_activity_log', array());
		if (empty($activity_log)) {
			echo '<p><em>No activity logged yet.</em></p>';
		} else {
			// Show most recent 100 entries
			$activity_log = array_slice(array_reverse($activity_log), 0, 100);
			?>
			<div style="max-height:400px;overflow-y:auto;border:1px solid #ccc;padding:10px;background:#f9f9f9;font-family:monospace;font-size:12px;">
			<?php foreach ($activity_log as $entry): ?>
				<div style="margin-bottom:8px;padding-bottom:8px;border-bottom:1px solid #ddd;">
					<strong><?php echo \esc_html($entry['timestamp'] ?? 'Unknown'); ?></strong>
					- <span style="color:#0073aa;"><?php echo \esc_html($entry['type'] ?? 'Unknown'); ?></span><br>
					<?php echo \esc_html($entry['message'] ?? ''); ?>
					<?php if (!empty($entry['details'])): ?>
						<br><small style="color:#666;"><?php echo \esc_html($entry['details']); ?></small>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
			</div>
			<p style="margin-top:10px;">
				<button type="button" class="button" id="hws_portal_clear_log">Clear Log</button>
			</p>
			<script>
			(function(){
				var btn = document.getElementById('hws_portal_clear_log');
				if(!btn)return;
				btn.onclick = function(){
					if(!confirm('Are you sure you want to clear the activity log?'))return;
					var xhr = new XMLHttpRequest();
					xhr.open('POST', ajaxurl);
					xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
					xhr.onload = function(){
						if(xhr.status === 200){
							btn.textContent = 'Cleared!';
							setTimeout(function(){ location.reload(); }, 500);
						}
					};
					xhr.send('action=hws_portal_clear_activity_log&nonce=<?php echo \wp_create_nonce('hws_portal_clear_log'); ?>');
				};
			})();
			</script>
		<?php } ?>
	</div>
	<?php
}
