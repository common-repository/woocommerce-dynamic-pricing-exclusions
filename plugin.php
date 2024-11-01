<?php
/*
Plugin Name: WooCommerce Dynamic Pricing Product Exclusions
Description: WooCommerce Dynamic Pricing Product Exclusions allows you to exclude individual products from the dynamic pricing calculations provided by the WooCommerce Dynamic Pricing plugin.
Version: 1.0.0
Author: Nathan Franklin
Author URI: http://www.nathanfranklin.com.au/
Requires at least: 3.3
Tested up to: 3.5.1
Text Domain: wcdppe
Domain Path: /lang/

Copyright: © 2014 Nathan Franklin
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

class wc_dynamic_pricing_product_exclusions {

	function __construct() {
		add_action("admin_init", array($this, "admin_init"));

		add_filter("woocommerce_dynamic_pricing_process_product_discounts", array($this, "dynamic_pricing_process_product_discounts"), 10, 4);
	}

	function admin_init() {
		// check to make sure WooCommerce and WooCommerce Dyanmic Pricing is installed.
		if(!is_plugin_active('woocommerce/woocommerce.php') || !is_plugin_active('woocommerce-dynamic-pricing/woocommerce-dynamic-pricing.php')) {
			// this plugin can't run
			add_action('admin_notices', array($this, 'show_incompatible_notice'));
		} else {
			if(class_exists("WC_Dynamic_Pricing")) {
				// both these plugins are installed and activated
				// wire up the admin functions
				add_action("add_meta_boxes", array($this, "add_meta_boxes"), 10, 2);
				add_action("save_post", array($this, "save_post"), 10, 2);
			}
		}
	}

	function show_incompatible_notice() {
		global $pagenow;
	    if($pagenow == 'plugins.php') {
		    echo '<div class="error"><p>' . __('The WooCommerce Dynamic Pricing Product Exclusions plugin requires WooCommerce and the premium WooCommerce Dynamic Pricing plugins to be installed and activated.', 'wcdppe') . '</p></div>';
	    }

	}

	function dynamic_pricing_process_product_discounts($eligible, $product, $module, $inst) {
		$disabled = get_post_meta($product->id, "_dynamic_pricing_disabled", true);
		$disabled = maybe_unserialize($disabled);
		$disabled = (empty($disabled) || !is_array($disabled) ? array() : $disabled);
		if(array_key_exists($module, $disabled) && $disabled[$module] == "1") {
			return false;
		}
		return $eligible;
	}

	function add_meta_boxes($post_type, $post) {
		if($post_type == "product") {
			add_meta_box('cs-dy-disable-pricing', __('Disable Dynamic Pricing', 'wcdppe'), array($this, 'meta_box_disable_dynamic_pricing'), 'product', 'side', 'default');
		}
	}

	function meta_box_disable_dynamic_pricing($post) {
		$disabled = get_post_meta($post->ID, "_dynamic_pricing_disabled", true);
		$disabled = maybe_unserialize($disabled);
		$disabled = (empty($disabled) || !is_array($disabled) ? array() : $disabled);

//      is not loaded during admin so we can't get access to the module dynamically.
//      instead we will need to add a filter here to allow people to 'add' additional modules to be excluded is necessary.
//		$inst = WC_Dynamic_Pricing::instance();
//		$modules = $inst->modules;

		$modules = $this->get_modules();
		?>
		<input type="hidden" name="disabled_dynamic_nonce" value="<?php print wp_create_nonce("disabled_dynamic"); ?>" />
		<p><?php print __("Select the modules to bypass when calculating dynamic pricing.", 'wcdppe'); ?></p>
		<?php
		foreach($modules as $module_id => $module_name) {
			?>
			<label for="disabled-dynamic-<?php print $module_id; ?>"><input type="checkbox" id="disabled-dynamic-<?php print $module_id; ?>" name="disabled_dynamic[<?php print $module_id; ?>]" value="1" <?php print (!empty($disabled) && $disabled[$module_id] == "1" ? "checked" : ""); ?> /> <?php print $module_name; ?></label><br/>
			<?php
		}
	}

	function save_post($post_id, $post) {
		$nonce = $_POST["disabled_dynamic_nonce"];
		if($post->post_type == "product" && wp_verify_nonce($nonce, "disabled_dynamic")) {
			$data = $_POST["disabled_dynamic"];
			$data = (empty($data) || !is_array($data) ? array() : $data);
			foreach($data as $module_id => $value) {
				if(empty($value) || !is_numeric($value)) {
					unset($data[$module_id]);
					continue;
				}
			}
			// the value will be cleaned by update_post_meta so there is no need to clean it.
			update_post_meta($post_id, "_dynamic_pricing_disabled", $data);
		}
	}

	private function get_modules() {
		$modules = array(
			"advanced_product" => __("Advanced Product", 'wcdppe'),
			"advanced_category" => __("Advanced Category", 'wcdppe'),
			"advanced_totals" => __("Advanced Totals", 'wcdppe'),
			"simple_product" => __("Simple Product", 'wcdppe'),
			"simple_category" => __("Simple Category", 'wcdppe'),
			"simple_membership" => __("Simple Membership", 'wcdppe')
		);

		$modules = apply_filters("wcdppe_additional_modules", $modules);
		$modules = (empty($modules) || !is_array($modules) ? array() : $modules);
		return $modules;
	}

}

new wc_dynamic_pricing_product_exclusions();