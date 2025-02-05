<?php

/**
 * Plugin Name: WooCommerce Custom Shipping
 * Description: Custom shipping rates based on country, postal code and weight
 * Version: 1.0.0
 * Author: Tiran Chanuka
 * Text Domain: wc-custom-shipping
 */

if (!defined('ABSPATH')) {
    exit;
}

// Activation function
function wc_custom_shipping_activate()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        country varchar(2) NOT NULL,
        postal_code varchar(10) NOT NULL,
        min_weight decimal(10,2) NOT NULL,
        max_weight decimal(10,2) NOT NULL,
        rate decimal(10,2) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// Register activation hook
register_activation_hook(__FILE__, 'wc_custom_shipping_activate');

function wc_custom_shipping_init()
{
    // Check if WooCommerce is active
    if (!class_exists('WC_Shipping_Method')) {
        return;
    }

    class WC_Custom_Shipping_Method extends WC_Shipping_Method
    {
        public function __construct($instance_id = 0)
        {
            parent::__construct($instance_id);

            $this->id = 'custom_shipping';
            $this->instance_id = absint($instance_id);
            $this->title = 'Custom Shipping';
            $this->method_title = 'Custom Shipping';
            $this->method_description = 'Custom shipping with rates based on country, postal code and weight';
            $this->supports = array(
                'shipping-zones',
                'instance-settings',
            );

            $this->init();
        }

        public function init()
        {
            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->enabled = $this->get_option('enabled');

            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        public function init_form_fields()
        {
            $this->instance_form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'wc-custom-shipping'),
                    'type' => 'checkbox',
                    'label' => __('Enable this shipping method', 'wc-custom-shipping'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'wc-custom-shipping'),
                    'type' => 'text',
                    'description' => __('This controls the title customers see during checkout.', 'wc-custom-shipping'),
                    'default' => __('Custom Shipping', 'wc-custom-shipping'),
                    'desc_tip' => true
                ),
                'shipping_rates' => array(
                    'title' => __('Shipping Rates', 'wc-custom-shipping'),
                    'type' => 'title',
                    'description' => ''
                )
            );
        }

        public function admin_options()
        {
?>
            <h2><?php echo esc_html($this->method_title); ?></h2>
            <p><?php echo esc_html($this->method_description); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table>
        <?php
            $this->generate_rates_table();
        }

        private function generate_rates_table()
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';
            $rates = $wpdb->get_results("SELECT * FROM $table_name ORDER BY id ASC", ARRAY_A);
        ?>
            <h3><?php _e('Shipping Rates', 'wc-custom-shipping'); ?></h3>
            <table class="widefat" id="shipping_rates_table">
                <thead>
                    <tr>
                        <th><?php _e('Country', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Postal Code', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Min Weight (kg)', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Max Weight (kg)', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('Rate', 'wc-custom-shipping'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($rates) : foreach ($rates as $rate) : ?>
                            <tr>
                                <td>
                                    <select name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][country]">
                                        <?php foreach (WC()->countries->get_countries() as $code => $name) : ?>
                                            <option value="<?php echo esc_attr($code); ?>" <?php selected($rate['country'], $code); ?>>
                                                <?php echo esc_html($name); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][postal_code]"
                                        value="<?php echo esc_attr($rate['postal_code']); ?>" placeholder="* for all">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][min_weight]"
                                        value="<?php echo esc_attr($rate['min_weight']); ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][max_weight]"
                                        value="<?php echo esc_attr($rate['max_weight']); ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][rate]"
                                        value="<?php echo esc_attr($rate['rate']); ?>">
                                </td>
                                <td>
                                    <button type="button" class="button remove_rate"><?php _e('Remove', 'wc-custom-shipping'); ?></button>
                                </td>
                            </tr>
                    <?php endforeach;
                    endif; ?>
                    <tr class="new-rate">
                        <td>
                            <select name="shipping_rate[new][country]">
                                <option value=""><?php _e('Select country', 'wc-custom-shipping'); ?></option>
                                <?php foreach (WC()->countries->get_countries() as $code => $name) : ?>
                                    <option value="<?php echo esc_attr($code); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="shipping_rate[new][postal_code]" placeholder="* for all"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][min_weight]" value="0"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][max_weight]" value="999999"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][rate]" value="0"></td>
                        <td><button type="button" class="button add_rate"><?php _e('Add Rate', 'wc-custom-shipping'); ?></button></td>
                    </tr>
                </tbody>
            </table>
<?php
        }

        public function process_admin_options()
        {
            parent::process_admin_options();

            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';

            if (isset($_POST['shipping_rate'])) {
                $rates = wc_clean($_POST['shipping_rate']);

                foreach ($rates as $id => $rate) {
                    if ($id === 'new' && !empty($rate['country'])) {
                        $wpdb->insert(
                            $table_name,
                            array(
                                'country' => $rate['country'],
                                'postal_code' => $rate['postal_code'],
                                'min_weight' => $rate['min_weight'],
                                'max_weight' => $rate['max_weight'],
                                'rate' => $rate['rate']
                            ),
                            array('%s', '%s', '%f', '%f', '%f')
                        );
                    } elseif (is_numeric($id)) {
                        $wpdb->update(
                            $table_name,
                            array(
                                'country' => $rate['country'],
                                'postal_code' => $rate['postal_code'],
                                'min_weight' => $rate['min_weight'],
                                'max_weight' => $rate['max_weight'],
                                'rate' => $rate['rate']
                            ),
                            array('id' => $id),
                            array('%s', '%s', '%f', '%f', '%f'),
                            array('%d')
                        );
                    }
                }
            }
        }

        public function calculate_shipping($package = array())
        {
            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';

            $weight = 0;
            $country = $package['destination']['country'];
            $postcode = $package['destination']['postcode'];

            foreach ($package['contents'] as $item) {
                if ($item['data']->get_weight()) {
                    $weight += floatval($item['data']->get_weight()) * $item['quantity'];
                }
            }

            $rate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE country = %s 
                AND (postal_code = '*' OR postal_code = %s)
                AND %f >= min_weight 
                AND %f <= max_weight
                LIMIT 1",
                $country,
                $postcode,
                $weight,
                $weight
            ));

            if ($rate) {
                $this->add_rate(array(
                    'id' => $this->id . $this->instance_id,
                    'label' => $this->title,
                    'cost' => $rate->rate,
                    'calc_tax' => 'per_order'
                ));
            }
        }
    }

    // Add the shipping method
    function add_wc_custom_shipping_method($methods)
    {
        $methods['custom_shipping'] = 'WC_Custom_Shipping_Method';
        return $methods;
    }
    add_filter('woocommerce_shipping_methods', 'add_wc_custom_shipping_method');
}

// Initialize the plugin
add_action('plugins_loaded', 'wc_custom_shipping_init');
