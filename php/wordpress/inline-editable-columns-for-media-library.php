<?php

	/**
	* title: Media Library inline editable columns for Alt, Title, Description (List view)
	* description: Images and SVG only - saves on blur when clicking to another field. Works in wp-admin -> Media -> List view.
	*/

    add_filter('manage_upload_columns', function ($columns) {
        $new = [];
        foreach ($columns as $key => $label) {
            $new[$key] = $label;
            if ($key === 'title') {
                $new['ml_inline_alt']   = 'Alt Text';
                $new['ml_inline_title'] = 'Title';
                $new['ml_inline_desc']  = 'Description';
            }
        }

        // Fallback if 'title' wasn't present
        if (!isset($new['ml_inline_alt'])) {
            $new['ml_inline_alt']   = 'Alt Text';
            $new['ml_inline_title'] = 'Title';
            $new['ml_inline_desc']  = 'Description';
        }

        return $new;
    }, 20);

    /** 2) Render editable fields - Images and SVG only */
    add_action('manage_media_custom_column', function ($column_name, $post_id) {
        // Only proceed for our custom columns
        if (!in_array($column_name, ['ml_inline_alt', 'ml_inline_title', 'ml_inline_desc'], true)) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $post_id)) {
            echo '<em>—</em>';
            return;
        }

        // Get mime type
        $mime_type = get_post_mime_type($post_id);

        // Only allow images (including SVG)
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!in_array($mime_type, $allowed_types, true)) {
            echo '<em>—</em>';
            return;
        }

        $post = get_post($post_id);
        if (!$post) {
            return;
        }

        $alt   = (string) get_post_meta($post_id, '_wp_attachment_image_alt', true);
        $title = (string) $post->post_title;
        $desc  = (string) $post->post_content;

        $nonce = wp_create_nonce('ml_inline_save_' . $post_id);

        $common_attrs = sprintf(
            'data-attach-id="%d" data-nonce="%s" class="ml-inline-field"',
            (int) $post_id,
            esc_attr($nonce)
        );

        if ($column_name === 'ml_inline_alt') {
            printf(
                '<input type="text" %s data-field="alt" value="%s" placeholder="Describe this image..." />',
                $common_attrs,
                esc_attr($alt)
            );
            echo '<span class="ml-inline-status"></span>';
        }

        if ($column_name === 'ml_inline_title') {
            printf(
                '<input type="text" %s data-field="title" value="%s" placeholder="Image title..." />',
                $common_attrs,
                esc_attr($title)
            );
            echo '<span class="ml-inline-status"></span>';
        }

        if ($column_name === 'ml_inline_desc') {
            printf(
                '<textarea %s data-field="desc" rows="3" placeholder="Image description...">%s</textarea>',
                $common_attrs,
                esc_textarea($desc)
            );
            echo '<span class="ml-inline-status"></span>';
        }
    }, 10, 2);

    /** 3) Enqueue styles and scripts */
    add_action('admin_enqueue_scripts', function ($hook) {
        if ($hook !== 'upload.php') {
            return;
        }

        // Add CSS
        $css = <<<CSS
    .ml-inline-field {
        width: 100%;
        max-width: 400px;
        padding: 6px 8px;
        border: 1px solid #ddd;
        border-radius: 3px;
        font-size: 13px;
        transition: border-color 0.2s;
    }

    .ml-inline-field:focus {
        border-color: #2271b1;
        outline: none;
        box-shadow: 0 0 0 1px #2271b1;
    }

    .ml-inline-field:disabled {
        background-color: #f6f7f7;
        cursor: not-allowed;
        opacity: 0.6;
    }

    .ml-inline-field[data-saving="true"] {
        border-color: #f0b849;
    }

    .ml-inline-status {
        display: block;
        font-size: 12px;
        margin-top: 4px;
        min-height: 16px;
        opacity: 0;
        transition: opacity 0.3s;
    }

    .ml-inline-status.show {
        opacity: 1;
    }

    .ml-inline-status.success {
        color: #1d7f2a;
    }

    .ml-inline-status.error {
        color: #b32d2e;
    }
    CSS;

        wp_add_inline_style('wp-admin', $css);

        // Add JavaScript
        wp_enqueue_script('jquery');

        $ajax_url = admin_url('admin-ajax.php');

        $js = <<<JS
    (function($) {
        'use strict';

        // Track which fields are currently saving
        var savingFields = {};

        function setStatus(\$field, msg, type) {
            var \$status = \$field.siblings('.ml-inline-status').first();
            if (!\$status.length) return;

            \$status
                .text(msg)
                .removeClass('success error')
                .addClass(type);

            if (msg) {
                \$status.addClass('show');

                // Auto-clear success messages after 3 seconds
                if (type === 'success') {
                    setTimeout(function() {
                        \$status.removeClass('show');
                    }, 3000);
                }
            } else {
                \$status.removeClass('show');
            }
        }

        function saveField(\$field) {
            var attachId = \$field.data('attach-id');
            var nonce = \$field.data('nonce');
            var fieldType = \$field.data('field');
            var value = \$field.val();
            var fieldKey = attachId + '_' + fieldType;

            // Prevent duplicate saves
            if (savingFields[fieldKey]) {
                return;
            }

            // Mark as saving
            savingFields[fieldKey] = true;
            \$field.prop('disabled', true).attr('data-saving', 'true');
            setStatus(\$field, 'Saving...', 'success');

            $.ajax({
                url: '$ajax_url',
                type: 'POST',
                data: {
                    action: 'ml_inline_save_attachment',
                    attach_id: attachId,
                    nonce: nonce,
                    field: fieldType,
                    value: value
                },
                timeout: 10000
            })
            .done(function(resp) {
                if (resp && resp.success) {
                    setStatus(\$field, 'Saved ✓', 'success');
                } else {
                    var errMsg = (resp && resp.data && resp.data.message)
                        ? resp.data.message
                        : 'Save failed';
                    setStatus(\$field, errMsg, 'error');
                }
            })
            .fail(function(xhr, status) {
                var errMsg = status === 'timeout'
                    ? 'Save timeout'
                    : 'Network error';
                setStatus(\$field, errMsg, 'error');
            })
            .always(function() {
                delete savingFields[fieldKey];
                \$field.prop('disabled', false).removeAttr('data-saving');
            });
        }

        // Save on blur (when clicking to another field)
        $(document).on('blur', '.ml-inline-field', function() {
            var \$field = $(this);

            // Small delay to ensure it's a real blur, not just a focus shift
            setTimeout(function() {
                if (!\$field.is(':focus')) {
                    saveField(\$field);
                }
            }, 100);
        });

        // Optional: Save on Ctrl/Cmd + Enter for textarea
        $(document).on('keydown', '.ml-inline-field', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.keyCode === 13) {
                e.preventDefault();
                $(this).blur(); // Trigger save via blur
            }
        });

    })(jQuery);
    JS;

        wp_add_inline_script('jquery', $js);
    });

    /** 4) AJAX handler */
    add_action('wp_ajax_ml_inline_save_attachment', function () {
        // Get and validate inputs
        $attach_id = isset($_POST['attach_id']) ? absint($_POST['attach_id']) : 0;
        $nonce     = isset($_POST['nonce']) ? sanitize_text_field($_POST['nonce']) : '';
        $field     = isset($_POST['field']) ? sanitize_key($_POST['field']) : '';
        $value     = isset($_POST['value']) ? wp_unslash($_POST['value']) : '';

        // Verify nonce
        if (!$attach_id || !wp_verify_nonce($nonce, 'ml_inline_save_' . $attach_id)) {
            wp_send_json_error(['message' => 'Invalid request.'], 400);
        }

        // Verify it's an attachment
        if (get_post_type($attach_id) !== 'attachment') {
            wp_send_json_error(['message' => 'Invalid attachment.'], 400);
        }

        // Check permissions
        if (!current_user_can('edit_post', $attach_id)) {
            wp_send_json_error(['message' => 'Permission denied.'], 403);
        }

        // Verify it's an image or SVG
        $mime_type = get_post_mime_type($attach_id);
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

        if (!in_array($mime_type, $allowed_types, true)) {
            wp_send_json_error(['message' => 'Only images can be edited.'], 400);
        }

        // Sanitize value
        if ($field === 'desc') {
            // Allow basic HTML in description
            $value = wp_kses_post($value);
        } else {
            $value = sanitize_text_field($value);
        }

        $value = trim($value);

        // Save based on field type
        switch ($field) {
            case 'alt':
                update_post_meta($attach_id, '_wp_attachment_image_alt', $value);
                wp_send_json_success(['message' => 'Alt text saved.']);
                break;

            case 'title':
                $result = wp_update_post([
                    'ID'         => $attach_id,
                    'post_title' => $value,
                ], true);

                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()], 400);
                }
                wp_send_json_success(['message' => 'Title saved.']);
                break;

            case 'desc':
                $result = wp_update_post([
                    'ID'           => $attach_id,
                    'post_content' => $value,
                ], true);

                if (is_wp_error($result)) {
                    wp_send_json_error(['message' => $result->get_error_message()], 400);
                }
                wp_send_json_success(['message' => 'Description saved.']);
                break;

            default:
                wp_send_json_error(['message' => 'Invalid field.'], 400);
        }
    });

?>