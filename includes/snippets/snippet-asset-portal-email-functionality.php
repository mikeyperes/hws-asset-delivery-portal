<?php
/**
 * Snippet: Asset Portal Email Functionality
 * 
 * Email sending + history/password automation + template dropdowns for ACF fields.
 */

namespace hws_portal;

if (!defined('ABSPATH')) { exit; }

/**
 * Register function - called by loader when snippet is enabled.
 * The hooks below are registered at file load time, so this is just a marker.
 */
function hws_portal_register_snippet_asset_portal_email_functionality(): void {
	// Hooks are registered below when file is loaded
}

/**
 * Asset Portal CPT - Email sending + history/password automation for ACF fields.
 * Plus template dropdowns + Import buttons for:
 * - Message > Name / Subject / Body / Signature (ACF fields)
 * - WP Content editor (the_content) on single Asset Portal edit screen
 *
 * Template sources (ACF Options: email_templates group):
 * - names (repeater)      -> name
 * - subjects (repeater)   -> subject
 * - bodys (repeater)      -> body (wysiwyg)            (legacy)
 * - signatures (repeater) -> signature (wysiwyg)       (new)
 * - contents (repeater)   -> content (wysiwyg editor)  (new)
 *
 * NOTE: In your actual ACF field group, "contents" is a ROOT options repeater (sibling of email_templates),
 * not inside the email_templates group. So it is retrieved with get_field('contents','option').
 */

/**
 * -------------------------------
 * ACF helper: resolve field key by name (robust in admin-ajax)
 * -------------------------------
 */

function asset_portal_find_field_key_recursive( $fields, $target_name ) {
	if ( empty( $fields ) || ! is_array( $fields ) ) {
		return '';
	}

	foreach ( $fields as $f ) {
		if ( empty( $f ) || ! is_array( $f ) ) {
			continue;
		}

		if ( ! empty( $f['name'] ) && $f['name'] === $target_name && ! empty( $f['key'] ) ) {
			return (string) $f['key'];
		}

		// Groups / repeaters / flexible content can contain sub_fields.
		if ( ! empty( $f['sub_fields'] ) && is_array( $f['sub_fields'] ) ) {
			$found = asset_portal_find_field_key_recursive( $f['sub_fields'], $target_name );
			if ( $found ) {
				return $found;
			}
		}

		// Flexible content layouts.
		if ( ! empty( $f['layouts'] ) && is_array( $f['layouts'] ) ) {
			foreach ( $f['layouts'] as $layout ) {
				if ( ! empty( $layout['sub_fields'] ) && is_array( $layout['sub_fields'] ) ) {
					$found = asset_portal_find_field_key_recursive( $layout['sub_fields'], $target_name );
					if ( $found ) {
						return $found;
					}
				}
			}
		}
	}

	return '';
}

function asset_portal_get_field_key_for_asset_portal_posttype( $field_name, $post_id = 0 ) {
	$field_name = (string) $field_name;
	if ( $field_name === '' ) {
		return '';
	}

	// 1) If ACF already stored the reference key, use it.
	if ( $post_id ) {
		$maybe = \get_post_meta( $post_id, '_' . $field_name, true );
		if ( is_string( $maybe ) && strpos( $maybe, 'field_' ) === 0 ) {
			return $maybe;
		}
	}

	// 2) Scan ACF field groups for portal CPT.
	if ( \Hexa\PluginCore\Fields\Field::available() && \Hexa\PluginCore\Fields\Field::available() ) {
		$groups = \Hexa\PluginCore\Fields\FieldGroups::all( array( 'post_type' => HWS_PORTAL_CPT ) );
		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				if ( empty( $group['key'] ) ) {
					continue;
				}
				$fields = \Hexa\PluginCore\Fields\FieldGroups::fields( $group['key'] );
				$found  = asset_portal_find_field_key_recursive( $fields, $field_name );
				if ( $found ) {
					return $found;
				}
			}
		}
	}

	return '';
}

function asset_portal_get_history_value( $post_id ) {
	$val = '';
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$val = \Hexa\PluginCore\Fields\Field::get( 'history', $post_id );
	}
	if ( ! is_string( $val ) ) {
		$val = \get_post_meta( $post_id, 'history', true );
	}
	return is_string( $val ) ? $val : '';
}

function asset_portal_set_history_value( $post_id, $new_history ) {
	$new_history = is_string( $new_history ) ? $new_history : '';

	$field_key = asset_portal_get_field_key_for_asset_portal_posttype( 'history', $post_id );

	// Prefer update_field with field key if we have it.
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		if ( $field_key ) {
			$ok = \Hexa\PluginCore\Fields\Field::update( $field_key, $new_history, $post_id );
			if ( $ok ) {
				// Keep the reference meta correct too.
				\update_post_meta( $post_id, '_history', $field_key );
				return true;
			}
		}

		// Try name-based as a secondary attempt.
		$ok = \Hexa\PluginCore\Fields\Field::update( 'history', $new_history, $post_id );
		if ( $ok ) {
			if ( $field_key ) {
				\update_post_meta( $post_id, '_history', $field_key );
			}
			return true;
		}
	}

	// Hard fallback: update post meta directly.
	\update_post_meta( $post_id, 'history', $new_history );
	if ( $field_key ) {
		\update_post_meta( $post_id, '_history', $field_key );
	}
	return true;
}

function asset_portal_append_history_block( $post_id, $log_block ) {
	$history = asset_portal_get_history_value( $post_id );
	$history = is_string( $history ) ? $history : '';

	$log_block = is_string( $log_block ) ? $log_block : '';
	if ( $log_block === '' ) {
		return;
	}

	$new_history = $history ? ( $history . "\n" . $log_block ) : $log_block;
	asset_portal_set_history_value( $post_id, $new_history );
}

/**
 * -------------------------------
 * Admin UI JS (name-based, no field keys)
 * Only load on admin
 * -------------------------------
 */

if ( is_admin() ) {
	\add_action( 'admin_footer-post.php', __NAMESPACE__ . '\\asset_portal_admin_footer_js_for_acf_email' );
	\add_action( 'admin_footer-post-new.php', __NAMESPACE__ . '\\asset_portal_admin_footer_js_for_acf_email' );
}

