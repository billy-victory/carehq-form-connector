<?php
// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function initialize_api_client() {
    try {
        // Retrieve settings values
        $account_id = get_option('carehq_account_id');
        $api_key = get_option('carehq_api_key');
        $api_secret = get_option('carehq_api_secret');
        $api_url = get_option('carehq_api_url');

        // Initialize the API client with dynamic values from settings
        return new CareHQ\APIClient(
            $account_id,
            $api_key,
            $api_secret,
            $api_url
        );
    } catch (Exception $e) {
        \Sentry\captureException($e);
        error_log('Failed to initialize API client: ' . $e->getMessage());
        return null;
    }
}

add_action('wp_ajax_get_locations', 'carehq_get_locations');

function carehq_get_locations() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    check_ajax_referer('get_locations_nonce', '_ajax_nonce');

    try {
        $api_client = initialize_api_client();
        $locations = $api_client->request(
            'GET',
            'locations',
            [
                'attributes'=>['name'],
                'filters-services'=>['residential_home']
            ]
        );
        wp_send_json_success(array('locations' => $locations));
        
    } catch (Exception $e) {
        \Sentry\captureException($e);
        error_log('Failed to get locations: ' . $e->getMessage());
        return ['error' => 'Failed to retrieve locations.'];
    }
}

// AJAX handler to get Sales Channels
add_action('wp_ajax_get_sales_channels', 'carehq_get_sales_channels');

function carehq_get_sales_channels() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized', 403);
    }
    check_ajax_referer('get_sales_channels_nonce', '_ajax_nonce');

    try {
        $api_client = initialize_api_client();
        $sales_channels = $api_client->request(
            'GET',
            'groups',
            [
                'attributes' => ['_id', 'name', 'sales_channel'],
                'filters-group_type' => 'sales_channel',
                'filters-archived' => 'no',
            ]
        );
        
        wp_send_json_success(array('groups' => $sales_channels));
    } catch (Exception $error) {
        wp_send_json_error('Error retrieving sales channels: ' . $error->getMessage());
    }
}