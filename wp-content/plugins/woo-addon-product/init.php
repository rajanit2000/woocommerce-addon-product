<?php
/**
 * Plugin Name:       WooCommerce Addon Product
 * Plugin URI:        http://wpsmartplugin.com/wap
 * Description:       WooCommerce addon product plugin which is use to add additional products for base product
 * Version:           1.0
 * Author:            Rajan V
 * Author URI:        https://www.wpsmartplugin.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       wooap
 */

class WooAP
{
    private $plugin_name;
    private $version;
    public function __construct()
    {
        $this->plugin_name = 'WooCommerce Addon Product';
        $this->version     = '1.0';
        
        add_action('admin_init', array(
            $this,
            'wooap_install'
        ), 5);
        add_action('add_meta_boxes', array(
            $this,
            'add_meta_box'
        ));
        add_action('save_post', array(
            $this,
            'save'
        ));
        
        add_action('woocommerce_add_to_cart', array(
            $this,
            'wooap_add_to_cart'
        ), 10, 3);
        add_action('admin_enqueue_scripts', array(
            $this,
            'wooap_wp_admin_style'
        ));
    }
    
    /**
     * Enqueue scripts and style
     * @return NULL
     * @since 1.0
     */
    public function wooap_wp_admin_style()
    {
        wp_register_style('wap_select2_css', plugin_dir_url(__FILE__) . 'css/select2.min.css');
        wp_register_style('wap_css', plugin_dir_url(__FILE__) . 'css/main.css');
        wp_enqueue_style('wap_select2_css');
        wp_enqueue_style('wap_css');
        wp_enqueue_script('wap_select2_js', plugin_dir_url(__FILE__) . 'js/select2.min.js');
        wp_enqueue_script('wap_js', plugin_dir_url(__FILE__) . 'js/main.js', array(), '1.0.0', true);
    }
    
    /**
     * Error if WooCommerce is not installed
     * @return NULL
     * @since 1.0
     */
    public function wooap_error_notice()
    { ?>
       	<div class="error notice">
            <p><?php _e('Woocommerce Addon Product Plugin requires WooCommerce Plugin', 'wooap'); ?></p>
        </div>
        <?php
    }
    
    /**
     * Check if WooCommerce Plugin is active
     * @return NULL
     * @since 1.0
     */
    public function wooap_install()
    {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array(
                $this,
                'wooap_error_notice'
            ));
        }
    }
    
    /**
     * Add metabox for WooCommerce Products
     * @param String
     * @return NULL
     * @since 1.0
     */
    public function add_meta_box($post_type)
    {
        $post_types = array(
            'product'
        );
        if (in_array($post_type, $post_types)) {
            add_meta_box('additional_wooap_information', __('Addon Product', 'wooap'), array(
                $this,
                'wooap_meta_box_content'
            ), $post_type, 'advanced', 'high');
        }
    }
    
    /**
     * Save metabox value
     * @param  int $post_id 
     * @return NULL | int
     * @since 1.0
     */
    public function save($post_id)
    {
        if (!isset($_POST['wooap_meta_box_nonce']))
            return $post_id;
        $nonce = $_POST['wooap_meta_box_nonce'];
        if (!wp_verify_nonce($nonce, 'wooap_meta_box'))
            return $post_id;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            return $post_id;
        if ('page' == $_POST['post_type']) {
            if (!current_user_can('edit_page', $post_id))
                return $post_id;
        } else {
            if (!current_user_can('edit_post', $post_id))
                return $post_id;
        }
        if (isset($_POST['wooap'])) {
            foreach ($_POST['wooap'] as $key => $value) {
                update_post_meta($post_id, $key, serialize($value));
            }
        }
    }
    
    /**
     * Add metabox elements
     * @param  string $post 
     * @return NULL
     * @since 1.0
     */
    public function wooap_meta_box_content($post)
    {
        wp_nonce_field('wooap_meta_box', 'wooap_meta_box_nonce'); ?>
        <div class="wooap-wrap">
            <div class="wooap-left">
                <p><label for="wooap_nav"><?php _e('Choose addon product IDs?', 'wooap'); ?></label></p>
                <?php $addon = $this->get_product_addon($post->ID); 
                if(empty($addon)){
                    $addon = array();
                }
                ?>
               <select type="text" name="wooap[addon][]" id="wooap[addon]" class="wooap-select-multiple ui-corner-all" multiple="multiple">
                <option>None</option>
                <?php 
		        $args = array(
		            'post_type' => 'product',
		            'posts_per_page' => -1,
                    'post__not_in' => array(get_the_ID())
		        );
		        $loop = new WP_Query($args);
		        while ($loop->have_posts()) {
		            $loop->the_post();
		            global $product;
				?>
                <option <?php if (in_array(get_the_ID(), $addon)) { ?> selected="selected"<?php } ?> value="<?php the_ID(); ?>"><?php the_title(); ?></option>
                <?php wp_reset_query(); } ?>
               </select>
            </div>
        </div>
        <?php
    }
    
    /**
     * Condition function for check product addons 
     * @param  string $productID 
     * @return Boolean
     * @since 1.0
     */
    public function is_product_having_addon($productID)
    {
        if (get_post_meta($productID, 'addon', true) != '') {
            return true;
        }
        return false;
    }
    
    /**
     * Get product addons ID
     * @param  string $productID 
     * @return Array
     * @since 1.0
     */
    public function get_product_addon($productID)
    {
        $addon = '';
        if ($this->is_product_having_addon($productID)) {
            $addonString = get_post_meta($productID, 'addon', true);
            $addon       = unserialize($addonString);
        }
        return $addon;
    }
    
    /**
     * Addon hook 
     * @return NULL
     * @since 1.0
     */
    public function wooap_add_to_cart($cart_item_key, $productID, $quantity)
    {

        global $woocommerce;
        if ($this->is_product_having_addon($productID)) {
            $addon = $this->get_product_addon($productID);

            foreach ($addon as $key => $addonID) {
                if (sizeof(WC()->cart->get_cart()) > 0) {
                    foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
                        $_product = $values['data'];
                        if ($_product->id == $addonID)
                            $found = true;
                    }
                    if (!$found)
                        WC()->cart->add_to_cart($addonID);
                } else {
                    WC()->cart->add_to_cart($addonID);
                }
            }
        }
    }
}

$wooap = new WooAP();