function asset_portal_admin_footer_js_for_acf_email() {
	$screen = function_exists( 'get_current_screen' ) ? \get_current_screen() : null;
	if ( ! $screen || empty( $screen->post_type ) || $screen->post_type !== HWS_PORTAL_CPT ) {
		return;
	}

	$email_templates = \Hexa\PluginCore\Fields\Field::get( 'email_templates', 'option' );

	// ROOT options repeater: contents -> content (wysiwyg)
	$root_contents = \Hexa\PluginCore\Fields\Field::get( 'contents', 'option' );

	// Get Default Marketing Templates from settings (for auto-import on new posts)
	$default_marketing_templates = \Hexa\PluginCore\Fields\Field::get( 'default_marketing_templates', 'option' );
	$default_templates_data = array();
	if ( ! empty( $default_marketing_templates ) && is_array( $default_marketing_templates ) ) {
		foreach ( $default_marketing_templates as $template ) {
			if ( is_object( $template ) && ! empty( $template->ID ) ) {
				$default_templates_data[] = array(
					'id'    => (int) $template->ID,
					'title' => html_entity_decode( \get_the_title( $template->ID ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				);
			} elseif ( is_numeric( $template ) ) {
				$default_templates_data[] = array(
					'id'    => (int) $template,
					'title' => html_entity_decode( \get_the_title( (int) $template ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ),
				);
			}
		}
	}

	$template_names      = array();
	$template_subjects   = array();
	$template_bodys      = array(); // used for Message > Body import
	$template_contents   = array(); // used for WP the_content import
	$template_signatures = array(); // used for Message > Signature import
	$template_froms      = array(); // used for Message > From import
	$template_reply_tos  = array(); // used for Message > Reply-To import
	$template_ccs        = array(); // used for CC emails import
	$template_audit_knowledge_panels = array(); // Audit > Knowledge Panel templates
	$template_audit_websites = array(); // Audit > Website templates

	// Froms repeater.
	if ( ! empty( $email_templates['froms'] ) && is_array( $email_templates['froms'] ) ) {
		foreach ( $email_templates['froms'] as $row ) {
			if ( empty( $row['from'] ) ) {
				continue;
			}
			$template_froms[] = array(
				'label' => $row['from'],
				'value' => $row['from'],
			);
		}
	}

	// Reply-Tos repeater.
	if ( ! empty( $email_templates['reply_tos'] ) && is_array( $email_templates['reply_tos'] ) ) {
		foreach ( $email_templates['reply_tos'] as $row ) {
			if ( empty( $row['reply_to'] ) ) {
				continue;
			}
			$template_reply_tos[] = array(
				'label' => $row['reply_to'],
				'value' => $row['reply_to'],
			);
		}
	}

	// CCs repeater - all CCs available for import
	if ( ! empty( $email_templates['ccs'] ) && is_array( $email_templates['ccs'] ) ) {
		foreach ( $email_templates['ccs'] as $row ) {
			if ( empty( $row['cc'] ) ) {
				continue;
			}
			$template_ccs[] = $row['cc'];
		}
	}

	// Names repeater.
	if ( ! empty( $email_templates['names'] ) && is_array( $email_templates['names'] ) ) {
		foreach ( $email_templates['names'] as $row ) {
			if ( empty( $row['name'] ) ) {
				continue;
			}
			$template_names[] = array(
				'label' => $row['name'],
				'value' => $row['name'],
			);
		}
	}

	// Subjects repeater.
	if ( ! empty( $email_templates['subjects'] ) && is_array( $email_templates['subjects'] ) ) {
		foreach ( $email_templates['subjects'] as $row ) {
			if ( empty( $row['subject'] ) ) {
				continue;
			}
			$template_subjects[] = array(
				'label' => $row['subject'],
				'value' => $row['subject'],
			);
		}
	}

	// Signatures repeater.
	if ( ! empty( $email_templates['signatures'] ) && is_array( $email_templates['signatures'] ) ) {
		$index = 1;
		foreach ( $email_templates['signatures'] as $row ) {
			if ( empty( $row['signature'] ) ) {
				$index++;
				continue;
			}

			$value = $row['signature'];

			// Use template_name if set, otherwise generate from content
			if ( ! empty( $row['template_name'] ) ) {
				$label = trim( $row['template_name'] );
			} else {
				$label = \wp_strip_all_tags( $value );
				$label = trim( preg_replace( '/\s+/', ' ', $label ) );

				if ( function_exists( 'mb_strlen' ) && mb_strlen( $label ) > 60 ) {
					$label = mb_substr( $label, 0, 57 ) . '...';
				} elseif ( strlen( $label ) > 60 ) {
					$label = substr( $label, 0, 57 ) . '...';
				}

				if ( '' === $label ) {
					$label = 'Signature ' . $index;
				}
			}

			$template_signatures[] = array(
				'label' => $label,
				'value' => $value,
			);

			$index++;
		}
	}

	// Audit > Knowledge Panels repeater (inside email_templates.audit)
	$audit_group = array();
	if (is_array($email_templates) && isset($email_templates['audit']) && is_array($email_templates['audit'])) {
		$audit_group = $email_templates['audit'];
	}
	if ( ! empty( $audit_group['knowledge_panels'] ) && is_array( $audit_group['knowledge_panels'] ) ) {
		$index = 1;
		foreach ( $audit_group['knowledge_panels'] as $row ) {
			if ( empty( $row['knowledge_panel'] ) ) {
				$index++;
				continue;
			}

			$value = $row['knowledge_panel'];

			// Use template_name if set, otherwise generate from content
			if ( ! empty( $row['template_name'] ) ) {
				$label = trim( $row['template_name'] );
			} else {
				$label = \wp_strip_all_tags( $value );
				$label = trim( preg_replace( '/\s+/', ' ', $label ) );

				if ( function_exists( 'mb_strlen' ) && mb_strlen( $label ) > 60 ) {
					$label = mb_substr( $label, 0, 57 ) . '...';
				} elseif ( strlen( $label ) > 60 ) {
					$label = substr( $label, 0, 57 ) . '...';
				}

				if ( '' === $label ) {
					$label = 'Knowledge Panel ' . $index;
				}
			}

			$template_audit_knowledge_panels[] = array(
				'label' => $label,
				'value' => $value,
			);

			$index++;
		}
	}

	// Audit > Websites repeater.
	if ( ! empty( $audit_group['websites'] ) && is_array( $audit_group['websites'] ) ) {
		$index = 1;
		foreach ( $audit_group['websites'] as $row ) {
			if ( empty( $row['website'] ) ) {
				$index++;
				continue;
			}

			$value = $row['website'];

			// Use template_name if set, otherwise generate from content
			if ( ! empty( $row['template_name'] ) ) {
				$label = trim( $row['template_name'] );
			} else {
				$label = \wp_strip_all_tags( $value );
				$label = trim( preg_replace( '/\s+/', ' ', $label ) );

				if ( function_exists( 'mb_strlen' ) && mb_strlen( $label ) > 60 ) {
					$label = mb_substr( $label, 0, 57 ) . '...';
				} elseif ( strlen( $label ) > 60 ) {
					$label = substr( $label, 0, 57 ) . '...';
				}

				if ( '' === $label ) {
					$label = 'Website Audit ' . $index;
				}
			}

			$template_audit_websites[] = array(
				'label' => $label,
				'value' => $value,
			);

			$index++;
		}
	}

	/**
	 * Build Body templates from email_templates['bodys']
	 * These are for the Message > Body WYSIWYG field
	 */
	if ( ! empty( $email_templates['bodys'] ) && is_array( $email_templates['bodys'] ) ) {
		$index = 1;
		foreach ( $email_templates['bodys'] as $row ) {
			if ( empty( $row['body'] ) ) {
				$index++;
				continue;
			}

			$value = $row['body'];

			// Use template_name if set, otherwise generate from content
			if ( ! empty( $row['template_name'] ) ) {
				$label = trim( $row['template_name'] );
			} else {
				$label = \wp_strip_all_tags( $value );
				$label = trim( preg_replace( '/\s+/', ' ', $label ) );

				if ( function_exists( 'mb_strlen' ) && mb_strlen( $label ) > 60 ) {
					$label = mb_substr( $label, 0, 57 ) . '...';
				} elseif ( strlen( $label ) > 60 ) {
					$label = substr( $label, 0, 57 ) . '...';
				}

				if ( '' === $label ) {
					$label = 'Body ' . $index;
				}
			}

			$template_bodys[] = array(
				'label' => $label,
				'value' => $value,
			);

			$index++;
		}
	}

	/**
	 * Build Content templates from ROOT contents repeater
	 * These are for the WP the_content editor
	 */
	if ( ! empty( $root_contents ) && is_array( $root_contents ) ) {
		$index = 1;
		foreach ( $root_contents as $row ) {
			if ( empty( $row['content'] ) ) {
				$index++;
				continue;
			}

			$value = $row['content'];

			// Use template_name if set, otherwise generate from content
			if ( ! empty( $row['template_name'] ) ) {
				$label = trim( $row['template_name'] );
			} else {
				$label = \wp_strip_all_tags( $value );
				$label = trim( preg_replace( '/\s+/', ' ', $label ) );

				if ( function_exists( 'mb_strlen' ) && mb_strlen( $label ) > 60 ) {
					$label = mb_substr( $label, 0, 57 ) . '...';
				} elseif ( strlen( $label ) > 60 ) {
					$label = substr( $label, 0, 57 ) . '...';
				}

				if ( '' === $label ) {
					$label = 'Content ' . $index;
				}
			}

			$template_contents[] = array(
				'label' => $label,
				'value' => $value,
			);

			$index++;
		}
	}

	$template_options = array(
		'names'      => $template_names,
		'subjects'   => $template_subjects,
		'bodys'      => $template_bodys,
		'contents'   => $template_contents,
		'signatures' => $template_signatures,
		'froms'      => $template_froms,
		'reply_tos'  => $template_reply_tos,
		'ccs'        => $template_ccs,
		'audit_knowledge_panels' => $template_audit_knowledge_panels,
		'audit_websites' => $template_audit_websites,
	);
	?>
	<script type="text/javascript">
		window.assetPortalTemplateOptions = <?php echo \wp_json_encode( $template_options ); ?>;
		window.assetPortalDefaultTemplates = <?php echo \wp_json_encode( $default_templates_data ); ?>;

		jQuery(function($) {

			if (typeof acf === 'undefined') return;

			function assetPortalGetPostId() {
				var id = $('#post_ID').val();
				if (!id) return 0;
				return parseInt(id, 10) || 0;
			}

			// --- ACF helpers (NAME-based, with group scoping) ---
			function getFieldByNameRoot(fieldName) {
				var $el = $('.acf-field[data-name="' + fieldName + '"]').first();
				if (!$el.length) return null;
				return acf.getField($el);
			}

			function getGroupEl(groupName) {
				return $('.acf-field[data-name="' + groupName + '"]').first();
			}

			function getFieldInGroup(groupName, subFieldName) {
				var $group = getGroupEl(groupName);
				if (!$group.length) return null;
				var $el = $group.find('.acf-field[data-name="' + subFieldName + '"]').first();
				if (!$el.length) return null;
				return acf.getField($el);
			}

			function getWysiwygHtml(fieldObj) {
				if (!fieldObj) return '';
				try {
					if (fieldObj.get && fieldObj.get('type') === 'wysiwyg') {
						var $textarea = fieldObj.$el.find('textarea.wp-editor-area');
						if ($textarea.length) {
							var editorId = $textarea.attr('id');
							if (typeof tinymce !== 'undefined' && editorId && tinymce.get(editorId)) {
								return tinymce.get(editorId).getContent() || '';
							}
							return $textarea.val() || '';
						}
					}
					return fieldObj.val ? (fieldObj.val() || '') : '';
				} catch (e) {
					return '';
				}
			}

			function setFieldValue(fieldObj, value) {
				if (!fieldObj) return;
				var type = fieldObj.get ? fieldObj.get('type') : '';
				if (type === 'wysiwyg') {
					var $textarea = fieldObj.$el.find('textarea.wp-editor-area');
					if ($textarea.length) {
						$textarea.val(value).trigger('change');
						if (typeof tinymce !== 'undefined') {
							var editorId = $textarea.attr('id');
							var editor = tinymce.get(editorId);
							if (editor) editor.setContent(value);
						}
						return;
					}
				}
				if (fieldObj.val) {
					fieldObj.val(value);
				}
			}

			function assetPortalInjectSendEmailButton() {
				var $messageGroup = getGroupEl('message');
				if (!$messageGroup.length) return;

				if ($messageGroup.find('#asset-portal-send-email-button').length) return;

				var $controlsWrapper = $('<div class="asset-portal-email-controls" style="margin-top:10px;"></div>');
				var $buttonSend = $('<button type="button" class="button button-primary" id="asset-portal-send-email-button">Send Email</button>');
				var $buttonTest = $('<button type="button" class="button" id="asset-portal-send-test-email-button" style="margin-left:6px;">Send Test Email</button>');
				var $buttonPreview = $('<button type="button" class="button" id="asset-portal-preview-email-button" style="margin-left:6px;">Preview Email</button>');
				var $status = $('<span id="asset-portal-send-email-status" style="margin-left:10px;"></span>');

				$controlsWrapper.append($buttonSend).append($buttonTest).append($buttonPreview).append($status);
				$messageGroup.find('> .acf-input').append($controlsWrapper);

				// Preview Email handler
				$buttonPreview.on('click', function(e) {
					e.preventDefault();
					
					var postId = assetPortalGetPostId();
					if (!postId) {
						$status.text('Save the post first to preview.').css('color', 'red');
						return;
					}

					// Gather field values
					var recipientEmailField = getFieldInGroup('recipient', 'email');
					var recipientNameField  = getFieldInGroup('recipient', 'name');
					var firstNameField      = getFieldInGroup('recipient', 'first_name');
					var lastNameField       = getFieldInGroup('recipient', 'last_name');
					var messageNameField = getFieldInGroup('message', 'name');
					var subjectField     = getFieldInGroup('message', 'subject');
					var bodyField        = getFieldInGroup('message', 'body');
					var signatureField   = getFieldInGroup('message', 'signature');
					var fromField        = getFieldInGroup('message', 'from');
					var replyToField     = getFieldInGroup('message', 'reply_to');
					var passwordField = getFieldByNameRoot('password');

					// Collect CC emails from repeater field
					var ccEmails = [];
					var $ccField = $('.acf-field[data-name="cc_emails"][data-type="repeater"]').first();
					if ($ccField.length) {
						$ccField.find('.acf-row:not(.acf-clone)').each(function() {
							var $input = $(this).find('input[type="email"], input[type="text"]').first();
							if ($input.length) {
								var email = $input.val();
								if (email && email.trim().length) {
									ccEmails.push(email.trim());
								}
							}
						});
					}

					var data = {
						action: 'asset_portal_preview_email',
						nonce: '<?php echo \wp_create_nonce( 'asset_portal_preview_email' ); ?>',
						post_id: postId,
						emails: recipientEmailField ? (recipientEmailField.val() || '') : '',
						name: recipientNameField ? (recipientNameField.val() || '') : '',
						first_name: firstNameField ? (firstNameField.val() || '') : '',
						last_name: lastNameField ? (lastNameField.val() || '') : '',
						message_name: messageNameField ? (messageNameField.val() || '') : '',
						subject: subjectField ? (subjectField.val() || '') : '',
						body: getWysiwygHtml(bodyField),
						signature: getWysiwygHtml(signatureField),
						password: passwordField ? (passwordField.val() || '') : '',
						from_email: fromField ? (fromField.val() || '') : '',
						reply_to: replyToField ? (replyToField.val() || '') : '',
						cc_emails: JSON.stringify(ccEmails)
					};

					$status.text('Loading preview...').css('color', '#666');

					$.post(ajaxurl, data, function(response) {
						if (response.success && response.data.html) {
							// Create modal
							var $modal = $('<div id="asset-portal-email-preview-modal" style="position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0.7);z-index:100000;display:flex;align-items:center;justify-content:center;"></div>');
							var $content = $('<div style="background:#fff;max-width:800px;width:90%;max-height:90vh;overflow:auto;border-radius:8px;box-shadow:0 4px 20px rgba(0,0,0,0.3);"></div>');
							var $header = $('<div style="padding:15px 20px;border-bottom:1px solid #ddd;display:flex;justify-content:space-between;align-items:center;background:#f5f5f5;border-radius:8px 8px 0 0;"><strong>Email Preview</strong><button type="button" class="button" id="close-preview-modal">Close</button></div>');
							var $body = $('<div style="padding:20px;">' + response.data.html + '</div>');
							
							$content.append($header).append($body);
							$modal.append($content);
							$('body').append($modal);

							$modal.on('click', function(e) {
								if (e.target === this || $(e.target).attr('id') === 'close-preview-modal') {
									$modal.remove();
								}
							});

							$status.text('').css('color', '');
						} else {
							$status.text(response.data.message || 'Preview failed.').css('color', 'red');
						}
					}).fail(function() {
						$status.text('Preview request failed.').css('color', 'red');
					});
				});

				function doSendEmail(opts) {
					opts = opts || {};
					var forcedEmails = opts.forcedEmails || '';
					var isTestSend   = opts.isTestSend ? 1 : 0;

					var postId = assetPortalGetPostId();
					if (!postId) {
						$status.text('You must save the asset portal before sending emails.').css('color', 'red');
						return;
					}

					// Check if post is published (not draft)
					var postStatus = $('#post_status').val() || $('#original_post_status').val() || '';
					if (postStatus !== 'publish') {
						$status.text('Post must be published before sending emails. Draft URLs will not work for clients.').css('color', 'red');
						return;
					}

					// Recipient group fields (scoped)
					var recipientEmailField = getFieldInGroup('recipient', 'email');
					var recipientNameField  = getFieldInGroup('recipient', 'name');
					var firstNameField      = getFieldInGroup('recipient', 'first_name');
					var lastNameField       = getFieldInGroup('recipient', 'last_name');

					// Message group fields (scoped)
					var messageNameField = getFieldInGroup('message', 'name');
					var subjectField     = getFieldInGroup('message', 'subject');
					var bodyField        = getFieldInGroup('message', 'body');
					var signatureField   = getFieldInGroup('message', 'signature');
					var fromField        = getFieldInGroup('message', 'from');
					var replyToField     = getFieldInGroup('message', 'reply_to');

					// Root password field
					var passwordField = getFieldByNameRoot('password');

					if (!subjectField || !bodyField) {
						$status.text('Missing required ACF fields (subject, body).').css('color', 'red');
						return;
					}

					var emailsRaw = forcedEmails ? forcedEmails : (recipientEmailField ? (recipientEmailField.val() || '') : '');
					var subject   = subjectField ? (subjectField.val() || '') : '';

					var body      = getWysiwygHtml(bodyField);
					var signature = getWysiwygHtml(signatureField);

					var recipientName = recipientNameField ? (recipientNameField.val() || '') : '';
					var firstName     = firstNameField ? (firstNameField.val() || '') : '';
					var lastName      = lastNameField ? (lastNameField.val() || '') : '';

					var messageName = messageNameField ? (messageNameField.val() || '') : '';
					var password    = passwordField ? (passwordField.val() || '') : '';
					var fromEmail   = fromField ? (fromField.val() || '') : '';
					var replyTo     = replyToField ? (replyToField.val() || '') : '';

					// Collect CC emails from repeater field
					var ccEmails = [];
					var $ccField = $('.acf-field[data-name="cc_emails"]');
					if ($ccField.length) {
						$ccField.find('.acf-row:not(.acf-clone)').each(function() {
							var $input = $(this).find('input[type="email"], input[type="text"]').first();
							if ($input.length) {
								var email = $input.val();
								if (email && email.trim().length) {
									ccEmails.push(email.trim());
								}
							}
						});
					}

					if (!emailsRaw.trim().length) {
						$status.text('Recipient email is required.').css('color', 'red');
						return;
					}
					if (!subject.trim().length) {
						$status.text('Subject is required.').css('color', 'red');
						return;
					}
					if (!body.trim().length) {
						$status.text('Body is required.').css('color', 'red');
						return;
					}

					$status.text('Sending...').css('color', '');

					$.post(ajaxurl, {
						action:       'asset_portal_send_email',
						post_id:      postId,
						emails:       emailsRaw,
						subject:      subject,
						body:         body,
						signature:    signature,
						name:         recipientName,
						first_name:   firstName,
						last_name:    lastName,
						message_name: messageName,
						password:     password,
						from_email:   fromEmail,
						reply_to:     replyTo,
						cc_emails:    JSON.stringify(ccEmails),
						is_test:      isTestSend
					}, function (response) {
						if (response && response.success && response.data && response.data.message) {
							$status.text(response.data.message).css('color', 'green');
						} else if (response && response.data && response.data.message) {
							$status.text(response.data.message).css('color', 'red');
						} else {
							$status.text('Unexpected error sending email.').css('color', 'red');
						}
					});
				}

				$buttonSend.on('click', function (e) {
					e.preventDefault();
					doSendEmail({ isTestSend: 0 });
				});

				$buttonTest.on('click', function (e) {
					e.preventDefault();
					doSendEmail({ forcedEmails: 'contact@michaelperes.com', isTestSend: 1 });
				});
			}

			/**
			 * Create auto-select dropdown control (NO Import button).
			 * Value is set immediately on dropdown change.
			 * Used for: name, subject, from, reply_to
			 */
			function assetPortalCreateAutoSelectControl(fieldObj, templateType) {
				if (!fieldObj) return;

				var options = (window.assetPortalTemplateOptions && window.assetPortalTemplateOptions[templateType]) || [];
				if (!options.length) return;

				if (fieldObj.$el.find('.asset-portal-template-control[data-template-type="' + templateType + '"]').length) return;

				var $container = $('<div class="asset-portal-template-control" data-template-type="' + templateType + '" style="margin-top:6px;"></div>');
				var $select    = $('<select class="asset-portal-template-select"><option value="">Select...</option></select>');
				var $status    = $('<span class="asset-portal-template-status" style="margin-left:6px;"></span>');

				options.forEach(function(opt, index) {
					var label = opt.label || ('Template ' + (index + 1));
					$select.append('<option value="' + index + '">' + label + '</option>');
				});

				$container.append($select).append($status);

				if (typeof fieldObj.$inputWrap === 'function') {
					fieldObj.$inputWrap().append($container);
				} else {
					fieldObj.$el.find('> .acf-input').append($container);
				}

				// Auto-select on dropdown change (no Import button needed)
				$select.on('change', function() {
					var selectedIndex = $(this).val();
					if (selectedIndex === '') return;

					selectedIndex = parseInt(selectedIndex, 10);
					var template  = options[selectedIndex] || {};
					var value     = template.value || '';

					if (value.length) {
						setFieldValue(fieldObj, value);
						$status.text('✓').css('color', 'green');
						setTimeout(function() { $status.text(''); }, 1500);
					}
				});
			}

			/**
			 * Create template control WITH Import button.
			 * Used for: body, signature (WYSIWYG fields that need explicit import)
			 */
			function assetPortalCreateTemplateControl(fieldObj, templateType) {
				if (!fieldObj) return;

				var options = (window.assetPortalTemplateOptions && window.assetPortalTemplateOptions[templateType]) || [];
				if (!options.length) return;

				if (fieldObj.$el.find('.asset-portal-template-control[data-template-type="' + templateType + '"]').length) return;

				var $container = $('<div class="asset-portal-template-control" data-template-type="' + templateType + '" style="margin-top:6px;"></div>');
				var $select    = $('<select class="asset-portal-template-select"><option value="">Select template...</option></select>');
				var $button    = $('<button type="button" class="button asset-portal-template-import-button" style="margin-left:4px;">Import</button>');
				var $status    = $('<span class="asset-portal-template-status" style="margin-left:6px;"></span>');

				options.forEach(function(opt, index) {
					var label = opt.label || ('Template ' + (index + 1));
					$select.append('<option value="' + index + '">' + label + '</option>');
				});

				$container.append($select).append($button).append($status);

				if (typeof fieldObj.$inputWrap === 'function') {
					fieldObj.$inputWrap().append($container);
				} else {
					fieldObj.$el.find('> .acf-input').append($container);
				}

				$button.on('click', function(e) {
					e.preventDefault();

					var selectedIndex = $select.val();
					if (selectedIndex === '') {
						$status.text('Choose a template first.').css('color', 'red');
						return;
					}

					selectedIndex = parseInt(selectedIndex, 10);
					var template  = options[selectedIndex] || {};
					var value     = template.value || '';

					if (!value.length) {
						$status.text('Selected template is empty.').css('color', 'red');
						return;
					}

					setFieldValue(fieldObj, value);
					$status.text('Template imported.').css('color', 'green');
				});
			}

			function assetPortalCreateWpContentTemplateControl() {
				var options = (window.assetPortalTemplateOptions && window.assetPortalTemplateOptions.contents) || [];
				if (!options.length) return;

				if ($('#asset-portal-wp-content-template-control').length) return;

				var $editorWrap = $('#postdivrich');
				if (!$editorWrap.length) return;

				var $container = $('<div id="asset-portal-wp-content-template-control" style="margin:10px 0;"></div>');
				var $select    = $('<select class="asset-portal-template-select"><option value="">Select content template...</option></select>');
				var $button    = $('<button type="button" class="button" style="margin-left:6px;">Import</button>');
				var $status    = $('<span style="margin-left:8px;"></span>');

				options.forEach(function(opt, index) {
					var label = opt.label || ('Template ' + (index + 1));
					$select.append('<option value="' + index + '">' + label + '</option>');
				});

				$container.append($select).append($button).append($status);

				var $target = $('#wp-content-editor-container');
				if ($target.length) {
					$target.after($container);
				} else {
					$editorWrap.find('.inside').append($container);
				}

				$button.on('click', function(e) {
					e.preventDefault();

					var selectedIndex = $select.val();
					if (selectedIndex === '') {
						$status.text('Choose a template first.').css('color', 'red');
						return;
					}

					selectedIndex = parseInt(selectedIndex, 10);
					var template  = options[selectedIndex] || {};
					var value     = template.value || '';

					if (!value.length) {
						$status.text('Selected template is empty.').css('color', 'red');
						return;
					}

					if (typeof tinymce !== 'undefined' && tinymce.get('content')) {
						tinymce.get('content').setContent(value);
					}

					var $textarea = $('#content');
					if ($textarea.length) {
						$textarea.val(value).trigger('change');
					}

					$status.text('Template imported.').css('color', 'green');
				});
			}

			/**
			 * Create CC import control with Import button.
			 * IMPORTANT: Only add to the TOP LEVEL .acf-input, not inside repeater rows.
			 */
			function assetPortalCreateCCControl() {
				// Find the CC field wrapper (not inside rows)
				var $ccField = $('.acf-field[data-name="cc_emails"][data-type="repeater"]').first();
				if (!$ccField.length) {
					return;
				}

				// Get the direct .acf-input child (not nested ones inside rows)
				var $acfInput = $ccField.children('.acf-input');
				if (!$acfInput.length) {
					return;
				}

				// Prevent duplicate controls - check only at this level
				if ($acfInput.children('.asset-portal-cc-import-control').length) {
					return;
				}

				var ccEmails = (window.assetPortalTemplateOptions && window.assetPortalTemplateOptions.ccs) || [];

				// Build import control
				var $container = $('<div class="asset-portal-cc-import-control" style="margin-bottom:10px; padding:8px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;"></div>');
				var $button = $('<button type="button" class="button">Import CCs from Settings</button>');
				var $status = $('<span style="margin-left:10px;"></span>');
				var $count = $('<span style="margin-left:10px; color:#666;">(' + ccEmails.length + ' CC emails in settings)</span>');

				$container.append($button).append($status).append($count);
				
				// Prepend to the direct .acf-input child only
				$acfInput.prepend($container);

				// Import button click
				$button.on('click', function(e) {
					e.preventDefault();
					e.stopPropagation();
					importCCEmails($ccField, ccEmails, $status);
					return false;
				});

				// Auto-import on NEW posts
				var isNewPost = window.location.href.indexOf('post-new.php') !== -1;
				if (isNewPost && ccEmails.length) {
					setTimeout(function() {
						var $existingRows = $ccField.find('.acf-row:not(.acf-clone)');
						if ($existingRows.length === 0) {
							importCCEmails($ccField, ccEmails, null);
						}
					}, 2000);
				}
			}

			/**
			 * Import CC emails into repeater field.
			 * Clears ALL existing rows first, then adds from settings.
			 */
			function importCCEmails($ccField, ccEmails, $status) {
				if (!ccEmails || !ccEmails.length) {
					if ($status) $status.text('No CC emails in settings.').css('color', 'orange');
					return;
				}

				if ($status) $status.text('Clearing existing...').css('color', '#666');

				// Get all existing rows (not clone)
				var $existingRows = $ccField.find('.acf-row:not(.acf-clone)');
				var rowCount = $existingRows.length;

				// Function to remove rows one by one, handling confirmation
				function removeAllRows(callback) {
					var $rows = $ccField.find('.acf-row:not(.acf-clone)');
					if ($rows.length === 0) {
						callback();
						return;
					}

					// Remove first row - ACF removes without confirmation if we use the acf method
					var $firstRow = $rows.first();
					var $removeBtn = $firstRow.find('.acf-icon.-minus').first();
					
					if ($removeBtn.length) {
						// Simulate shift+click to skip confirmation, or just remove directly
						var field = acf.getField($ccField);
						if (field && typeof field.remove === 'function') {
							field.remove($firstRow);
						} else {
							// Fallback: trigger click and auto-confirm
							$removeBtn.trigger('click');
							// Auto-click Remove in confirmation if it appears
							setTimeout(function() {
								var $confirmRemove = $('.acf-fc-popup a[data-event="remove-row"], .acf-actions a:contains("Remove")').first();
								if ($confirmRemove.length) {
									$confirmRemove.trigger('click');
								}
							}, 50);
						}
					}

					// Wait and check again
					setTimeout(function() {
						removeAllRows(callback);
					}, 100);
				}

				// Clear all rows first, then add new ones
				removeAllRows(function() {
					if ($status) $status.text('Adding emails...').css('color', '#666');
					
					var addedCount = 0;
					
					function addNextEmail(index) {
						if (index >= ccEmails.length) {
							// All done
							if ($status) {
								$status.text(addedCount + ' CC emails imported.').css('color', 'green');
								setTimeout(function() { $status.text(''); }, 3000);
							}
							return;
						}
						
						var email = ccEmails[index];
						// Find add button in the .acf-actions area (bottom of repeater)
						var $addBtn = $ccField.find('.acf-actions .acf-button[data-event="add-row"]').first();
						
						if ($addBtn.length) {
							$addBtn.trigger('click');
							
							// Wait for row to appear
							setTimeout(function() {
								var $newRow = $ccField.find('.acf-row:not(.acf-clone)').last();
								var $input = $newRow.find('input[type="email"], input[type="text"]').first();
								
								if ($input.length) {
									$input.val(email).trigger('change').trigger('input');
									addedCount++;
								}
								
								// Add next email
								addNextEmail(index + 1);
							}, 150);
						} else {
							addNextEmail(index + 1);
						}
					}
					
					// Start adding emails
					addNextEmail(0);
				});
			}

			function assetPortalInjectTemplateControls() {
				// Auto-select controls (no Import button)
				assetPortalCreateAutoSelectControl(getFieldInGroup('message', 'name'),      'names');
				assetPortalCreateAutoSelectControl(getFieldInGroup('message', 'subject'),   'subjects');
				assetPortalCreateAutoSelectControl(getFieldInGroup('message', 'from'),      'froms');
				assetPortalCreateAutoSelectControl(getFieldInGroup('message', 'reply_to'),  'reply_tos');
				
				// Template controls with Import button (for WYSIWYG)
				assetPortalCreateTemplateControl(getFieldInGroup('message', 'body'),      'bodys');
				assetPortalCreateTemplateControl(getFieldInGroup('message', 'signature'), 'signatures');
				
				// Other controls
				assetPortalCreateWpContentTemplateControl();
				assetPortalCreateAuditTemplateControls();
				assetPortalCreateCCControl();
				assetPortalSetupDefaultTemplates();
			}

			/**
			 * Auto-import default templates on NEW posts if empty.
			 * NO button, NO refresh - just silent jQuery/Select2 manipulation.
			 */
			function assetPortalSetupDefaultTemplates() {
				var defaultTemplates = window.assetPortalDefaultTemplates || [];
				if (!defaultTemplates.length) return;

				// Only run on NEW posts
				var isNewPost = window.location.href.indexOf('post-new.php') !== -1;
				if (!isNewPost) return;

				// Find the ACF field wrapper
				var $field = $('.acf-field[data-name="templates"]');
				if (!$field.length) return;

				// Get the actual select element
				var $select = $field.find('select');
				if (!$select.length) return;

				// Mark as processed to prevent running twice
				if ($field.data('defaults-loaded')) return;
				$field.data('defaults-loaded', true);

				// Wait for Select2 to fully initialize, then check if empty
				setTimeout(function() {
					// Check if already has values
					var currentVal = $select.val();
					var hasValues = currentVal && (Array.isArray(currentVal) ? currentVal.length > 0 : true);
					var hasOptions = $select.find('option').length > 0;
					
					if (hasValues || hasOptions) return; // Already has data, don't override
					
					// Add each default template as a selected option
					$.each(defaultTemplates, function(i, tpl) {
						var option = new Option(tpl.title, tpl.id, true, true);
						$select.append(option);
					});
					
					// Trigger Select2 to update its display
					$select.trigger('change');
					
				}, 1500); // Wait 1.5 seconds for Select2 to fully init
			}

			/**
			 * Create audit template import controls with Replace/Prepend options.
			 * Always shows controls, even if no templates available yet.
			 * Updated for new nested structure: audit > knowledge_panel > report, audit > website > report
			 */
			function assetPortalCreateAuditTemplateControls() {
				// Knowledge Panel - now targets audit > knowledge_panel > report
				var kpOptions = (window.assetPortalTemplateOptions && window.assetPortalTemplateOptions.audit_knowledge_panels) || [];
				var kpReportField = getNestedField('audit', 'knowledge_panel', 'report');
				if (kpReportField) {
					createAuditImportControl(kpReportField, kpOptions, 'knowledge_panel');
				}

				// Website - now targets audit > website > report
				var wsOptions = (window.assetPortalTemplateOptions && window.assetPortalTemplateOptions.audit_websites) || [];
				var wsReportField = getNestedField('audit', 'website', 'report');
				if (wsReportField) {
					createAuditImportControl(wsReportField, wsOptions, 'website');
				}
			}

			/**
			 * Get a field nested two levels deep: groupName > subGroupName > fieldName
			 */
			function getNestedField(groupName, subGroupName, fieldName) {
				var $group = getGroupEl(groupName);
				if (!$group.length) return null;
				
				// Find the subgroup
				var $subGroup = $group.find('.acf-field[data-name="' + subGroupName + '"]').first();
				if (!$subGroup.length) return null;
				
				// Find the field within subgroup
				var $el = $subGroup.find('.acf-field[data-name="' + fieldName + '"]').first();
				if (!$el.length) return null;
				
				return acf.getField($el);
			}

			/**
			 * Create a single audit import control with Replace/Prepend toggle.
			 * Shows even if no templates available (with message).
			 */
			function createAuditImportControl(fieldObj, options, templateType) {
				if (!fieldObj || !fieldObj.$el) return;
				if (fieldObj.$el.find('.audit-template-control').length) return;

				var $container = $('<div class="audit-template-control" data-template-type="' + templateType + '" style="margin-top:8px; padding:10px; background:#f9f9f9; border:1px solid #ddd; border-radius:4px;"></div>');
				
				// Label
				var $label = $('<div style="margin-bottom:6px; font-weight:600; color:#333;">📥 Import Template</div>');
				$container.append($label);

				// Check if templates available
				if (!options.length) {
					var $noTemplates = $('<div style="color:#666; font-style:italic;">No templates available. Add templates in Asset Portal Settings → Email Templates → Audit.</div>');
					$container.append($noTemplates);
					
					// Append to field
					if (typeof fieldObj.$inputWrap === 'function') {
						fieldObj.$inputWrap().append($container);
					} else {
						fieldObj.$el.find('> .acf-input').append($container);
					}
					return;
				}
				
				// Select dropdown
				var $select = $('<select class="audit-template-select" style="min-width:200px;"><option value="">Select template...</option></select>');
				options.forEach(function(opt, index) {
					var label = opt.label || ('Template ' + (index + 1));
					$select.append('<option value="' + index + '">' + label + '</option>');
				});

				// Mode selector (Replace vs Prepend)
				var $modeWrap = $('<span style="margin-left:10px;"></span>');
				var $modeReplace = $('<label style="margin-right:10px; cursor:pointer;"><input type="radio" name="audit_import_mode_' + templateType + '" value="replace" checked style="margin-right:3px;">Replace</label>');
				var $modePrepend = $('<label style="cursor:pointer;"><input type="radio" name="audit_import_mode_' + templateType + '" value="prepend" style="margin-right:3px;">Prepend to Top</label>');
				$modeWrap.append($modeReplace).append($modePrepend);

				// Import button
				var $button = $('<button type="button" class="button button-primary" style="margin-left:10px;">Import</button>');
				
				// Status message
				var $status = $('<span class="audit-template-status" style="margin-left:8px;"></span>');

				$container.append($select).append($modeWrap).append($button).append($status);

				// Append to field
				if (typeof fieldObj.$inputWrap === 'function') {
					fieldObj.$inputWrap().append($container);
				} else {
					fieldObj.$el.find('> .acf-input').append($container);
				}

				// Click handler
				$button.on('click', function(e) {
					e.preventDefault();

					var selectedIndex = $select.val();
					if (selectedIndex === '') {
						$status.text('Choose a template first.').css('color', 'red');
						return;
					}

					selectedIndex = parseInt(selectedIndex, 10);
					var template = options[selectedIndex] || {};
					var newValue = template.value || '';

					if (!newValue.length) {
						$status.text('Selected template is empty.').css('color', 'red');
						return;
					}

					// Get selected mode
					var mode = $container.find('input[name="audit_import_mode_' + templateType + '"]:checked').val() || 'replace';

					// Get current value
					var currentValue = getWysiwygHtml(fieldObj) || '';

					var finalValue = '';
					if (mode === 'prepend' && currentValue.trim().length > 0) {
						// Prepend: new content at top, then existing content
						finalValue = newValue + '\n\n<hr>\n\n' + currentValue;
					} else {
						// Replace: just the new content
						finalValue = newValue;
					}

					setFieldValue(fieldObj, finalValue);
					
					var modeLabel = (mode === 'prepend') ? 'prepended' : 'imported';
					$status.text('Template ' + modeLabel + ' successfully!').css('color', 'green');
				});
			}

			function assetPortalMakeHistoryAndOrderInfoReadonly() {
				var historyField   = getFieldByNameRoot('history');
				var orderInfoField = getFieldByNameRoot('order_info');

				if (historyField && typeof historyField.$input === 'function') {
					var $hInput = historyField.$input();
					if ($hInput && $hInput.length) $hInput.attr('readonly', 'readonly');
				} else {
					$('.acf-field[data-name="history"] textarea').attr('readonly', 'readonly');
				}

				if (orderInfoField && typeof orderInfoField.$input === 'function') {
					var $oInput = orderInfoField.$input();
					if ($oInput && $oInput.length) $oInput.attr('readonly', 'readonly');
				} else {
					$('.acf-field[data-name="order_info"] textarea').attr('readonly', 'readonly');
				}
			}

			assetPortalInjectSendEmailButton();
			assetPortalInjectTemplateControls();
			assetPortalMakeHistoryAndOrderInfoReadonly();
			assetPortalGenerateDynamicUrls();

			acf.addAction('refresh', function () {
				assetPortalInjectSendEmailButton();
				assetPortalInjectTemplateControls();
				assetPortalMakeHistoryAndOrderInfoReadonly();
				assetPortalGenerateDynamicUrls();
			});

			/**
			 * Generate dynamic URLs for search_results
			 */
			function assetPortalGenerateDynamicUrls() {
				// Get post title
				var postTitle = $('#title').val() || '';
				
				// Generate search_results URL from post title (preserve title case)
				var searchResultsField = getNestedField('audit', 'knowledge_panel', 'search_results');
				if (searchResultsField && searchResultsField.$input) {
					var $input = searchResultsField.$input();
					// Only populate if empty
					if ($input && $input.length && !$input.val().trim()) {
						if (postTitle.trim()) {
							// Preserve case, just replace spaces with +
							var searchParam = postTitle.trim().replace(/\s+/g, '+');
							var searchUrl = 'https://seoforpublicfigures.com/google-knowledge-graph-explorer/?s=' + searchParam;
							$input.val(searchUrl);
						}
					}
					// Update instructions with dynamic clickable link
					updateSearchResultsInstructions(searchResultsField, postTitle);
				}

				// Update KGMID instructions with dynamic Google search URL
				var kgmidField = getNestedField('audit', 'knowledge_panel', 'kgmid');
				if (kgmidField) {
					updateKgmidInstructions(kgmidField);
					// Listen for KGMID changes
					if (kgmidField.$input) {
						kgmidField.$input().off('change.kgmid input.kgmid').on('change.kgmid input.kgmid', function() {
							updateKgmidInstructions(kgmidField);
						});
					}
				}

				// Update URL Personal instructions with clickable link
				var urlPersonalField = getNestedField('audit', 'website', 'url_personal');
				if (urlPersonalField) {
					updateUrlFieldInstructions(urlPersonalField, 'personal');
					// Listen for URL changes
					if (urlPersonalField.$input) {
						urlPersonalField.$input().off('change.urlpersonal input.urlpersonal').on('change.urlpersonal input.urlpersonal', function() {
							updateUrlFieldInstructions(urlPersonalField, 'personal');
						});
					}
				}

				// Update URL Company instructions with clickable link
				var urlCompanyField = getNestedField('audit', 'website', 'url_company');
				if (urlCompanyField) {
					updateUrlFieldInstructions(urlCompanyField, 'company');
					// Listen for URL changes
					if (urlCompanyField.$input) {
						urlCompanyField.$input().off('change.urlcompany input.urlcompany').on('change.urlcompany input.urlcompany', function() {
							updateUrlFieldInstructions(urlCompanyField, 'company');
						});
					}
				}

				// Update Image field instructions - Before Image
				var kpBeforeImageField = getNestedField('audit', 'knowledge_panel', 'before_image');
				if (kpBeforeImageField) {
					updateImageFieldInstructions(kpBeforeImageField, 'audit_knowledge_panel_before_image', null);
				}
				// Update Image field instructions - KGMID Image
				var kpKgmidImageField = getNestedField('audit', 'knowledge_panel', 'kgmid_image');
				if (kpKgmidImageField) {
					updateImageFieldInstructions(kpKgmidImageField, 'audit_knowledge_panel_kgmid_image', null);
				}
				// Update Image field instructions - Personal Website Image
				var wsImagePersonalField = getNestedField('audit', 'website', 'image_personal');
				if (wsImagePersonalField) {
					updateImageFieldInstructions(wsImagePersonalField, 'audit_personal_website_image', urlPersonalField);
					// Listen for URL changes to update instructions
					if (urlPersonalField && urlPersonalField.$input) {
						urlPersonalField.$input().off('change.imgpersonal input.imgpersonal').on('change.imgpersonal input.imgpersonal', function() {
							updateImageFieldInstructions(wsImagePersonalField, 'audit_personal_website_image', urlPersonalField);
						});
					}
				}
				// Update Image field instructions - Company Website Image
				var wsImageCompanyField = getNestedField('audit', 'website', 'image_company');
				if (wsImageCompanyField) {
					updateImageFieldInstructions(wsImageCompanyField, 'audit_company_website_image', urlCompanyField);
					// Listen for URL changes to update instructions
					if (urlCompanyField && urlCompanyField.$input) {
						urlCompanyField.$input().off('change.imgcompany input.imgcompany').on('change.imgcompany input.imgcompany', function() {
							updateImageFieldInstructions(wsImageCompanyField, 'audit_company_website_image', urlCompanyField);
						});
					}
				}
				
				// Update Schema URL text fields with dynamic URLs
				var schemaUrlPersonalField = getNestedField('audit', 'website', 'report_schema_url_personal');
				var schemaUrlCompanyField = getNestedField('audit', 'website', 'report_schema_url_company');
				
				// Initial update
				if (schemaUrlPersonalField && urlPersonalField) {
					updateSchemaUrlField(schemaUrlPersonalField, urlPersonalField, 'personal');
				}
				if (schemaUrlCompanyField && urlCompanyField) {
					updateSchemaUrlField(schemaUrlCompanyField, urlCompanyField, 'company');
				}
				
				// Listen for URL changes to update schema URLs
				if (urlPersonalField && urlPersonalField.$input && schemaUrlPersonalField) {
					urlPersonalField.$input().off('change.schemapersonal input.schemapersonal').on('change.schemapersonal input.schemapersonal', function() {
						updateSchemaUrlField(schemaUrlPersonalField, urlPersonalField, 'personal');
					});
				}
				if (urlCompanyField && urlCompanyField.$input && schemaUrlCompanyField) {
					urlCompanyField.$input().off('change.schemacompany input.schemacompany').on('change.schemacompany input.schemacompany', function() {
						updateSchemaUrlField(schemaUrlCompanyField, urlCompanyField, 'company');
					});
				}
				
				// Update Schema Image instructions
				var schemaImagePersonalField = getNestedField('audit', 'website', 'report_schema_image_personal');
				if (schemaImagePersonalField) {
					updateImageFieldInstructions(schemaImagePersonalField, 'audit_website_report_schema_image_personal', null);
				}
				var schemaImageCompanyField = getNestedField('audit', 'website', 'report_schema_image_company');
				if (schemaImageCompanyField) {
					updateImageFieldInstructions(schemaImageCompanyField, 'audit_website_report_schema_image_company', null);
				}
			}

			/**
			 * Update KGMID field instructions with dynamic Google search URL
			 */
			function updateKgmidInstructions(field) {
				if (!field || !field.$el) return;
				var $instructions = field.$el.find('.acf-label .description');
				if (!$instructions.length) {
					$instructions = field.$el.find('.acf-input .description');
				}
				
				var baseHtml = '<code>[audit_knowledge_panel_kgmid]</code><br><code>[audit_knowledge_panel_kgmid format=&quot;url&quot;]</code> = Full Google URL';
				
				if (field.$input) {
					var kgmid = field.$input().val() || '';
					if (kgmid.trim()) {
						// Build clean URL for display (no encoding)
						var cleanUrl = 'https://www.google.com/search?kgmid=' + kgmid.trim();
						// Encode only for href attribute
						var hrefUrl = 'https://www.google.com/search?kgmid=' + encodeURIComponent(kgmid.trim());
						baseHtml += '<br><a href="' + hrefUrl + '" target="_blank" style="color:#0073aa;text-decoration:none;">🔗 ' + cleanUrl + '</a>';
					}
				}
				
				$instructions.html(baseHtml);
			}

			/**
			 * Update URL field instructions with clickable link
			 */
			function updateUrlFieldInstructions(field, type) {
				if (!field || !field.$el) return;
				var $instructions = field.$el.find('.acf-label .description');
				if (!$instructions.length) {
					$instructions = field.$el.find('.acf-input .description');
				}
				
				var shortcode = (type === 'personal') ? 'audit_website_url_personal' : 'audit_website_url_company';
				var baseHtml = '<code>[' + shortcode + ']</code><br><code>[' + shortcode + ' format=&quot;pretty&quot;]</code> = strips https://www.';
				
				if (field.$input) {
					var url = field.$input().val() || '';
					if (url.trim()) {
						var normalizedUrl = normalizeUrl(url);
						baseHtml += '<br><a href="' + normalizedUrl + '" target="_blank" style="color:#0073aa;text-decoration:none;">🔗 ' + normalizedUrl + '</a>';
					}
				}
				
				$instructions.html(baseHtml);
			}

			/**
			 * Update Image field instructions with size and link parameter info
			 */
			function updateImageFieldInstructions(field, shortcode, urlField) {
				if (!field || !field.$el) return;
				var $instructions = field.$el.find('.acf-label .description');
				if (!$instructions.length) {
					$instructions = field.$el.find('.acf-input .description');
				}
				
				var baseHtml = '<code>[' + shortcode + ']</code> (default: medium_large, lightbox)<br>';
				baseHtml += '<code>[' + shortcode + ' size=&quot;thumbnail|medium|medium_large|large|full&quot;]</code><br>';
				baseHtml += '<code>[' + shortcode + ' link=&quot;media_file|none&quot;]</code>';
				
				// Add PageSpeed links for website image fields - use actual URL from field
				if (shortcode === 'audit_personal_website_image' || shortcode === 'audit_company_website_image') {
					var websiteUrl = '';
					if (urlField && urlField.$input) {
						websiteUrl = urlField.$input().val() || '';
					}
					
					baseHtml += '<br><br>';
					baseHtml += '<strong>PageSpeed Test:</strong> <a href="https://pagespeed.web.dev/" target="_blank" style="color:#0073aa;">https://pagespeed.web.dev/</a><br>';
					
					if (websiteUrl.trim()) {
						// Build PageSpeed URL from actual website URL
						var normalizedUrl = normalizeUrl(websiteUrl);
						var pagespeedUrl = 'https://pagespeed.web.dev/analysis/' + normalizedUrl.replace(/^https?:\/\//, '').replace(/\//g, '-').replace(/\./g, '-');
						baseHtml += '<strong>Analyze:</strong> <a href="' + pagespeedUrl + '" target="_blank" style="color:#0073aa;">' + pagespeedUrl + '</a><br>';
						baseHtml += '<strong>Website URL:</strong> <a href="' + normalizedUrl + '" target="_blank" style="color:#0073aa;">' + normalizedUrl + '</a><br>';
					} else {
						baseHtml += '<em style="color:#999;">Enter URL above to generate PageSpeed analysis link</em><br>';
					}
					baseHtml += '<em>Screenshot PageSpeed results, upload here.</em>';
				}
				
				$instructions.html(baseHtml);
			}

			/**
			 * Update Schema URL text field - sets field value AND instructions
			 */
			function updateSchemaUrlField(field, urlField, type) {
				if (!field || !field.$el) return;
				
				var shortcode = (type === 'personal') ? 'audit_website_report_schema_url_personal' : 'audit_website_report_schema_url_company';
				var urlType = (type === 'personal') ? 'Personal' : 'Company';
				
				// Get the URL value from the related URL field
				var url = '';
				if (urlField && urlField.$input) {
					url = urlField.$input().val() || '';
				}
				
				// Build the schema validator URL
				var schemaUrl = '';
				if (url.trim()) {
					var normalizedUrl = normalizeUrl(url);
					schemaUrl = 'https://validator.schema.org/#url=' + encodeURIComponent(normalizedUrl);
				}
				
				// Set the text field value
				if (field.$input) {
					field.$input().val(schemaUrl);
				}
				
				// Update instructions with shortcode and clickable link
				var $instructions = field.$el.find('.acf-label .description');
				if (!$instructions.length) {
					$instructions = field.$el.find('.acf-input .description');
				}
				
				var baseHtml = '<code>[' + shortcode + ']</code>';
				if (schemaUrl) {
					baseHtml += '<br><a href="' + schemaUrl + '" target="_blank" style="color:#0073aa;text-decoration:none;">🔗 ' + schemaUrl + '</a>';
				} else {
					baseHtml += '<br><em style="color:#999;">Enter a ' + urlType + ' URL above to generate validator link</em>';
				}
				
				$instructions.html(baseHtml);
			}

			/**
			 * Update search_results field instructions with dynamic clickable link
			 */
			function updateSearchResultsInstructions(field, postTitle) {
				if (!field || !field.$el) return;
				var $instructions = field.$el.find('.acf-label .description');
				if (!$instructions.length) {
					$instructions = field.$el.find('.acf-input .description');
				}
				if ($instructions.length && postTitle.trim()) {
					// Preserve case for display
					var searchParam = postTitle.trim().replace(/\s+/g, '+');
					var searchUrl = 'https://seoforpublicfigures.com/google-knowledge-graph-explorer/?s=' + searchParam;
					$instructions.html('<code>[audit_knowledge_panel_search_results]</code><br><a href="' + searchUrl + '" target="_blank">🔗 View Knowledge Graph for "' + postTitle + '"</a>');
				}
			}

			/**
			 * Normalize URL - ensure https:// prefix and clean format
			 */
			function normalizeUrl(url) {
				if (!url) return '';
				url = url.trim();
				// Remove trailing slash
				url = url.replace(/\/+$/, '');
				// If already has protocol, return as is
				if (url.match(/^https?:\/\//)) {
					return url;
				}
				// Remove www. prefix if no protocol
				url = url.replace(/^www\./, '');
				// Add https://
				return 'https://' + url;
			}
		});
	</script>
	<?php
}

/**
 * Ensure password exists on save.
 * NOW also logs to History when it generates a new password.
 * 
 * FIXED: Also hooks into post meta changes to catch imports that
 * create posts then set meta separately.
 */
\add_action( 'save_post_' . HWS_PORTAL_CPT, __NAMESPACE__ . '\\asset_portal_ensure_password_exists', 20, 3 );

/**
 * Additional hooks for imports - fire password check when relevant meta is added.
 * This catches import plugins that set meta after post creation.
 */
\add_action( 'added_post_meta', __NAMESPACE__ . '\\asset_portal_maybe_generate_password_on_meta', 10, 4 );
\add_action( 'updated_post_meta', __NAMESPACE__ . '\\asset_portal_maybe_generate_password_on_meta', 10, 4 );

/**
 * Check if password needs generating when meta is set (for imports).
 */
function asset_portal_maybe_generate_password_on_meta( $meta_id, $post_id, $meta_key, $meta_value ) {
	// Only trigger on relevant ACF fields being set (recipient, message indicate an import)
	$trigger_keys = [ 'recipient', 'message', '_recipient', '_message', 'recipient_name', 'recipient_email' ];
	if ( ! in_array( $meta_key, $trigger_keys, true ) ) {
		return;
	}
	
	// Check if this is our CPT
	if ( \get_post_type( $post_id ) !== HWS_PORTAL_CPT ) {
		return;
	}
	
	// Check if password already exists
	$current = '';
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$current = \Hexa\PluginCore\Fields\Field::get( 'password', $post_id );
	}
	if ( ! is_string( $current ) ) {
		$current = \get_post_meta( $post_id, 'password', true );
	}
	$current = is_string( $current ) ? trim( $current ) : '';
	
	// If password exists, don't generate
	if ( $current !== '' ) {
		return;
	}
	
	// Check if we already generated for this post (prevent loops)
	if ( \get_post_meta( $post_id, '_hws_password_generated', true ) ) {
		return;
	}
	
	// Generate password
	$new_pw = \wp_generate_password( 12, false, false );
	
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		\Hexa\PluginCore\Fields\Field::update( 'password', $new_pw, $post_id );
	} else {
		\update_post_meta( $post_id, 'password', $new_pw );
	}
	
	// Mark as generated
	\update_post_meta( $post_id, '_hws_password_generated', 1 );
	
	$log_block  = "----------------------------------------\n";
	$log_block .= sprintf( "[%s] PASSWORD GENERATED (via import)\n", \current_time( 'mysql' ) );
	$log_block .= sprintf( "Post ID: %d\n", (int) $post_id );
	$log_block .= "Password: [generated]\n";
	$log_block .= "----------------------------------------";
	
	asset_portal_append_history_block( $post_id, $log_block );
}

/**
 * Standard password generation on post save.
 */
function asset_portal_ensure_password_exists( $post_id, $post, $update ) {
	if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
		return;
	}

	$current = '';
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$current = \Hexa\PluginCore\Fields\Field::get( 'password', $post_id );
	}
	if ( ! is_string( $current ) ) {
		$current = \get_post_meta( $post_id, 'password', true );
	}
	$current = is_string( $current ) ? trim( $current ) : '';

	if ( $current !== '' ) {
		return;
	}

	$new_pw = \wp_generate_password( 12, false, false );

	// Write password (prefer ACF if available).
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		\Hexa\PluginCore\Fields\Field::update( 'password', $new_pw, $post_id );
	} else {
		\update_post_meta( $post_id, 'password', $new_pw );
	}
	
	// Mark as generated
	\update_post_meta( $post_id, '_hws_password_generated', 1 );

	$log_block  = "----------------------------------------\n";
	$log_block .= sprintf( "[%s] PASSWORD GENERATED\n", \current_time( 'mysql' ) );
	$log_block .= sprintf( "Post ID: %d\n", (int) $post_id );
	$log_block .= "Password: [generated]\n";
	$log_block .= "----------------------------------------";

	asset_portal_append_history_block( $post_id, $log_block );
}

