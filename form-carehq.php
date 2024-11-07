<?php
/*
Plugin Name: CareHQ Form Connector
Description: Integrates WordPress forms with CareHQ CRM, providing settings for API credentials and handling form submissions.
Version: 1.0
Author: Victory Digital
*/

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Include Composer's autoloader
require_once 'vendor/autoload.php';

// Initialize Sentry
\Sentry\init([
    'dsn' => 'https://f22f548b680fe4a8ef690f24297edbed@o4508200039022592.ingest.de.sentry.io/4508200041185360' ,
    'traces_sample_rate' => 1.0,
    'profiles_sample_rate' => 1.0,
  ]);
  

// Include settings and form handler files
require_once plugin_dir_path(__FILE__) . 'settings.php';
require_once plugin_dir_path(__FILE__) . 'form-handler.php';
