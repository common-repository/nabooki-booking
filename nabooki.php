<?php
/**
   Plugin Name: Nabooki Booking Plugin
   Description: Use the Nabooki plugin to enable bookings from your Wordpress site
   Version: 0.3
   Author: Nabooki
   Author URI: http://nabooki.com
   License: GPL2
*/
$GLOBALS['nabooki_plugin_baseurl'] = 'https://services.nabooki.com';

class NabookiWidget extends WP_Widget
{
    public function __construct()
    {
        parent::__construct(false, 'Nabooki Widget');
    }

    public function widget($args, $instance)
    {
        $e = explode('-', $instance['widget-selection']);
        $type = $e[0];
        $service = $e[1];
        $paged = false;
        $token = null;

        // Paged service selected?
        if (isset($e[2]) && $e[2] == 1 && isset($e[3]) && $e[3] !== null) {

          $paged = true;
          $token = $e[3];
        }

        switch ($type) {

          case 'button':

            return nabooki_show_button(['service' => $service, 'paged' => $paged, 'token' => $token]);
            break;

          case 'widget':
          
            return nabooki_show_widget(['service' => $service, 'paged' => $paged, 'token' => $token]);
            break;
        }
    }

    public function update($new_instance, $old_instance)
    {
        return [
          'widget-selection' => $new_instance['widget-selection']
        ];
    }

    public function form($instance)
    {
        $items = get_option('nabooki_items');
        if ($items != '') {

            $items = unserialize($items);

        }
        if (!count($items)) {

          echo '<h4>You must add a new Nabooki widget item first!</h4>';
          echo '<h5>Go to the Settings / Nabooki page to add a new item.</h5>';
          return;
        }
        ?>
        <p>Select Nabooki widget to display:</p>
        <select id="<?php echo esc_attr($this->get_field_id('widget-selection')); ?>" name="<?php echo esc_attr($this->get_field_name('widget-selection')); ?>">
          <?php

          if (trim($instance['widget-selection']) == '') {

            echo '<option value="" selected="selected">Select a widget</option>';
          }

          foreach ($items as $i) {

            $concat = $i['type'] . '-' . $i['service_id'];

            if (isset($i['paged']) && isset($i['token']) && $i['paged'] == true && $i['token'] !== null) {

              $concat .= '-1-' . $i['token'];
            }

            $selected = '';

            if (trim($instance['widget-selection']) == trim($concat)) {

              $selected = ' selected="selected"';
            }

            echo '<option value="' . $concat . '"'. $selected .'>Nabooki ' . $i['type'] . ' : ' . $i['name'] . '</option>';
          }
        ?>
        </select><br /><br />
        <?php
    }
}

/**
 * Include Administration source
 */
function nabooki_admin()
{
    include('nabooki-admin.php');
}

/**
 * Register Administration
 */
function nabooki_admin_actions()
{  
    add_options_page("Nabooki", "Nabooki", "manage_options", "nabooki-booking-widgets", "nabooki_admin");
}

/**
 * Register Administration Head Contents
 */
function nabooki_admin_register_head()
{
    $base_url = get_option('siteurl') . '/wp-content/plugins/' . basename(dirname(__FILE__)) . '/';

    wp_enqueue_script('nabooki_js', $base_url . 'nabooki.js');
    // wp_enqueue_style('nabooki_css', $base_url . 'nabooki.css');
}

/**
 * Register Nabooki Widget Class
 */
function nabooki_register_widget()
{
    register_widget('NabookiWidget');
}

/**
 * Show the Button
 */
function nabooki_show_button($args)
{
    $token = get_option('nabooki_token');

    if ($token == null) {

      return '
            <div>
                <p>Booking button is unavailable, please link your Nabooki account first.</p>
            </div>
            ';
    }

    // Append token to all get requests
    $get_params = "?token=" . $token;

    // Has a service been selected?
    if (isset($args['service']) && $args['service'] != '') {

      // Append service to the get params
      $get_params .= "&sid=" . $args['service'];
    }

    // Assume an all services or non-paged service widget url
    $data_widget_url = $GLOBALS['nabooki_plugin_baseurl'] . "/booking/popup/widget/" . $get_params;

    // Has a paged service been selected?
    if (isset($args['paged']) && $args['paged'] == true && isset($args['token'])) {

      // Extract service token
      $service_token = $args['token'];

      // Different widget url is used
      $data_widget_url = $GLOBALS['nabooki_plugin_baseurl'] . "/booking/popup/service/" . $service_token . "?token=" . $token;
    }

    // Construct Frame src URL
    $frame_src_url = $GLOBALS['nabooki_plugin_baseurl'] . "/booking/button" . $get_params;

    // Show Book Now Button
    return '
        <iframe class="nb-button" style="max-height: 37px;" frameborder="0" scrolling="no" src="' . $frame_src_url . '" data-widget="' . $data_widget_url . '"></iframe><link href="' . $GLOBALS['nabooki_plugin_baseurl'] . '/css/booking-button.css" rel="stylesheet"/><script src="' . $GLOBALS['nabooki_plugin_baseurl'] . '/js/booking-button.js" defer async></script>
      ';
}

