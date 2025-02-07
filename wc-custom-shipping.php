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

// Add JavaScript to handle add/remove buttons
function wc_custom_shipping_admin_scripts()
{
    if (
        isset($_GET['page']) && $_GET['page'] === 'wc-settings'
        && isset($_GET['tab']) && $_GET['tab'] === 'shipping'
    ) {
?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                // Handle Add Rate button
                $(document).on('click', '.add_rate', function() {
                    var $row = $(this).closest('tr');
                    var $clone = $row.clone();

                    // Clear values in the cloned row
                    $clone.find('input').val('');
                    $clone.find('select').prop('selectedIndex', 0);

                    // Insert before the "new rate" row
                    $row.before($clone);
                });

                // Handle Remove Rate button
                $(document).on('click', '.remove_rate', function() {
                    $(this).closest('tr').remove();
                });
            });
        </script>
        <?php
    }
}
add_action('admin_footer', 'wc_custom_shipping_admin_scripts');

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
         standard_fee decimal(10,2) NOT NULL,
        one_day_fee decimal(10,2) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Check if we need to migrate existing data
    $columns = $wpdb->get_col("SHOW COLUMNS FROM $table_name");
    if (in_array('rate', $columns)) {
        // Migrate existing data
        $wpdb->query("ALTER TABLE $table_name 
                     ADD COLUMN standard_fee decimal(10,2) NOT NULL DEFAULT 0,
                     ADD COLUMN one_day_fee decimal(10,2) NOT NULL DEFAULT 0");
        $wpdb->query("UPDATE $table_name SET standard_fee = rate, one_day_fee = rate * 1.5");
        $wpdb->query("ALTER TABLE $table_name DROP COLUMN rate");
    }
}

register_activation_hook(__FILE__, 'wc_custom_shipping_activate');

function wc_custom_shipping_init()
{
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
                        <th><?php _e('Standard Fee', 'wc-custom-shipping'); ?></th>
                        <th><?php _e('One Day Fee', 'wc-custom-shipping'); ?></th>
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
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][standard_fee]"
                                        value="<?php echo esc_attr($rate['standard_fee']); ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="shipping_rate[<?php echo esc_attr($rate['id']); ?>][one_day_fee]"
                                        value="<?php echo esc_attr($rate['one_day_fee']); ?>">
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
                        <td><input type="number" step="0.01" name="shipping_rate[new][min_weight]" placeholder="0"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][max_weight]" placeholder="999999"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][standard_fee]" value="0"></td>
                        <td><input type="number" step="0.01" name="shipping_rate[new][one_day_fee]" value="0"></td>
                        <td><button type="button" class="button add_rate"><?php _e('Add Rate', 'wc-custom-shipping'); ?></button></td>
                    </tr>
                </tbody>
            </table>
            <input type="hidden" name="deleted_rates" value="">
<?php
        }


        public function process_admin_options()
        {
            parent::process_admin_options();

            global $wpdb;
            $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';

            if (isset($_POST['shipping_rate'])) {
                $rates = wc_clean($_POST['shipping_rate']);

                // Process deletions first
                if (isset($_POST['deleted_rates'])) {
                    $deleted_rates = array_map('intval', explode(',', $_POST['deleted_rates']));
                    foreach ($deleted_rates as $rate_id) {
                        $wpdb->delete($table_name, array('id' => $rate_id), array('%d'));
                    }
                }

                // Then process updates and additions
                foreach ($rates as $id => $rate) {
                    // Skip empty rows
                    if (empty($rate['country']) && $id !== 'new') {
                        continue;
                    }

                    // Normalize and validate the data
                    $rate_data = array(
                        'country' => $rate['country'],
                        'postal_code' => !empty($rate['postal_code']) ? strtoupper(wc_normalize_postcode($rate['postal_code'])) : '*',
                        'min_weight' => floatval($rate['min_weight']),
                        'max_weight' => floatval($rate['max_weight']),
                        'standard_fee' => floatval($rate['standard_fee']),
                        'one_day_fee' => floatval($rate['one_day_fee'])
                    );

                    if ($id === 'new' && !empty($rate['country'])) {
                        $wpdb->insert(
                            $table_name,
                            $rate_data,
                            array('%s', '%s', '%f', '%f', '%f', '%f')
                        );
                    } elseif (is_numeric($id)) {
                        $wpdb->update(
                            $table_name,
                            $rate_data,
                            array('id' => $id),
                            array('%s', '%s', '%f', '%f', '%f', '%f'),
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
            $postcode = strtoupper(wc_normalize_postcode($package['destination']['postcode']));

            // Calculate total weight
            foreach ($package['contents'] as $item) {
                if ($item['data']->get_weight()) {
                    $weight += floatval($item['data']->get_weight()) * $item['quantity'];
                }
            }

            // Debug log
            error_log("Calculating shipping for: Country: $country, Postcode: $postcode, Weight: $weight");

            // Modified query to first try exact postal code match, then fallback to wildcard
            $rate = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE country = %s 
                AND (
                    postal_code = %s 
                    OR postal_code = '*' 
                    OR postal_code = ''
                )
                AND %f >= min_weight 
                AND %f <= max_weight
                ORDER BY 
                    CASE 
                        WHEN postal_code = %s THEN 1
                        WHEN postal_code = '*' OR postal_code = '' THEN 2
                    END
                LIMIT 1",
                $country,
                $postcode,
                $weight,
                $weight,
                $postcode
            ));

            // Debug log
            error_log("Found rate: " . print_r($rate, true));

            if ($rate) {
                $this->add_rate(array(
                    'id' => $this->id . $this->instance_id . '_standard',
                    'label' => $this->title . ' - Standard Delivery',
                    'cost' => $rate->standard_fee,
                    'calc_tax' => 'per_order'
                ));

                // Add one day shipping rate
                $this->add_rate(array(
                    'id' => $this->id . $this->instance_id . '_one_day',
                    'label' => $this->title . ' - One Day Delivery',
                    'cost' => $rate->one_day_fee,
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

add_action('plugins_loaded', 'wc_custom_shipping_init');

// Add AJAX handling for rate deletion
add_action('wp_ajax_delete_shipping_rate', 'handle_delete_shipping_rate');
function handle_delete_shipping_rate()
{
    if (!current_user_can('manage_woocommerce')) {
        wp_die(-1);
    }

    $rate_id = isset($_POST['rate_id']) ? absint($_POST['rate_id']) : 0;

    if ($rate_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wc_custom_shipping_rates';
        $wpdb->delete($table_name, array('id' => $rate_id), array('%d'));
    }

    wp_die();
}
