<?php
/**
 * Nabooki Plugin - Administration Class
 */
class NabookiAdmin
{
    protected $token = null;
    protected $email = null;
    protected $status = null;
    protected $items = [];
    protected $base_url = '';

    public function __construct()
    {
        // Get Merchant's token from db
        $this->token = trim(get_option('nabooki_token'));

        // Get Merchant's token from db
        $this->email = trim(get_option('nabooki_email'));

        // Get Widget Items from db
        $this->items = $this->_getWidgetItems();

        // Determine Merchant Account status
        if ($this->token == '' || $this->email == '') {

            $this->status = 'Not linked';
        }

        if ($this->token != '' && $this->email != '') {

            $this->status = 'Account linked';
        }

        $this->base_url = $GLOBALS['nabooki_plugin_baseurl'];
    }

    protected function _getWidgetItems()
    {
        $items = get_option('nabooki_items');

        if ($items == '') {

            return [];
        }

        $items = unserialize($items);

        return $items;
    }

    protected function _setWidgetItems(array $items)
    {
        // Serialise items and update db
        update_option('nabooki_items', serialize($items));
    }

    protected function _removeWidgetItem($target)
    {
        $result = [];

        $items = $this->_getWidgetItems();

        if (count($items) == 0) {

            return;
        }

        foreach($items as $subject) {

            // Does this item match?
            if (
                ($subject['type'] == $target['type'])
                                &&
                ($subject['name'] == $target['name'])
                                &&
                ($subject['service_id'] == $target['service_id'])
                                &&
                ($subject['paged'] == $target['paged'])
                                &&
                ($subject['token'] == $target['token'])
            ) {
                // Matches, omit from result as we are removing
                ;

            } else {

                // No match, add to result
                $result[] = $subject;
            }
        }

        // Update the db with the new result
        $this->_setWidgetItems($result);
    }

    /**
     * The main entry point for the Admin
     */
    public function run()
    {
        if (isset($_POST) && !empty($_POST)) {

            return $this->_processPost($_POST);
        }

        // Show the Admin page
        return $this->_viewAdminPage($this->status, $this->email, $this->token, $this->items);
    }

    protected function _processPost($params)
    {
        if (!isset($params['action']) || $params['action'] == null) {

            die("<Error processing post. Please press back and try again.");
        }

        switch ($params['action']) {

            case 'create_widget_item':

                return $this->_processCreateWidgetItem($params);
                break;

            case 'remove_widget_item':

                return $this->_processRemoveWidgetItem($params);
                break;

            default:
                die("Error, action not recognised.");
                break;
        }
    }

    /**
     * Create a new nabooki widget from a form post
     */
    protected function _processCreateWidgetItem($params)
    {
        $this->_validatePostParams($params);

        // Get all current widget items
        $items = $this->_getWidgetItems();

        // Paged service?
        $token = '';
        $paged = false;
        if (strstr($params['nabooki_service_id'], '_') !== false) {

            // Split service id to get the token
            $sp = explode("_", $params['nabooki_service_id']);

            $params['nabooki_service_id'] = $sp[0];
            $token = $sp[1];
            $paged = true;
        }

        // Append this widget item
        $items[] = [
            'type' => $params['nabooki_widget_type'],
            'name' => $params['nabooki_widget_name'],
            'service_id' => $params['nabooki_service_id'],
            'paged' => $paged,
            'token' => $token
        ];

        // Update the database
        $this->_setWidgetItems($items);

        return $this->_refresh();
    }

    protected function _processRemoveWidgetItem($params)
    {
        $this->_validatePostParams($params);

        // Update the database
        $this->_removeWidgetItem([
            "name" => $params['nabooki_widget_name'],
            "type" => $params['nabooki_widget_type'],
            "service_id" => $params['nabooki_service_id'],
            "paged" => $params['nabooki_service_paged'],
            "token" => $params['nabooki_service_token'],
        ]);

        return $this->_refresh();
    }

    protected function _refresh()
    {
        // Prevent post loop
        unset($_POST);

        // Update the items
        $this->items = $this->_getWidgetItems();

        // Refresh admin page
        return $this->run();
    }

    protected function _validatePostParams($params)
    {
        // Validate name first
        if (
            (!isset($params['nabooki_widget_name']))
                            ||
            (trim($params['nabooki_widget_name']) == '')
        ) {
            echo '<h2>You must assign a name to the new Nabooki widget. Please press back in your browser and try again.</h2>';
            die();
        }

        // Validate type and service id
        if (
            (!isset($params['nabooki_widget_type']))
                            ||
            (!isset($params['nabooki_service_id']))
        ) {
            echo '<h2>An error was encountered. Please press back in your browser and try again.</h2>';
            die();
        }
    }

    protected function _viewInvalidPost()
    {
        return $this->_viewShowMesssageDiv('An error has occured, please try again.');
    }

