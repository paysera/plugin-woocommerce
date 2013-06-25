<?php
/**
 * Plugin Name: WooCommerce Payment Gateway - Paysera
 * Plugin URI: http://paysera.com
 * Description: Accepts Paysera
 * Version: 1.6.6
 * Author: EVP International
 * Author URI: http://evp.lt
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 *
 * @package WordPress
 * @author Paysera (http://paysera.com)
 * @since 1.6.6
 */

add_action('plugins_loaded', 'paysera_init', 0);

function paysera_init() {
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    };

    define('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');

    require_once(dirname(__FILE__) . '/vendor/webtopay/libwebtopay/WebToPay.php');

    class WC_Paysera extends WC_Payment_Gateway {

        /**
         * @var WC_Logger
         */
        private $log;

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct() {
            global $woocommerce;

            $this->id           = 'paysera';
            $this->has_fields   = false;
            $this->method_title = __('Paysera', 'woocommerce');

            // Load icon
            $this->icon = apply_filters('woocommerce_paysera_icon', PLUGIN_DIR . '/paysera_white.png');
            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user set variables
            $this->title       = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->projectid   = $this->settings['projectid'];
            $this->password    = $this->settings['password'];
            $this->test        = $this->settings['test'];
            $this->debug       = $this->settings['debug'];

            // Logs
            if ($this->debug == 'yes') {
                $this->log = $woocommerce->logger();
            }

            // Actions
            add_action('init', array($this, 'check_callback_request'));
            add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
            add_action('woocommerce_thankyou_paysera', array($this, 'thankyou'));
            add_action('paysera_callback', array($this, 'payment_callback'));
        }

        public function admin_options() {
            ?>
            <h3><?php _e('Paysera', 'woothemes'); ?></h3>
            <p><?php _e('Paysera payment', 'woothemes'); ?></p>
            <table class="form-table">
                <?php
                // Generate the HTML For the settings form.
                $this->generate_settings_html();
                ?>
            </table>
        <?php
        } // End admin_options()

        function init_form_fields() {
            $this->form_fields = array(
                'enabled'     => array(
                    'title'       => __('Enable Paysera', 'woocommerce'),
                    'label'       => __('Enable Paysera payment', 'woocommerce'),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'description' => array(
                    'title'       => __('Description', 'woocommerce'),
                    'type'        => 'textarea',
                    'description' => __('This controls the description which the user sees during checkout.', 'woocommerce'),
                    'default'     => __('Pay via Paysera')
                ),
                'title'       => array(
                    'title'       => __('Title', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Payment method title that the customer will see on your website.', 'woocommerce'),
                    'default'     => __('Paysera', 'woocommerce')
                ),
                'projectid'   => array(
                    'title'       => __('Project ID', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Project id', 'woocommerce'),
                    'default'     => __('', 'woocommerce')
                ),
                'password'    => array(
                    'title'       => __('Sign', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Paysera sign password', 'woocommerce'),
                    'default'     => __('', 'woocommerce')
                ),
                'test'        => array(
                    'title'       => __('Test', 'woocommerce'),
                    'type'        => 'checkbox',
                    'label'       => __('Enable test mode', 'woocommerce'),
                    'default'     => 'yes',
                    'description' => __('Enable this to accept test payments', 'woocommerce'),
                ),
                'debug'       => array(
                    'title'       => __('Debug', 'woocommerce'),
                    'type'        => 'checkbox',
                    'label'       => __('Enable debug logs', 'woocommerce'),
                    'default'     => 'no',
                    'description' => __('Enable this to log debug data', 'woocommerce'),
                ),
            );
        }

        function thankyou() {
            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }
        }

        //Redirect to payment
        function process_payment($order_id) {
            global $woocommerce;
            $order = new WC_Order($order_id);

            if ($this->log) {
                $this->log->add('paysera', 'Generating payment form for order #' . $order_id . '. Notify URL: ' . trailingslashit(home_url()) . '?payseraListener=paysera_callback');
            }

            $request = WebToPay::buildRequest(array(
                'projectid'     => $this->projectid,
                'sign_password' => $this->password,
                'orderid'       => $order->id,
                'amount'        => intval(number_format($order->get_total(), 2, '', '')),
                'currency'      => get_woocommerce_currency(),
                'country'       => $order->billing_country,
                'accepturl'     => add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('thanks')))),
                'cancelurl'     => $order->get_cancel_order_url(),
                'callbackurl'   => trailingslashit(get_bloginfo('wpurl')) . '?payseraListener=payseraCallback',
                'p_firstname'   => $order->billing_first_name,
                'p_lastname'    => $order->billing_last_name,
                'p_email'       => $order->billing_email,
                'p_street'      => $order->billing_address_1,
                'p_city'        => $order->billing_city,
                'p_state'       => $order->billing_state,
                'p_zip'         => $order->billing_postcode,
                'p_countrycode' => $order->billing_country,
                'test'          => $this->test == "yes",
            ));

            $url = WebToPay::PAY_URL . '?' . http_build_query($request);
            $url = preg_replace('/[\r\n]+/is', '', $url);

            return array(
                'result'   => 'success',
                'redirect' => $url,
            );
        }

        //Check callback
        function check_callback_request() {
            if (is_admin()) {
                return;
            }

            if (isset($_REQUEST['payseraListener']) && $_REQUEST['payseraListener'] == 'payseraCallback') {
                @ob_clean();

                header('HTTP/1.1 200 OK');

                do_action('paysera_callback', $_REQUEST);
            }
        }

        /**
         *
         *
         * @param array $request
         *
         */
        function payment_callback($request) {
            global $woocommerce;

            try {
                $response = WebToPay::checkResponse($_REQUEST, array(
                    'projectid'     => $this->projectid,
                    'sign_password' => $this->password,
                ));


                if ($response['status'] == 1) {
                    $order = new WC_Order($response['orderid']);

                    if ((intval(number_format($order->get_total(), 2, '', '')) > $response['amount'])) {
                        if ($this->log) {
                            $this->log->add('paysera', 'Order #' . $order->id . ' Amounts do no match. ' . (intval(number_format($order->get_total(), 2, '', '')) . '!=' . $response['amount']));
                        }

                        throw new Exception('Amounts do not match');
                    }

                    if (get_woocommerce_currency() != $response['currency']) {
                        if ($this->log) {
                            $this->log->add('paysera', 'Order #' . $order->id . ' Currencies do not match. ' . get_woocommerce_currency() . '!=' . $response['currency']);
                        }

                        throw new Exception('Currencies do not match');
                    }

                    if ($order->status !== 'completed') {
                        if ($this->log) {
                            $this->log->add('paysera', 'Order #' . $order->id . ' Callback payment completed.');
                        }

                        $order->add_order_note(__('Callback payment completed', 'woocomerce'));
                        $order->payment_complete();
                    }
                }

                echo 'OK';
            } catch (Exception $e) {
                $msg = get_class($e) . ': ' . $e->getMessage();
                if ($this->log) {
                    $this->log->add('paysera', $msg);
                }
                echo $msg;
            }

            exit();
        }
    }

    /**
     * Add the gateway to WooCommerce
     *
     * @access public
     * @param array $methods
     * @package        WooCommerce/Classes/Payment
     * @return array
     */
    function add_paysera_gateway($methods) {
        $methods[] = 'WC_Paysera';
        return $methods;
    }

    add_filter('woocommerce_payment_gateways', 'add_paysera_gateway');
}