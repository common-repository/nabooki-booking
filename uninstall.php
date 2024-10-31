<?php

/**
 * Nabooki Plugin - Uninstall
 */

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {

    die;
}

// Remove nabooki entries from database 
delete_option('nabooki_email');
delete_option('nabooki_token');
delete_option('nabooki_items');