    protected function _viewValidPost()
    {
        return $this->_viewShowMesssageDiv('Your Nabooki account has been successfully linked. You can now embed the Booking Button of Nabooki Booking Widget.');
    }

    protected function _viewShowMesssageDiv($message)
    {
        ?>
            <div class="nabooki-error-container">
                <p><?php _e($message); ?></p>
            </div>
        <?php
    }

    protected function _viewAdminPage($status, $email, $token, $items)
    {
        echo '<div class="wrap"><h1>Nabooki Plugin Settings</h1>';

        if ($status == 'Account linked') {

            // Show unlink page
            $this->_viewUnlinkAccount($status, $email, $token);

            echo '<hr />';

            // Show item management
            $this->_viewManageItems($items, $token);

        } else {

            // Show link account page
            $this->_viewShowForm($status, $email, $token);
        }

        echo '<hr />';
        echo '</div>';

        // $this->_viewShowWidgetPreview($token);
    }

    protected function _viewManageItems($items, $token)
    {
        echo '<h2>Nabooki widget items</h2>';

        if (count($items) == 0) {

            echo "<p>You don't have any widget items, please create one below.</p>";

        } else {

            foreach($items as $item) {

                $this->_viewWidgetItem($item);
            }
        }

        echo '<hr />';

        $this->_viewCreateWidgetItem();
    }

    protected function _viewWidgetItem($item)
    {
        echo "<div style='padding:25px; background:#ddd; width: 80%; margin-bottom: 15px;'>";
        echo "<h4>Name: " . $item['name'] . "</h4>";
        echo "<h4>Type: " . $item['type'] . "</h4>";
        echo "<h4>Show details page: " . ($item['paged'] ? 'Yes' : 'No') . "</h4>";
        echo "<h4>Shortcode tag (copy and paste the following bold text to use in blog posts):</h4>";
        echo "<h3>" . $this->_viewShortCode($item) . "</h3>";
        echo "<h4>Preview:</h4>";

        switch ($item['type']) {

            case 'button':
                echo nabooki_show_button(['service' => $item['service_id'], 'paged' => $item['paged'], 'token' => $item['token']]);
                break;

            case 'widget':
                echo nabooki_show_widget(['service' => $item['service_id'], 'paged' => $item['paged'], 'token' => $item['token']]);
                break;

            default:
                break;
        }
        ?>
        <hr />
        <form name="nabooki_remove_widget_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
            <input type="hidden" name="action" id="action" value="remove_widget_item" />
            <input type="hidden" name="nabooki_widget_name" id="nabooki_widget_name" value="<?php echo $item['name'] ?>" />
            <input type="hidden" name="nabooki_widget_type" id="nabooki_widget_type" value="<?php echo $item['type'] ?>" />
            <input type="hidden" name="nabooki_service_id" id="nabooki_service_id" value="<?php echo $item['service_id'] ?>" />
            <input type="hidden" name="nabooki_service_paged" id="nabooki_service_paged" value="<?php echo $item['paged'] ?>" />
            <input type="hidden" name="nabooki_service_token" id="nabooki_service_token" value="<?php echo $item['token'] ?>" />
            <h4>Action: <button type="submit" id="btn_nabooki_create_widget" class="button button-primary">Remove this widget item*</button></h4>
            <p><b>*Note:</b> this does not automatically update your posts. Any reference to this widget will have to be manually removed.</p>
        </form>
        <?php
        echo "</div>";
    }

    protected function _viewShortCode($item)
    {
        $service = '';

        if ($item['service_id'] !== '') {

            $service = ' service="' . $item['service_id'] . '"';
        }

        $paged = '';

        if ($item['paged'] === true && $item['token'] !== null) {

            $paged = ' paged="true" token="' . $item['token'] . '"';
        }

        return '[' . 'nabooki-' . $item['type'] . $service . $paged . ']';
    }