/**
 * Auto-generate Knowledge Panel search_results URL on save.
 * URL format: https://seoforpublicfigures.com/google-knowledge-graph-explorer/?s={title}
 */
\add_action( 'save_post_' . HWS_PORTAL_CPT, __NAMESPACE__ . '\\asset_portal_generate_search_results_url', 20, 3 );

function asset_portal_generate_search_results_url( $post_id, $post, $update ) {
	if ( \wp_is_post_autosave( $post_id ) || \wp_is_post_revision( $post_id ) ) {
		return;
	}

	// Check if search_results is already set
	$current = '';
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$audit = \Hexa\PluginCore\Fields\Field::get( 'audit', $post_id );
		if ( is_array( $audit ) && isset( $audit['knowledge_panel'] ) && is_array( $audit['knowledge_panel'] ) ) {
			$current = $audit['knowledge_panel']['search_results'] ?? '';
		}
	}
	
	// If already has a value, don't overwrite
	if ( ! empty( trim( $current ) ) ) {
		return;
	}

	// Get post title
	$title = \get_the_title( $post_id );
	if ( empty( $title ) ) {
		return;
	}

	// Build URL: preserve title case, replace spaces with +
	$search_param = trim( $title );
	$search_param = preg_replace( '/\s+/', '+', $search_param );
	$search_url = 'https://seoforpublicfigures.com/google-knowledge-graph-explorer/?s=' . $search_param;

	// Save to ACF field: audit > knowledge_panel > search_results
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		// Get current audit data
		$audit = \Hexa\PluginCore\Fields\Field::get( 'audit', $post_id );
		if ( ! is_array( $audit ) ) {
			$audit = array();
		}
		if ( ! isset( $audit['knowledge_panel'] ) || ! is_array( $audit['knowledge_panel'] ) ) {
			$audit['knowledge_panel'] = array();
		}
		$audit['knowledge_panel']['search_results'] = $search_url;
		\Hexa\PluginCore\Fields\Field::update( 'audit', $audit, $post_id );
	}
}

