<?php

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include Composer's autoloader
require_once 'vendor/autoload.php';

// Include functions
require_once plugin_dir_path(__FILE__) . 'functions.php'; // Adjust the path as needed

// Hook into Gravity Forms' submission action
add_action('gform_after_submission', 'handle_gravity_forms_submission', 10, 2);


function handle_gravity_forms_submission($entry, $form) {
    try {
        // Use the entry form ID
        $form_id = $entry['form_id'];

        // Get location ID based on form ID
        $location_id = get_location_by_form_id($form_id);
        if (is_null($location_id)) {
            throw new Exception("Location ID not set for form ID: $form_id");
        }
        
        
        // Retrieve values for fields first to check enquiry type
        $enquiry_type = sanitize_text_field(rgar($entry, '4'));
        // Skip processing for feedback/complaint, manager callback, or other
        $skip_types = ['Feedback/Complaint', 'Manager Call Back', 'Other'];
        if (in_array($enquiry_type, $skip_types)) {
            error_log("Skipping CRM submission for enquiry type: $enquiry_type");
            return;
        }

        // Retrieve remaining field values
        $name = sanitize_text_field(rgar($entry, '1'));
        $email = sanitize_email(rgar($entry, '2'));
        $phone = sanitize_text_field(rgar($entry, '3'));
        $care_requirements_message = sanitize_textarea_field(rgar($entry, '5'));

        // Split the full name
        $name_parts = explode(' ', trim($name));
        $first_name = array_shift($name_parts); // Get the first element
        $last_name = !empty($name_parts) ? implode(' ', $name_parts) : '*'; // Join the rest

        // Prepare the data to send to the API
        $sales_channel_id = get_option('sales_channel_id');
        $form_data = [
            'first_name' => $first_name,
            'email' => $email,
            'enquiry_type' => $enquiry_type,
            'care_requirements' => "Enquiry Type: $enquiry_type - $care_requirements_message",
            'location' => $location_id,

            // Default values that are required by Care HQ API.
            'funding_type' => 'not_sure',
            'last_name' => $last_name,
            'service' => 'residential_home',
            'sales_channel' => $sales_channel_id,
        ];

        // Replace the phone cleaning logic with the new function
        $cleaned_phone = clean_phone_number($phone);
        if ($cleaned_phone) {
            $form_data['phone'] = $cleaned_phone;
        }

        // Send data to the API
        error_log("Sending data to API: " . json_encode($form_data));
        send_data_to_api($form_data);
        
    } catch (Exception $e) {
        // Log the error or handle it as needed
        error_log('Error handling Gravity Forms submission: ' . $e->getMessage());
    }
}
function get_location_by_form_id($form_id) {
    $mappings = get_option('carehq_form_location_mapping', array());
    if (!is_array($mappings)) {
        return null;
    }

    return isset($mappings[$form_id]) && !empty($mappings[$form_id]) ? $mappings[$form_id] : null;
}
function clean_phone_number($phone) {
    // Remove only non-numeric characters
    $cleaned_phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Convert 44 format to 0 format
    if (substr($cleaned_phone, 0, 2) === '44') {
        $cleaned_phone = '0' . substr($cleaned_phone, 2);
        error_log('Converted phone: ' . $cleaned_phone); // Debug log
    }
    
    // Check if it starts with 07 or 7 and has valid length
    if (preg_match('/^(07|7)/', $cleaned_phone) && strlen($cleaned_phone) <= 11) {
        return $cleaned_phone;
    }
    
    return null;
}

function send_data_to_api($data) {
    try {
        $api_client = initialize_api_client();
        $care_enquiry = $api_client->request('PUT', 'care-enquiries', null, $data);

        error_log($care_enquiry ? 'Care enquiry sent successfully.' : 'Failed to send care enquiry.');
    } catch (CareHQ\Exception\APIException $error) {
        \Sentry\captureException($error);
        error_log('API Error: ' . $error->getMessage());
    }
}