/**
 * Show the Widget
 */
function nabooki_show_widget($args)
{
    $token = get_option('nabooki_token');

    if ($token == null) {
      return '
        <div>
            <p>Widget is unavailable, please link your Nabooki account first.</p>
        </div>
      ';
    }

    // Append token to get request
    $get_params = "?token=" . $token;

    // Has a service been selected?
    if (isset($args['service']) && $args['service'] != '') {

      // Append service to the get request
      $get_params .= "&sid=" . $args['service'];
    }

    $frame_src_url = $GLOBALS['nabooki_plugin_baseurl'] . '/booking/step1' . $get_params;

    // Has a paged service been selected?
    if (isset($args['paged']) && $args['paged'] == true && isset($args['token'])) {

      // Extract service token
      $service_token = $args['token'];

      // Different widget url is used
      $frame_src_url = $GLOBALS['nabooki_plugin_baseurl'] . "/booking/service/" . $service_token . "?token=" . $token;
    }

    // Show the Booking Widget
    return '
        <iframe id="widget-inline-embed" frameborder="0" scrolling="yes" allowtransparency="true" style="width: 100%;  min-height: 550px; overflow: hidden; position: relative;" src="' . $frame_src_url .'" onload="initResizer();"></iframe><script src="' . $GLOBALS['nabooki_plugin_baseurl'] . '/js/iframeResizer.js"></script><script>var initResizer = function() { iframes = iFrameResize({}, "#widget-inline-embed"); }</script>
      ';
}

/**
 * Link Nabooki account
 */
function nabooki_link_account_callback()
{
    try {

        // Prepare POST
        $url = $GLOBALS['nabooki_plugin_baseurl'] . '/link/wordpress/account';
        $email = sanitize_email($_POST['email']);

        // Do POST
        $response = wp_remote_post($url, array(
            'body' => array(
              'email' => $email,
              'password' => $_POST['password']
            )
          )
        );

        // Decode the JSON response
        $decoded_response = json_decode($response['body']);

        // Error check
        if ($decoded_response->result == 'error' || !isset($decoded_response->token)) {

            echo 'error';
            die();
        }

        // Extract Token
        $token = $decoded_response->token;

        // Save Nabooki Email and Token in DB
        update_option('nabooki_email', $email);
        update_option('nabooki_token', $token);

        // Return success
        echo 'ok';
        die();

    } catch (\Exception $e) {

        // Return error
        echo 'error';
        die();
    }
}

/**
 * Unlink Nabooki account
 */
function nabooki_unlink_account_callback()
{
    try {

        // Remove Nabooki data from DB
        update_option('nabooki_email', $email);
        update_option('nabooki_token', $token);
        update_option('nabooki_items', $token);

        // Return success
        echo 'ok';
        die();

    } catch (\Exception $e) {

        // Return error
        echo 'error';
        die();
    }
}

// Register the Administration head contents
add_action('admin_head', 'nabooki_admin_register_head');

// Register the Administation menu item
add_action('admin_menu', 'nabooki_admin_actions');

// Register the Ajax Link Account Callback
add_action('wp_ajax_nabooki_link_account', 'nabooki_link_account_callback');

// Register the Ajax Unlink Account Callback
add_action('wp_ajax_nabooki_unlink_account', 'nabooki_unlink_account_callback');

// Register the Nabooki Button shortcode
add_shortcode('nabooki-button', 'nabooki_show_button');

// Register the Nabooki Widget shortcode
add_shortcode('nabooki-widget', 'nabooki_show_widget');

// Initialise widget
add_action('widgets_init', 'nabooki_register_widget');