/**
 * AJAX handler used by the "Send Email" button.
 * Sends email(s) and logs to History.
 * Only register in admin context (AJAX runs in admin context)
 */
if ( is_admin() ) {
	\add_action( 'wp_ajax_asset_portal_send_email', __NAMESPACE__ . '\\asset_portal_handle_send_email_ajax' );
	\add_action( 'wp_ajax_asset_portal_preview_email', __NAMESPACE__ . '\\asset_portal_handle_preview_email_ajax' );
}

/**
 * Process parameterized shortcodes in email content.
 * Handles URL format="pretty", KGMID format="url", and image size parameters.
 * 
 * @param string $content The content to process
 * @param int    $post_id The post ID
 * @return string Processed content
 */
function asset_portal_process_parameterized_shortcodes( string $content, int $post_id ): string {
	// ==========================================================================
	// NORMALIZE QUOTES - Handle curly quotes, HTML entities, and Unicode variants
	// WYSIWYG editors love to convert straight quotes to fancy ones
	// ==========================================================================
	// HTML entities first
	$content = str_replace(
		array('&quot;', '&#34;', '&#x22;', '&ldquo;', '&rdquo;', '&lsquo;', '&rsquo;'),
		array('"', '"', '"', '"', '"', "'", "'"),
		$content
	);
	// Unicode curly quotes and prime marks (using hex codes to avoid encoding issues)
	$content = str_replace(
		array("\xe2\x80\x9c", "\xe2\x80\x9d", "\xe2\x80\x98", "\xe2\x80\x99", "\xe2\x80\xb3", "\xe2\x80\xb2", "\xc2\xab", "\xc2\xbb", "\xe2\x80\xb9", "\xe2\x80\xba"),
		array('"', '"', "'", "'", '"', "'", '"', '"', "'", "'"),
		$content
	);
	// Decode HTML-encoded brackets (WYSIWYG may encode them)
	$content = str_replace(
		array('&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;'),
		array('[', ']', '[', ']', '[', ']'),
		$content
	);
	// Full HTML entity decode
	$content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
	
	// Get audit field data
	$audit = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get( 'audit', $post_id ) : array();
	
	// Get example images/URLs from theme options (email_templates.audit)
	$audit_kp_example_image = '';
	$audit_ws_schema_example_image = '';
	$audit_ws_schema_example_url = '';
	
	// Get via ACF first
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$email_templates = \Hexa\PluginCore\Fields\Field::get( 'email_templates', 'option' );
		if ( is_array( $email_templates ) && isset( $email_templates['audit'] ) && is_array( $email_templates['audit'] ) ) {
			$audit_settings = $email_templates['audit'];
			$audit_kp_example_image = $audit_settings['knowledge_panel_example_image'] ?? '';
			$audit_ws_schema_example_image = $audit_settings['website_schema_example_image'] ?? '';
			$audit_ws_schema_example_url = $audit_settings['website_schema_example_url'] ?? '';
		}
	}
	
	// Fallback to direct option retrieval
	if ( empty( $audit_kp_example_image ) ) {
		$audit_kp_example_image = \get_option( 'options_email_templates_audit_knowledge_panel_example_image' ) ?: '';
	}
	if ( empty( $audit_ws_schema_example_image ) ) {
		$audit_ws_schema_example_image = \get_option( 'options_email_templates_audit_website_schema_example_image' ) ?: '';
	}
	if ( empty( $audit_ws_schema_example_url ) ) {
		$audit_ws_schema_example_url = \get_option( 'options_email_templates_audit_website_schema_example_url' ) ?: '';
	}
	
	$audit_ws_url_personal = '';
	$audit_ws_url_company = '';
	$audit_kp_before_image = '';
	$audit_kp_kgmid_image = '';
	$audit_kp_kgmid = '';
	$audit_kp_search_results = '';
	$audit_ws_image_personal = '';
	$audit_ws_image_company = '';
	$audit_ws_schema_image_personal = '';
	$audit_ws_schema_image_company = '';
	
	if ( is_array( $audit ) ) {
		if ( isset( $audit['website'] ) && is_array( $audit['website'] ) ) {
			$audit_ws_url_personal = $audit['website']['url_personal'] ?? '';
			$audit_ws_url_company = $audit['website']['url_company'] ?? '';
			$audit_ws_image_personal = $audit['website']['image_personal'] ?? '';
			$audit_ws_image_company = $audit['website']['image_company'] ?? '';
			$audit_ws_schema_image_personal = $audit['website']['report_schema_image_personal'] ?? '';
			$audit_ws_schema_image_company = $audit['website']['report_schema_image_company'] ?? '';
		}
		if ( isset( $audit['knowledge_panel'] ) && is_array( $audit['knowledge_panel'] ) ) {
			$audit_kp_before_image = $audit['knowledge_panel']['before_image'] ?? '';
			$audit_kp_kgmid_image = $audit['knowledge_panel']['kgmid_image'] ?? '';
			$audit_kp_kgmid = $audit['knowledge_panel']['kgmid'] ?? '';
			$audit_kp_search_results = $audit['knowledge_panel']['search_results'] ?? '';
		}
	}
	
	// Build dynamic schema validator URLs
	$audit_ws_schema_url_personal = '';
	$audit_ws_schema_url_company = '';
	if ( ! empty( $audit_ws_url_personal ) ) {
		$audit_ws_schema_url_personal = 'https://validator.schema.org/#url=' . rawurlencode( $audit_ws_url_personal );
	}
	if ( ! empty( $audit_ws_url_company ) ) {
		$audit_ws_schema_url_company = 'https://validator.schema.org/#url=' . rawurlencode( $audit_ws_url_company );
	}
	
	// ==========================================================================
	// URL SHORTCODES WITH format="pretty" PARAMETER
	// ==========================================================================
	$url_shortcodes = [
		'audit_website_url_personal' => $audit_ws_url_personal,
		'audit_website_url_company'  => $audit_ws_url_company,
	];
	
	foreach ( $url_shortcodes as $shortcode => $value ) {
		// Match with format parameter: [shortcode format="pretty"]
		$pattern = '/\[' . preg_quote( $shortcode, '/' ) . '\s+format\s*=\s*["\']?pretty["\']?\s*\]/i';
		if ( preg_match( $pattern, $content ) ) {
			$pretty = trim( $value );
			$pretty = preg_replace( '/^https?:\/\//', '', $pretty );
			$pretty = preg_replace( '/^www\./', '', $pretty );
			$pretty = rtrim( $pretty, '/' );
			$content = preg_replace( $pattern, $pretty, $content );
		}
		
		// Simple replacement without parameter
		$content = str_replace( '[' . $shortcode . ']', $value, $content );
	}
	
	// ==========================================================================
	// KGMID SHORTCODE WITH format="url" PARAMETER
	// ==========================================================================
	// Match with format="url" parameter - very flexible
	$kgmid_pattern = '/\[audit_knowledge_panel_kgmid\s+format\s*=\s*["\']?url["\']?\s*\]/i';
	$content = preg_replace_callback( $kgmid_pattern, function($match) use ($audit_kp_kgmid) {
		if ( ! empty( $audit_kp_kgmid ) ) {
			return 'https://www.google.com/search?kgmid=' . urlencode( $audit_kp_kgmid );
		}
		return '';
	}, $content );
	
	// Also try without quotes
	$kgmid_pattern_noquote = '/\[audit_knowledge_panel_kgmid\s+format\s*=\s*url\s*\]/i';
	$content = preg_replace_callback( $kgmid_pattern_noquote, function($match) use ($audit_kp_kgmid) {
		if ( ! empty( $audit_kp_kgmid ) ) {
			return 'https://www.google.com/search?kgmid=' . urlencode( $audit_kp_kgmid );
		}
		return '';
	}, $content );
	
	// Simple KGMID replacement without parameter
	$content = str_replace( '[audit_knowledge_panel_kgmid]', $audit_kp_kgmid, $content );
	
	// Simple URL replacements for nested content
	$content = str_replace( '[audit_website_url_personal]', $audit_ws_url_personal, $content );
	$content = str_replace( '[audit_website_url_company]', $audit_ws_url_company, $content );
	
	// Schema URL replacements (dynamically built)
	$content = str_replace( '[audit_website_report_schema_url_personal]', $audit_ws_schema_url_personal, $content );
	$content = str_replace( '[audit_website_report_schema_url_company]', $audit_ws_schema_url_company, $content );
	$content = str_replace( '[audit_website_schema_example_url]', $audit_ws_schema_example_url, $content );
	$content = str_replace( '[audit_website_schema_example_link]', $audit_ws_schema_example_url, $content ); // Alias
	
	// Search results for nested content
	$content = str_replace( '[audit_knowledge_panel_search_results]', $audit_kp_search_results, $content );
	
	// ==========================================================================
	// IMAGE SHORTCODES WITH size AND link PARAMETERS
	// Email context: link="media_file" wraps in <a>, link="none" is just img
	// Default: size="medium_large", link="media_file"
	// ==========================================================================
	$image_shortcodes = [
		'audit_knowledge_panel_before_image'         => $audit_kp_before_image,
		'audit_knowledge_panel_kgmid_image'          => $audit_kp_kgmid_image,
		'audit_knowledge_panel_example_image'        => $audit_kp_example_image,
		'audit_personal_website_image'               => $audit_ws_image_personal,
		'audit_company_website_image'                => $audit_ws_image_company,
		'audit_website_report_schema_image_personal' => $audit_ws_schema_image_personal,
		'audit_website_report_schema_image_company'  => $audit_ws_schema_image_company,
		'audit_website_schema_example_image'         => $audit_ws_schema_example_image,
	];
	
	$valid_sizes = ['thumbnail', 'medium', 'medium_large', 'large', 'full'];
	$valid_links = ['media_file', 'none'];
	
	foreach ( $image_shortcodes as $shortcode => $img_value ) {
		// Determine alt text based on shortcode
		$alt = 'Image';
		if ( $shortcode === 'audit_knowledge_panel_before_image' ) {
			$alt = 'Knowledge Panel Before';
		} elseif ( $shortcode === 'audit_knowledge_panel_kgmid_image' ) {
			$alt = 'Knowledge Panel KGMID';
		} elseif ( $shortcode === 'audit_knowledge_panel_example_image' ) {
			$alt = 'Knowledge Panel Example';
		} elseif ( $shortcode === 'audit_personal_website_image' ) {
			$alt = 'Personal Website Screenshot';
		} elseif ( $shortcode === 'audit_company_website_image' ) {
			$alt = 'Company Website Screenshot';
		} elseif ( $shortcode === 'audit_website_report_schema_image_personal' ) {
			$alt = 'Schema Validation Personal';
		} elseif ( $shortcode === 'audit_website_report_schema_image_company' ) {
			$alt = 'Schema Validation Company';
		} elseif ( $shortcode === 'audit_website_schema_example_image' ) {
			$alt = 'Schema Example';
		}
		
		// Pattern matches [shortcode] and [shortcode with any parameters]
		// Very permissive - matches anything inside the brackets
		$pattern = '/\[' . preg_quote( $shortcode, '/' ) . '([^\]]*)\]/i';
		
		if ( preg_match_all( $pattern, $content, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$full_match = $match[0];
				$params_str = $match[1] ?? '';
				
				// Parse parameters (very flexible - handles any quote style)
				$size = 'medium_large'; // Default size
				$link = 'media_file';   // Default link
				
				// Extract size parameter - match size= followed by value
				if ( preg_match( '/size\s*=\s*[^a-z]*(thumbnail|medium_large|medium|large|full)/i', $params_str, $size_match ) ) {
					$size = strtolower( $size_match[1] );
				}
				
				// Extract link parameter
				if ( preg_match( '/link\s*=\s*[^a-z]*(media_file|none)/i', $params_str, $link_match ) ) {
					$link = strtolower( $link_match[1] );
				}
				
				// Get image URLs
				$display_url = asset_portal_get_image_by_size( $img_value, $size );
				$full_url = asset_portal_get_image_by_size( $img_value, 'full' );
				
				$replacement = '';
				if ( ! empty( $display_url ) ) {
					$img_tag = '<img src="' . \esc_url( $display_url ) . '" alt="' . $alt . '" class="shortcode_image shortcode_' . \esc_attr( $shortcode ) . '" style="max-width:100%;height:auto;" />';
					
					if ( $link === 'media_file' && ! empty( $full_url ) ) {
						$replacement = '<a href="' . \esc_url( $full_url ) . '" target="_blank">' . $img_tag . '</a>';
					} else {
						$replacement = $img_tag;
					}
				}
				
				$content = str_replace( $full_match, $replacement, $content );
			}
		}
	}
	
	return $content;
}

