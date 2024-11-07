<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

add_action('admin_menu', 'carehq_add_admin_menu');

function carehq_add_admin_menu() {
    add_menu_page(
        'CareHQ Settings',
        'CareHQ Settings',
        'manage_options',
        'carehq_settings',
        'carehq_settings_page',
        'dashicons-networking',
        100
    );
}

// Register settings
add_action('admin_init', 'carehq_register_settings');

function carehq_register_settings() {
    register_setting('carehq_settings_group', 'carehq_api_key');
    register_setting('carehq_settings_group', 'carehq_api_url');
    register_setting('carehq_settings_group', 'carehq_api_secret');
    register_setting('carehq_settings_group', 'carehq_account_id');
    register_setting('carehq_settings_group', 'sales_channel_id');
    register_setting('carehq_settings_group', 'carehq_form_location_mapping');
}

// Render the settings page
function carehq_settings_page() {
    ?>
    <div class="wrap">
        <h1>CareHQ API Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('carehq_settings_group');
            do_settings_sections('carehq_settings_group');
            ?>

            <table class="form-table">
                <!-- Existing settings fields -->
                <tr valign="top">
                    <th scope="row"><label for="carehq_account_id">Account ID</label></th>
                    <td><input type="text" id="carehq_account_id" name="carehq_account_id" value="<?php echo esc_attr(get_option('carehq_account_id')); ?>" class="regular-text" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><label for="carehq_api_key">API Key</label></th>
                    <td><input type="text" id="carehq_api_key" name="carehq_api_key" value="<?php echo esc_attr(get_option('carehq_api_key')); ?>" class="regular-text" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="carehq_api_secret">API Secret</label></th>
                    <td><input type="password" id="carehq_api_secret" name="carehq_api_secret" value="<?php echo esc_attr(get_option('carehq_api_secret')); ?>" class="regular-text" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="sales_channel_id">Sales Channel ID</label></th>
                    <td>
                        <input type="text" id="sales_channel_id" name="sales_channel_id" value="<?php echo esc_attr(get_option('sales_channel_id')); ?>" class="regular-text" />
                        <p style="margin-top: 10px;" class="description">Enter the Sales Channel ID you would like forms to be submitted to in the CRM.</p>

                        <button id="get_sales_channels_button" class="button button-secondary" style="margin-top: 10px;">Get Sales Channels IDs</button>
                        <div id="sales_channel_response"></div>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="carehq_api_url">API URL</label></th>
                    <td>
                        <select id="carehq_api_url" name="carehq_api_url" class="regular-text">
                            <option value="https://api.carehq.dev" <?php selected(get_option('carehq_api_url'), 'https://api.carehq.dev'); ?>>
                                Developer (https://api.carehq.dev)
                            </option>
                            <option value="https://api.carehq.co.uk" <?php selected(get_option('carehq_api_url'), 'https://api.carehq.co.uk'); ?>>
                                Production (https://api.carehq.co.uk)
                            </option>
                        </select>
                    </td>
                </tr>

                <tr valign="top">
                    <th scope="row"><label for="">Location IDs</label></th>
                    <td>
                        <button id="get_locations_button" class="button button-secondary" style="margin-top: 10px;">Get Location IDs</button>
                        <div id="location_response"></div>
                    </td>
                </tr>
            </table>

            <!-- New section for Form Name, Form ID to Location ID Mapping -->
            <h2>Form to Location Mapping</h2>
            <p>Enter the Location ID for each form. The Form Name and Form ID are automatically retrieved from Gravity Forms.</p>
            <table id="form_location_mapping_table" class="widefat">
                <thead>
                    <tr>
                        <th>Form Name</th>
                        <th>Form ID</th>
                        <th>Location ID</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $mappings = get_option('carehq_form_location_mapping', array());
                    if (!is_array($mappings)) {
                        $mappings = array();
                    }

                    // Retrieve forms using GFAPI
                    if (class_exists('GFAPI')) {
                        $forms = GFAPI::get_forms();
                        if (!empty($forms)) {
                            foreach ($forms as $form) {
                                $form_id = $form['id'];
                                $form_name = $form['title'];
                                $location_id = isset($mappings[$form_id]) ? $mappings[$form_id] : '';
                                ?>
                                <tr>
                                    <td><?php echo esc_html($form_name); ?></td>
                                    <td><?php echo esc_html($form_id); ?></td>
                                    <td><input type="text" name="carehq_form_location_mapping[<?php echo esc_attr($form_id); ?>]" value="<?php echo esc_attr($location_id); ?>" /></td>
                                </tr>
                                <?php
                            }
                        } else {
                            echo '<tr><td colspan="3">No forms found.</td></tr>';
                        }
                    } else {
                        echo '<tr><td colspan="3">Gravity Forms is not installed or activated.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>

            <?php submit_button(); ?>
        </form>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Get Locations button
        $('#get_locations_button').on('click', function(e) {
            e.preventDefault();
            $('#location_response').html('Loading...');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_locations',
                    _ajax_nonce: '<?php echo wp_create_nonce("get_locations_nonce"); ?>'
                },
                success: function(response) {
                    let output = '<h3>Locations Information</h3><ul>';
                    if (response.success && response.data.locations && response.data.locations.items) {
                        response.data.locations.items.forEach(function(location) {
                            output += '<li><strong>Location Name:</strong> ' + location.name + '<br><strong>Location ID:</strong> ' + location._id + '</li>';
                        });
                    } else {
                        output = 'Failed to retrieve locations.';
                    }
                    output += '</ul>';
                    $('#location_response').html(output);
                },
                error: function() {
                    $('#location_response').text('Failed to retrieve locations.');
                }
            });
        });


        // Get Sales Channels button
        $('#get_sales_channels_button').on('click', function(e) {
            e.preventDefault();
            $('#sales_channel_response').html('Loading...');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_sales_channels',
                    _ajax_nonce: '<?php echo wp_create_nonce("get_sales_channels_nonce"); ?>'
                },
                success: function(response) {
                    let output = '<h3>Sales Channel Information</h3><ul>';
                    if (response.success && response.data.groups && response.data.groups.items) {
                        response.data.groups.items.forEach(function(group) {
                            output += '<li><strong>Sales Channel Name:</strong> ' + group.name + '<br><strong>Sales Channel ID:</strong> ' + group._id + '</li>';
                        });
                    } else {
                        output = 'Failed to retrieve sales channels.';
                    }
                    output += '</ul>';
                    $('#sales_channel_response').html(output);
                },
                error: function() {
                    $('#sales_channel_response').text('Failed to retrieve sales channels.');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler to get Locations