    protected function _viewCreateWidgetItem()
    {
        $services_response = $this->_getServices($this->token);
        $services = $services_response['services'];
        $services_paged = $services_response['services_paged'];

        if (!$services) {
            ?>
            <div id="nabooki_create_widget_error" style="display:none;">
                <h2>There was an error retrieving your Nabooki services. Please try again.</h2>
            </div>
            <?php

            return;
        }
        ?>
        <div id="nabooki_create_widget_container">
          <form name="nabooki_create_widget_form" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
            <input type="hidden" name="action" id="action" value="create_widget_item" />
            <h2>Create Nabooki widget</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="nabooki_widget_type">Select widget type</label>
                        </th>
                        <td>
                            <select name="nabooki_widget_type" id="nabooki_widget_type" style="width: 350px;">
                                <option value="button">Book Now Button</option>
                                <option value="widget">Booking Tool</option>
                            </select>
                            <p class="description" id="nabooki_widget_type_description">The type of Nabooki widget you want to create.</p>

                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nabooki_service_id">Select service</label>
                        </th>
                        <td>
                            <select name="nabooki_service_id" id="nabooki_service_id" style="width: 350px;">
                                <optgroup label="All Services">
                                    <option value="" selected="selected">All Services Booking Tool</option>
                                </optgroup>
                                <optgroup label="Single Service">
                                    <?php foreach($services as $s) {

                                        echo '<option value="' . $s->value . '">' . $s->name . '</option>';
                                    }
                                    ?>
                                </optgroup>
                                <?php if(count($services_paged)): ?>
                                    <optgroup label="Service Details Page">
                                        <?php foreach($services_paged as $p) {

                                            echo '<option value="' . $p->value . '_' . $p->token . '">' . $p->name . '</option>';
                                        }
                                        ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                            <p class="description" id="nabooki_service_id_description">Select the Nabooki Service linked to this widget.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nabooki_widget_name">Enter widget name</label>
                        </th>
                        <td>
                            <input name="nabooki_widget_name" type="text" id="nabooki_widget_name" size="16" value="" class="regular-text">
                            <p class="description" id="nabooki_widget_name_description">Name your widget in order to reference it later on.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">&nbsp;</th>
                        <td>
                            <button type="submit" id="btn_nabooki_create_widget" class="button button-primary">Create widget</button>
                        </td>
                    </tr>
                </tbody>
            </table>
          </form>
        </div>
        <div id="nabooki_create_widget_error" style="display:none;">
            <h2>There was an error creating your Nabooki widget. Please try again.</h2>
        </div>
        <?php
    }

    protected function _viewShowWidgetPreview($token)
    {
        ?>
            <h2>Booking Widget Preview</h2>
        <?php

        if ($token == null) {

            return $this->_viewShowMesssageDiv('Widget preview is currently unavailable, please link your account first.');
        }

        // Show the Booking Widget
        ?>
            <div>
               <iframe id="widget-inline-embed" frameborder="0" scrolling="yes" allowtransparency="true" style="width: 100%;  min-height: 550px; overflow: hidden; position: relative;" src="<?php echo $this->base_url; ?>/booking/step1?token=<?php echo $token; ?>&sid=6401" onload="initResizer();"></iframe><script src="<?php echo $this->base_url; ?>/js/iframeResizer.js"></script><script>var initResizer = function() { iframes = iFrameResize({}, "#widget-inline-embed"); }</script>
            </div>
        <?php
    }

    protected function _viewShowForm($status, $email, $token)
    {
        ?>
        <div id="nabooki_link_account_container">
            <h2>Your Nabooki account is currently not linked. Please link your account below.</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="nabooki_email">Your Nabooki email address</label>
                        </th>
                        <td>
                            <input name="nabooki_email" type="text" id="nabooki_email" size="16" value="<?php echo $email; ?>" class="regular-text">
                            <p class="description" id="nabooki_email_description">Enter your Nabooki account email address.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="nabooki_password">Your Nabooki password</label>
                        </th>
                        <td>
                            <input name="nabooki_password" type="password" id="nabooki_password" size="16" value="" class="regular-text">
                            <p class="description" id="nabooki_password_description">Enter your Nabooki account password to link your account.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">&nbsp;</th>
                        <td>
                           <p class="submit">
                            <span id="btn_nabooki_link_account" class="button button-primary">Link Account</span>
                           </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="nabooki_account_link_error" style="display:none;">
            <h2>There was an error linking your Nabooki account. Please try again.</h2>
        </div>
        <?php
    }

    protected function _viewUnlinkAccount($status, $email, $token)
    {
        ?>
        <div id="nabooki_unlink_account_container">
            <h2>Your Nabooki account is currently linked.</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row">
                            <label for="nabooki_email">Your Nabooki email address</label>
                        </th>
                        <td>
                            <input disabled name="nabooki_email" type="text" id="nabooki_email" size="16" value="<?php echo $email; ?>" class="regular-text">
                            <p class="description" id="nabooki_email_description">The Nabooki account that is currently linked.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">&nbsp;</th>
                        <td>
                           <p class="submit">
                            <span id="btn_nabooki_unlink_account" class="button button-primary">Unlink Account</span>
                           </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div id="nabooki_account_unlink_error" style="display:none;">
            <h2>There was an error unlinking your Nabooki account. Please try again.</h2>
        </div>
        <?php
    }

    protected function _getServices($token)
    {
        try {

            // Prepare POST
            $url = $this->base_url . '/link/wordpress/services';

            // Do POST
            $response = wp_remote_post($url, array(
                'body' => array(
                  'nabooki-token' => get_option('nabooki_token')
                )
              )
            );

            // Decode the JSON response
            $decoded_response = json_decode($response['body']);

            // Error check
            if ($decoded_response->result == 'error' || !isset($decoded_response->services)) {

                return;
            }

            // Extract services
            return array(
                'services' => (array) $decoded_response->services,
                'services_paged' => (array) $decoded_response->services_paged
            );

        } catch (\Exception $e) {

            // Return error
            return;
        }
    }
}

$nabooki_admin = new NabookiAdmin;
$nabooki_admin->run();