/**
 * Get image URL by size from attachment ID or URL.
 */
function asset_portal_get_image_by_size( $image, string $size = 'medium_large' ): string {
	if ( empty( $image ) ) {
		return '';
	}
	
	// If it's an attachment ID
	if ( is_numeric( $image ) ) {
		$image_src = \wp_get_attachment_image_src( (int) $image, $size );
		return $image_src ? $image_src[0] : '';
	}
	
	// If it's a URL
	if ( is_string( $image ) && filter_var( $image, FILTER_VALIDATE_URL ) ) {
		$attachment_id = \attachment_url_to_postid( $image );
		if ( $attachment_id ) {
			$image_src = \wp_get_attachment_image_src( $attachment_id, $size );
			return $image_src ? $image_src[0] : $image;
		}
		return $image;
	}
	
	return '';
}

/**
 * AJAX handler for email preview.
 */
function asset_portal_handle_preview_email_ajax() {
	if ( ! \wp_verify_nonce( $_POST['nonce'] ?? '', 'asset_portal_preview_email' ) ) {
		\wp_send_json_error( array( 'message' => 'Invalid nonce.' ) );
	}

	if ( ! \current_user_can( 'edit_posts' ) ) {
		\wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id || \get_post_type( $post_id ) !== HWS_PORTAL_CPT ) {
		\wp_send_json_error( array( 'message' => 'Invalid asset portal item.' ) );
	}

	$raw_emails   = isset( $_POST['emails'] ) ? \wp_unslash( $_POST['emails'] ) : '';
	$subject      = isset( $_POST['subject'] ) ? \wp_unslash( $_POST['subject'] ) : '';
	$body         = isset( $_POST['body'] ) ? \wp_unslash( $_POST['body'] ) : '';
	$signature    = isset( $_POST['signature'] ) ? \wp_unslash( $_POST['signature'] ) : '';
	$name         = isset( $_POST['name'] ) ? \wp_unslash( $_POST['name'] ) : '';
	$first_name   = isset( $_POST['first_name'] ) ? \wp_unslash( $_POST['first_name'] ) : '';
	$last_name    = isset( $_POST['last_name'] ) ? \wp_unslash( $_POST['last_name'] ) : '';
	$message_name = isset( $_POST['message_name'] ) ? \wp_unslash( $_POST['message_name'] ) : '';
	$password     = isset( $_POST['password'] ) ? \wp_unslash( $_POST['password'] ) : '';
	$from_email   = isset( $_POST['from_email'] ) ? \wp_unslash( $_POST['from_email'] ) : '';
	$reply_to     = isset( $_POST['reply_to'] ) ? \wp_unslash( $_POST['reply_to'] ) : '';
	$cc_emails_raw = isset( $_POST['cc_emails'] ) ? \wp_unslash( $_POST['cc_emails'] ) : '';

	// Parse CC emails for preview
	$preview_cc_list = array();
	if ( ! empty( $cc_emails_raw ) ) {
		$cc_decoded = json_decode( $cc_emails_raw, true );
		if ( is_array( $cc_decoded ) ) {
			foreach ( $cc_decoded as $cc_email ) {
				$cc_clean = trim( $cc_email );
				if ( $cc_clean && \is_email( $cc_clean ) ) {
					$preview_cc_list[] = $cc_clean;
				}
			}
		}
	}

	// Build replacements using centralized function
	$replacements = asset_portal_get_shortcode_replacements( $post_id, array(
		'name'         => $name,
		'first_name'   => $first_name,
		'last_name'    => $last_name,
		'message_name' => $message_name,
		'password'     => $password,
		'emails'       => $raw_emails,
	) );

	// ==========================================================================
	// PROPER SHORTCODE PROCESSING ORDER:
	// 1. Simple replacements first (injects content that may contain more shortcodes)
	// 2. Parameterized shortcodes (process format="", size="", etc. in injected content)
	// 3. Simple replacements again (catch any remaining simple shortcodes)
	// ==========================================================================
	
	// First pass: Simple shortcodes (injects [audit_knowledge_panel] content etc.)
	$subject   = strtr( $subject, $replacements );
	$body      = strtr( $body, $replacements );
	$signature = strtr( $signature, $replacements );
	
	// Decode brackets in injected content (WYSIWYG may encode them)
	$decode_brackets = function( $text ) {
		$text = str_replace(
			array( '&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;' ),
			array( '[', ']', '[', ']', '[', ']' ),
			$text
		);
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	};
	$subject   = $decode_brackets( $subject );
	$body      = $decode_brackets( $body );
	$signature = $decode_brackets( $signature );

	// Process parameterized shortcodes (now including those inside injected content)
	$subject   = asset_portal_process_parameterized_shortcodes( $subject, $post_id );
	$body      = asset_portal_process_parameterized_shortcodes( $body, $post_id );
	$signature = asset_portal_process_parameterized_shortcodes( $signature, $post_id );

	// Second pass: Simple shortcodes (catch any remaining)
	$final_subject   = strtr( $subject, $replacements );
	$final_body      = strtr( $body, $replacements );
	$final_signature = strtr( $signature, $replacements );
	
	// Decode again after second pass
	$final_subject   = $decode_brackets( $final_subject );
	$final_body      = $decode_brackets( $final_body );
	$final_signature = $decode_brackets( $final_signature );

	// Process WordPress shortcodes
	$final_body      = \do_shortcode( $final_body );
	$final_signature = \do_shortcode( $final_signature );

	// Build preview HTML
	$html = '<div style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, sans-serif;">';
	
	$html .= '<div style="margin-bottom:15px;padding:10px;background:#f5f5f5;border-radius:4px;">';
	if ( ! empty( $from_email ) ) {
		$html .= '<strong>From:</strong> ' . \esc_html( $from_email ) . '<br>';
	}
	$html .= '<strong>To:</strong> ' . \esc_html( $raw_emails ?: '(no recipient)' ) . '<br>';
	if ( ! empty( $reply_to ) ) {
		$html .= '<strong>Reply-To:</strong> ' . \esc_html( $reply_to ) . '<br>';
	}
	if ( ! empty( $preview_cc_list ) ) {
		$html .= '<strong>CC:</strong> ' . \esc_html( implode( ', ', $preview_cc_list ) ) . '<br>';
	}
	$html .= '<strong>Subject:</strong> ' . \esc_html( $final_subject ?: '(no subject)' );
	$html .= '</div>';
	
	$html .= '<div style="border:1px solid #ddd;padding:20px;border-radius:4px;background:#fff;">';
	$html .= '<div class="email-body">' . \wp_kses_post( $final_body ) . '</div>';
	
	if ( trim( $final_signature ) !== '' ) {
		$html .= '<hr style="margin:20px 0;border:none;border-top:1px solid #eee;">';
		$html .= '<div class="email-signature">' . \wp_kses_post( $final_signature ) . '</div>';
	}
	
	$html .= '</div>';
	$html .= '</div>';

	\wp_send_json_success( array( 'html' => $html ) );
}

/**
 * Centralized shortcode replacements.
 * Call this from anywhere to get the replacement array.
 */
function asset_portal_get_shortcode_replacements( $post_id, $data = array() ) {
	$name         = $data['name'] ?? '';
	$first_name   = $data['first_name'] ?? '';
	$last_name    = $data['last_name'] ?? '';
	$message_name = $data['message_name'] ?? '';
	$password     = $data['password'] ?? '';
	$raw_emails   = $data['emails'] ?? '';

	// ==========================================================================
	// SMART NAME PARSING
	// If first_name or last_name are empty, try to parse from full name
	// ==========================================================================
	$first_name = trim((string) $first_name);
	$last_name = trim((string) $last_name);
	$name = trim((string) $name);
	$message_name = trim((string) $message_name);
	
	if (empty($first_name) || empty($last_name)) {
		// Determine which full name to use for parsing
		$name_to_parse = '';
		
		if (!empty($name)) {
			$name_to_parse = $name;
		} elseif (!empty($message_name)) {
			$name_to_parse = $message_name;
		}
		
		if (!empty($name_to_parse)) {
			// Call the parser (same namespace - hws_portal)
			$parsed = hws_portal_parse_full_name($name_to_parse);
			
			// Only fill in what's missing
			if (empty($first_name) && !empty($parsed['first_name'])) {
				$first_name = $parsed['first_name'];
			}
			if (empty($last_name) && !empty($parsed['last_name'])) {
				$last_name = $parsed['last_name'];
			}
		}
	}

	$permalink = \get_permalink( $post_id );
	$permalink = $permalink ? \esc_url( $permalink ) : '';

	// Get deliverables field content
	$deliverables = '';
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$deliverables = \Hexa\PluginCore\Fields\Field::get( 'delivertables', $post_id );
		if ( ! $deliverables ) {
			$deliverables = '';
		}
	}

	// Get audit field content - NESTED STRUCTURE
	$audit = \Hexa\PluginCore\Fields\Field::available() ? \Hexa\PluginCore\Fields\Field::get( 'audit', $post_id ) : array();
	
	// Knowledge Panel group
	$audit_kp_report = '';
	$audit_kp_search_results = '';
	$audit_kp_image = '';
	$audit_kp_kgmid = '';
	$audit_kp_detailed_feedback = '';
	// Website group
	$audit_ws_url_personal = '';
	$audit_ws_url_company = '';
	$audit_ws_report = '';
	$audit_ws_image_personal = '';
	$audit_ws_image_company = '';
	$audit_ws_detailed_feedback = '';
	$audit_ws_schema_url_personal = '';
	$audit_ws_schema_url_company = '';
	
	if ( is_array( $audit ) ) {
		// Knowledge Panel (now a group)
		if ( isset( $audit['knowledge_panel'] ) && is_array( $audit['knowledge_panel'] ) ) {
			$audit_kp_report = $audit['knowledge_panel']['report'] ?? '';
			$audit_kp_search_results = $audit['knowledge_panel']['search_results'] ?? '';
			$audit_kp_image = $audit['knowledge_panel']['image'] ?? '';
			$audit_kp_kgmid = $audit['knowledge_panel']['kgmid'] ?? '';
			$audit_kp_detailed_feedback = $audit['knowledge_panel']['detailed_feedback'] ?? '';
		}
		// Website (now a group)
		if ( isset( $audit['website'] ) && is_array( $audit['website'] ) ) {
			$audit_ws_url_personal = $audit['website']['url_personal'] ?? '';
			$audit_ws_url_company = $audit['website']['url_company'] ?? '';
			$audit_ws_report = $audit['website']['report'] ?? '';
			$audit_ws_image_personal = $audit['website']['image_personal'] ?? '';
			$audit_ws_image_company = $audit['website']['image_company'] ?? '';
			$audit_ws_detailed_feedback = $audit['website']['detailed_feedback'] ?? '';
			$audit_ws_schema_url_personal = $audit['website']['report_schema_url_personal'] ?? '';
			$audit_ws_schema_url_company = $audit['website']['report_schema_url_company'] ?? '';
		}
	}
	
	// If schema URLs not stored in ACF, build dynamically
	if ( empty( $audit_ws_schema_url_personal ) && ! empty( $audit_ws_url_personal ) ) {
		$audit_ws_schema_url_personal = 'https://validator.schema.org/#url=' . rawurlencode( $audit_ws_url_personal );
	}
	if ( empty( $audit_ws_schema_url_company ) && ! empty( $audit_ws_url_company ) ) {
		$audit_ws_schema_url_company = 'https://validator.schema.org/#url=' . rawurlencode( $audit_ws_url_company );
	}
	
	// Get schema example URL from theme options (email_templates.audit)
	$audit_ws_schema_example_url = '';
	
	// Get Google Sheet Submissions URL from theme options
	$google_sheet_submissions_url = '';
	$google_sheet_submissions_link = '';
	
	// Get via ACF first
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$email_templates = \Hexa\PluginCore\Fields\Field::get( 'email_templates', 'option' );
		if ( is_array( $email_templates ) && isset( $email_templates['audit'] ) && is_array( $email_templates['audit'] ) ) {
			$audit_ws_schema_example_url = $email_templates['audit']['website_schema_example_url'] ?? '';
		}
		// Get settings group for Google Sheet
		$settings = \Hexa\PluginCore\Fields\Field::get( 'settings', 'option' );
		if ( is_array( $settings ) ) {
			$google_sheet_submissions_url = $settings['google_sheet_submissions'] ?? '';
		}
	}
	
	// Build clickable link for Google Sheet
	if ( ! empty( $google_sheet_submissions_url ) ) {
		$google_sheet_submissions_link = '<a href="' . \esc_url( $google_sheet_submissions_url ) . '" target="_blank">' . \esc_html( $google_sheet_submissions_url ) . '</a>';
	}
	
	// Fallback to direct option retrieval
	if ( empty( $audit_ws_schema_example_url ) ) {
		$audit_ws_schema_example_url = \get_option( 'options_email_templates_audit_website_schema_example_url' ) ?: '';
	}

	// Format image fields as img tags
	$audit_kp_image_html = '';
	if ( ! empty( $audit_kp_image ) ) {
		$audit_kp_image_html = '<img src="' . \esc_url( $audit_kp_image ) . '" alt="Knowledge Panel Screenshot" />';
	}
	
	$audit_ws_image_html = '';
	if ( ! empty( $audit_ws_image ) ) {
		$audit_ws_image_html = '<img src="' . \esc_url( $audit_ws_image ) . '" alt="Website Screenshot" />';
	}

	// Parse emails
	$emails = array();
	if ( $raw_emails ) {
		$email_parts = preg_split( '/[,;]+/', $raw_emails );
		if ( is_array( $email_parts ) ) {
			foreach ( $email_parts as $part ) {
				$email_clean = trim( $part );
				if ( $email_clean && \is_email( $email_clean ) ) {
					$emails[] = $email_clean;
				}
			}
		}
	}

	$recipient_full_name = trim( (string) $name );
	if ( '' === $recipient_full_name ) {
		$recipient_full_name = trim( (string) $first_name . ' ' . (string) $last_name );
	}
	if ( '' === $recipient_full_name ) {
		$recipient_full_name = trim( (string) $message_name );
	}

	return array(
		// Basic shortcodes
		'[name]'                 => $recipient_full_name,
		'[recipient_name]'       => $recipient_full_name,
		'[first_name]'           => $first_name,
		'[last_name]'            => $last_name,
		'[recipient_first_name]' => $first_name,
		'[recipient_last_name]'  => $last_name,
		'[message_name]'         => $message_name,
		'[password]'             => $password,
		'[email]'                => implode( ', ', $emails ),
		'[permalink]'            => $permalink,
		'[deliverables]'         => $deliverables,
		// Audit - Knowledge Panel
		'[audit_knowledge_panel]'                     => $audit_kp_report,
		'[audit_knowledge_panel_report]'              => $audit_kp_report,
		'[audit_knowledge_panel_search_results]'      => $audit_kp_search_results,
		// Note: [audit_knowledge_panel_before_image] and [audit_knowledge_panel_kgmid_image] handled by parameterized processor
		'[audit_knowledge_panel_kgmid]'               => $audit_kp_kgmid,
		'[audit_knowledge_panel_detailed_feedback]'   => $audit_kp_detailed_feedback,
		// Audit - Website
		'[audit_website]'                             => $audit_ws_report,
		'[audit_website_report]'                      => $audit_ws_report,
		'[audit_website_url_personal]'                => $audit_ws_url_personal,
		'[audit_website_url_company]'                 => $audit_ws_url_company,
		// Note: Image shortcodes handled by parameterized processor
		'[audit_website_detailed_feedback]'           => $audit_ws_detailed_feedback,
		// Schema URLs (from ACF text fields or dynamically built)
		'[audit_website_report_schema_url_personal]'  => $audit_ws_schema_url_personal,
		'[audit_website_report_schema_url_company]'   => $audit_ws_schema_url_company,
		'[audit_website_schema_example_url]'          => $audit_ws_schema_example_url,
		'[audit_website_schema_example_link]'         => $audit_ws_schema_example_url, // Alias
		// Google Sheet Submissions
		'[google_sheet_submissions]'                  => $google_sheet_submissions_link,
		'[google_sheet_submissions_url]'              => $google_sheet_submissions_url,
	);
}

function asset_portal_handle_send_email_ajax() {

	if ( ! \current_user_can( 'edit_posts' ) ) {
		\wp_send_json_error( array( 'message' => 'Permission denied.' ) );
	}

	$post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;
	if ( ! $post_id || \get_post_type( $post_id ) !== HWS_PORTAL_CPT ) {
		\wp_send_json_error( array( 'message' => 'Invalid asset portal item.' ) );
	}

	$raw_emails   = isset( $_POST['emails'] ) ? \wp_unslash( $_POST['emails'] ) : '';
	$subject      = isset( $_POST['subject'] ) ? \wp_unslash( $_POST['subject'] ) : '';
	$body         = isset( $_POST['body'] ) ? \wp_unslash( $_POST['body'] ) : '';
	$signature    = isset( $_POST['signature'] ) ? \wp_unslash( $_POST['signature'] ) : '';
	$name         = isset( $_POST['name'] ) ? \wp_unslash( $_POST['name'] ) : '';
	$first_name   = isset( $_POST['first_name'] ) ? \wp_unslash( $_POST['first_name'] ) : '';
	$last_name    = isset( $_POST['last_name'] ) ? \wp_unslash( $_POST['last_name'] ) : '';
	$message_name = isset( $_POST['message_name'] ) ? \wp_unslash( $_POST['message_name'] ) : '';
	$password     = isset( $_POST['password'] ) ? \wp_unslash( $_POST['password'] ) : '';
	$from_email   = isset( $_POST['from_email'] ) ? \wp_unslash( $_POST['from_email'] ) : '';
	$reply_to     = isset( $_POST['reply_to'] ) ? \wp_unslash( $_POST['reply_to'] ) : '';
	$cc_emails_raw = isset( $_POST['cc_emails'] ) ? \wp_unslash( $_POST['cc_emails'] ) : '';
	$is_test      = isset( $_POST['is_test'] ) ? intval( $_POST['is_test'] ) : 0;

	if ( '' === trim( $raw_emails ) ) {
		\wp_send_json_error( array( 'message' => 'No recipient email(s) provided.' ) );
	}
	if ( '' === trim( $subject ) ) {
		\wp_send_json_error( array( 'message' => 'Subject is required.' ) );
	}
	if ( '' === trim( $body ) ) {
		\wp_send_json_error( array( 'message' => 'Body is required.' ) );
	}

	$email_parts = preg_split( '/[,;]+/', $raw_emails );
	$emails      = array();

	if ( is_array( $email_parts ) ) {
		foreach ( $email_parts as $part ) {
			$email_clean = trim( $part );
			if ( $email_clean && \is_email( $email_clean ) ) {
				$emails[] = $email_clean;
			}
		}
	}

	if ( empty( $emails ) ) {
		\wp_send_json_error( array( 'message' => 'No valid email addresses found.' ) );
	}

	$permalink = \get_permalink( $post_id );
	$permalink = $permalink ? \esc_url( $permalink ) : '';

	// Get deliverables field content
	$deliverables = '';
	if ( \Hexa\PluginCore\Fields\Field::available() ) {
		$deliverables = \Hexa\PluginCore\Fields\Field::get( 'delivertables', $post_id );
		if ( ! $deliverables ) {
			$deliverables = '';
		}
	}

	$recipient_full_name = trim( (string) $name );
	if ( '' === $recipient_full_name ) {
		$recipient_full_name = trim( (string) $first_name . ' ' . (string) $last_name );
	}
	if ( '' === $recipient_full_name ) {
		$recipient_full_name = trim( (string) $message_name );
	}

	$replacements = array(
		'[name]'                 => $recipient_full_name,
		'[recipient_name]'       => $recipient_full_name,
		'[first_name]'           => $first_name,
		'[last_name]'            => $last_name,
		'[recipient_first_name]' => $first_name,
		'[recipient_last_name]'  => $last_name,
		'[message_name]'         => $message_name,
		'[password]'             => $password,
		'[email]'                => implode( ', ', $emails ),
		'[permalink]'            => $permalink,
		'[deliverables]'         => $deliverables,
	);

	// ==========================================================================
	// PROPER SHORTCODE PROCESSING ORDER:
	// 1. Simple replacements first (injects content that may contain more shortcodes)
	// 2. Parameterized shortcodes (process format="", size="", etc. in injected content)
	// 3. Simple replacements again (catch any remaining simple shortcodes)
	// ==========================================================================
	
	// First pass: Simple shortcodes (injects [audit_knowledge_panel] content etc.)
	$subject   = strtr( $subject, $replacements );
	$body      = strtr( $body, $replacements );
	$signature = strtr( $signature, $replacements );
	
	// Decode brackets in injected content (WYSIWYG may encode them)
	$decode_brackets = function( $text ) {
		$text = str_replace(
			array( '&#91;', '&#93;', '&lbrack;', '&rbrack;', '&#x5B;', '&#x5D;' ),
			array( '[', ']', '[', ']', '[', ']' ),
			$text
		);
		return html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	};
	$subject   = $decode_brackets( $subject );
	$body      = $decode_brackets( $body );
	$signature = $decode_brackets( $signature );

	// Process parameterized shortcodes (now including those inside injected content)
	$subject   = asset_portal_process_parameterized_shortcodes( $subject, $post_id );
	$body      = asset_portal_process_parameterized_shortcodes( $body, $post_id );
	$signature = asset_portal_process_parameterized_shortcodes( $signature, $post_id );

	// Second pass: Simple shortcodes (catch any remaining)
	$final_subject   = strtr( $subject, $replacements );
	$final_body_main = strtr( $body, $replacements );
	$final_signature = strtr( $signature, $replacements );
	
	// Decode again after second pass
	$final_subject   = $decode_brackets( $final_subject );
	$final_body_main = $decode_brackets( $final_body_main );
	$final_signature = $decode_brackets( $final_signature );

	$final_body_main = trim( $final_body_main );
	$final_signature = trim( $final_signature );

	if ( $final_body_main !== '' && $final_body_main === \wp_strip_all_tags( $final_body_main ) ) {
		$final_body_main = \wpautop( $final_body_main );
	}
	if ( $final_signature !== '' && $final_signature === \wp_strip_all_tags( $final_signature ) ) {
		$final_signature = \wpautop( $final_signature );
	}

	$final_body_main = \do_shortcode( $final_body_main );
	$final_signature = \do_shortcode( $final_signature );

	$final_body = $final_body_main;
	if ( '' !== $final_signature ) {
		if ( '' !== $final_body ) {
			$final_body .= "\n<br><br>\n";
		}
		$final_body .= $final_signature;
	}

	$desired_from_email = \get_bloginfo( 'admin_email' );
	$desired_from_name  = ( '' !== trim( $message_name ) ) ? $message_name : \get_bloginfo( 'name' );
	
	// Use from_email from ACF field if set
	if ( '' !== trim( $from_email ) && \is_email( $from_email ) ) {
		$desired_from_email = trim( $from_email );
	}

	$from_filter = function( $from ) use ( $desired_from_email ) {
		return $desired_from_email ? $desired_from_email : $from;
	};
	$from_name_filter = function( $from_name ) use ( $desired_from_name ) {
		return $desired_from_name ? $desired_from_name : $from_name;
	};

	\add_filter( 'wp_mail_from', $from_filter, 9999 );
	\add_filter( 'wp_mail_from_name', $from_name_filter, 9999 );

	$headers   = array();
	$headers[] = 'Content-Type: text/html; charset=UTF-8';
	
	// Add Reply-To header if set
	if ( '' !== trim( $reply_to ) && \is_email( $reply_to ) ) {
		$headers[] = 'Reply-To: ' . trim( $reply_to );
	}

	// Add CC headers
	$cc_list = array();
	if ( ! empty( $cc_emails_raw ) ) {
		$cc_decoded = json_decode( $cc_emails_raw, true );
		if ( is_array( $cc_decoded ) ) {
			foreach ( $cc_decoded as $cc_email ) {
				$cc_clean = trim( $cc_email );
				if ( $cc_clean && \is_email( $cc_clean ) ) {
					$cc_list[] = $cc_clean;
				}
			}
		}
	}
	if ( ! empty( $cc_list ) ) {
		$headers[] = 'Cc: ' . implode( ', ', $cc_list );
	}

	$sent = \wp_mail( $emails, $final_subject, $final_body, $headers );

	\remove_filter( 'wp_mail_from', $from_filter, 9999 );
	\remove_filter( 'wp_mail_from_name', $from_name_filter, 9999 );

	if ( $sent ) {
		// Update status to 'email_sent' for non-test emails
		if ( ! $is_test && \Hexa\PluginCore\Fields\Field::available() ) {
			\Hexa\PluginCore\Fields\Field::update( 'status', 'email_sent', $post_id );
		}
		
		// Log to activity log
		if ( function_exists( '\\hws_portal\\hws_portal_log_activity' ) ) {
			$log_type = $is_test ? 'test_email_sent' : 'email_sent';
			$log_msg = ($is_test ? 'Test email' : 'Email') . ' sent: ' . \get_the_title( $post_id );
			$log_details = 'To: ' . implode( ', ', $emails );
			if ( ! empty( $cc_list ) ) {
				$log_details .= ' | CC: ' . implode( ', ', $cc_list );
			}
			\hws_portal\hws_portal_log_activity( $log_type, $log_msg, $log_details );
		}
		
		$current_user = \wp_get_current_user();
		$user_login   = ( $current_user && $current_user->ID ) ? $current_user->user_login : 'unknown';
		$user_email   = ( $current_user && $current_user->ID ) ? $current_user->user_email : '';

		$body_for_log = $final_body;
		$max_len      = 5000;

		if ( function_exists( 'mb_strlen' ) ) {
			if ( mb_strlen( $body_for_log ) > $max_len ) {
				$body_for_log = mb_substr( $body_for_log, 0, $max_len ) . "\n...[truncated]";
			}
		} else {
			if ( strlen( $body_for_log ) > $max_len ) {
				$body_for_log = substr( $body_for_log, 0, $max_len ) . "\n...[truncated]";
			}
		}

		$signature_for_log = $final_signature;
		if ( function_exists( 'mb_strlen' ) ) {
			if ( mb_strlen( $signature_for_log ) > $max_len ) {
				$signature_for_log = mb_substr( $signature_for_log, 0, $max_len ) . "\n...[truncated]";
			}
		} else {
			if ( strlen( $signature_for_log ) > $max_len ) {
				$signature_for_log = substr( $signature_for_log, 0, $max_len ) . "\n...[truncated]";
			}
		}

		$masked_password = '';
		if ( '' !== trim( $password ) ) {
			$plen = function_exists( 'mb_strlen' ) ? mb_strlen( $password ) : strlen( $password );
			$masked_password = str_repeat( '*', min( 12, max( 4, (int) $plen ) ) ) . ' (len ' . (int) $plen . ')';
		}

		$email_name_for_log = ( '' !== trim( $message_name ) ) ? $message_name : '';

		$log_block  = "----------------------------------------\n";
		$log_block .= sprintf( "[%s] %s EMAIL SENT\n", \current_time( 'mysql' ), ( $is_test ? 'TEST' : 'LIVE' ) );
		$log_block .= sprintf( "Post ID: %d\n", (int) $post_id );
		$log_block .= sprintf( "Sent by: %s (%s)\n", $user_login, $user_email );
		$log_block .= sprintf( "From (requested): %s <%s>\n", $desired_from_name, $desired_from_email );
		$log_block .= sprintf( "To: %s\n", implode( ', ', $emails ) );
		if ( ! empty( $cc_list ) ) {
			$log_block .= sprintf( "CC: %s\n", implode( ', ', $cc_list ) );
		}
		$log_block .= sprintf( "Email Name (Message > Name): %s\n", $email_name_for_log );
		$log_block .= sprintf( "Recipient Name (ACF): %s\n", $name );
		$log_block .= sprintf( "Recipient First Name (ACF): %s\n", $first_name );
		$log_block .= sprintf( "Recipient Last Name (ACF): %s\n", $last_name );
		$log_block .= sprintf( "Subject (final): %s\n", $final_subject );
		$log_block .= sprintf( "Permalink: %s\n", $permalink );

		if ( '' !== $masked_password ) {
			$log_block .= sprintf( "Password (masked): %s\n", $masked_password );
		}

		$log_block .= "Signature (final HTML):\n";
		$log_block .= ( $signature_for_log !== '' ? $signature_for_log : '[empty]' ) . "\n";
		$log_block .= "Body (final HTML):\n";
		$log_block .= $body_for_log . "\n";
		$log_block .= "----------------------------------------";

		asset_portal_append_history_block( $post_id, $log_block );

		\wp_send_json_success( array( 'message' => $is_test ? 'Test email sent successfully.' : 'Email sent successfully.' ) );
	}

	\wp_send_json_error( array( 'message' => 'Email could not be sent (wp_mail returned false).' ) );
}
