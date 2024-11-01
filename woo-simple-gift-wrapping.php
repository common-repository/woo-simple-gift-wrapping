<?php

/*
Plugin Name: Woo Gift Wrapping
Plugin URI: http://wordpress-plugin.lnits.net/wo-gift-wrapping
Description: Plugin help you add a gift card to checkout and can choose custom fee.
Version: 1.1
Author: Ly Ngoc Quoc Lam
Author URI: http://lnits.net
License: Lamlnq 2015
*/
defined('ABSPATH') or die('No script kiddies please!');

class NA_WC_Gift_Wrapping {
	public $id = 'na_wo_gw';
	public $feeId = 'Gift Wrapping';

	public function __construct() {
		// Enable sessions
		add_action('init', array($this,'register_my_session'));
		/**
		 * Check if WooCommerce is active
		 */
		if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
			// Start plugin
			if ( is_admin() ) {
				add_filter('woocommerce_get_settings_products', array($this, 'settings'), 10, 2);
				add_filter('woocommerce_get_sections_products', array($this, 'section'));
				add_action('woocommerce_update_options_products',array($this,'updateSettings'));
			}
			// Add ajax and all other js, css here
			if ( get_option( 'na_gw_enable' ) ) {
				add_action( 'wp_enqueue_scripts', array ( $this, 'loadScripts' ) );
				//add_action('admin_footer',array($this,'ajax_footer_js'));
				//add_filter( 'woocommerce_checkout_fields' , array($this, 'checkout_add_field') );
				$this->setCheckOutFormPosition();


				//add_action('woocommerce_checkout_update_order_meta', array($this, 'checkout_field_progress'));
				//
				add_action( 'woocommerce_cart_calculate_fees', array($this,'calFee') );
				add_action('wp_ajax_woocommerce_add_gift_box', array($this,'addFee'), 10);
				add_action('wp_ajax_nopriv_woocommerce_add_gift_box', array($this,'addFee'), 10);
			}

		}
	}
	function register_my_session()
	{
		if( !session_id() )
		{
			session_start();
		}
	}

	public function getSessionFees() {
		return $_SESSION[ $this->id . '_cart_fees' ];
	}
	public function checkFeeExists() {
		return isset( $_SESSION[ $this->id . '_cart_fees' ]);
	}
	public function calFee() {
		if ($this->checkFeeExists() ) {
			WC()->cart->fees = $_SESSION[ $this->id . '_cart_fees' ];
		}
	}
	public function addFee() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) )
			return;

		// AJAX REQUEST
		$response = array('result'=>false);
		// Do State
		if ( isset( $_POST['state'] ) ) {
			$state = $_POST['state'];
			$wooCart = WC()->cart;
			if ( $state == 'REMOVE' ) {
				$fees = $wooCart->get_fees();
                $feeId = get_option('na_gw_fee_label');
				foreach ( $fees as $index => $fee ) {
					if ( $fee->id == sanitize_title($feeId) ) {
						unset($fees[$index]);
					}
				}
				$wooCart->fees = $fees;
			} elseif($state == 'ADD') {
                $feeId = get_option('na_gw_fee_label');
				$wooCart->add_fee( $feeId, get_option('na_gw_fee'));
				$notificationTemplate = @file_get_contents(dirname(__FILE__).DIRECTORY_SEPARATOR.'notification.html');
				if (is_string($notificationTemplate)) {
					$response['result'] = true;
				}
				$link = get_term_link((int)get_option('na_gw_category'),'product_cat');
				$message = str_replace('{fee}', get_option('na_gw_fee'), get_option('na_gw_notification'));
				if ( is_wp_error( $link ) ) {
					$link = '';
					$message = __('Please check setting for gift card category');
				}
				// Replace notification template
				$replacement = array(
					'{message}'        => $message,
					'{link}'           => $link,
					'{yes}'            => __('Yes','woocommerce'),
					'{no}'             => __('No','woocommerce'),
				);
				$response['data'] = str_replace(array_keys($replacement), array_values($replacement), $notificationTemplate);
				echo json_encode($response);
			}
			$_SESSION[$this->id.'_cart_fees'] = $wooCart->fees;
		}
		die;
	}

	public function loadScripts() {
		wp_enqueue_script('na-woo-gift-wrapping',
			plugins_url( 'js/wo-gift-wrapping.min.js', __FILE__ ),
			array( 'jquery' )
		);
		wp_enqueue_style( 'na-woo-gift-wrapping', plugins_url( 'css/woo-gift-wrapping.min.css', __FILE__ ));
		// use thinkbox
		add_thickbox();
	}
	/** Add new section to product tab
	 * @param $sections
	 *
	 * @return mixed
	 */
	public function section($sections)
	{
		$sections[$this->id] = __('NA Gift Wrapping', 'na-woo');
		return $sections;
	}
	/** Add all settings field to section
	 * @param $settings
	 * @param $currentSection
	 *
	 * @return array
	 */
	public function settings($settings, $currentSection)
	{
		if ($currentSection == $this->id) {
			$product_categories = get_terms( 'product_cat', array('hide_empty'=>false));
			$options = array();
			foreach ( $product_categories as $category ) {
				$options[$category->term_id] = $category->name;
			}
			$settingNA = array();
			// Add Title to the Settings
			$settingNA[] = array(
				'name' => __('Gift Wrapping'),
				'type' => 'title',
				'desc' => __('The following options are used to configure NA WooCommerce Gift Wrapping'),
				'desc_tip'=>true,
				'id' => $this->id
			);
			$settingNA[] = array(
				'name'  => __('Enable Gift Wrapping'),
				'type'  => 'checkbox',
				'id'    => 'na_gw_enable'
			);
			$settingNA[] = array(
				'name'  => __('Label in checkout'),
				'type'  => 'text',
				'id'    => 'na_gw_label',
				'css'   => 'min-width:300px',
				'require'=> true
			);
			$settingNA[] = array(
				'name'  => __('Label section'),
				'type'  => 'text',
				'id'    => 'na_gw_checkout_label',
				'css'   => 'min-width:300px',
				'require'=> true
			);
			$settingNA[] = array(
				'name'  => __('Gift card category Link'),
				'type'  => 'text',
				'id'    => 'na_gw_link_label',
				'css'   => 'min-width:300px',
				'desc'  => __('Link to the category, can use html tag [strong][/strong]'),
				'desc_tip' => true
			);
			$settingNA[] = array(
				'name'  => __('Fee'),
				'type'  => 'text',
				'id'    => 'na_gw_fee',
				'require'=> true
			);
            $settingNA[] = array(
                'name'  => __('Label in Overview and Order'),
                'type'  => 'text',
                'id'    => 'na_gw_fee_label',
                'require'=> true,
                'default'   => $this->feeId
            );
			$settingNA[] = array(
				'name'  => __('Gift form location'),
				'type'  => 'select',
				'id'    => 'na_gw_location',
				'options'   => array(
					'shipping_before'   => 'Before Shipping Form',
					'shipping_after'    => 'After Shipping Form',
					'billing_before'    => 'Before Billing Form',
					'billing_after'     => 'Before Billing Form',
					'order_before'      => 'Before Order Notes',
					'order_after'       => 'After Order Notes',
					'before_form'       => 'Before Checkout Form'
				),
				'desc'      => __('Gift Wrapping checkbox position'),
				'desc_tip'  =>true
			);
			$settingNA[] = array(
				'name'  => __('Category of Gift Card'),
				'type'  => 'select',
				'id'    => 'na_gw_category',
				'options'   => $options,
				'require'=> true
			);
			$settingNA[] = array(
				'name'  => __('Note Text'),
				'type'  => 'textarea',
				'id'    => 'na_gw_notification',
				'css'   => 'width:100%; height: 75px;',
				'desc'  => __('This text will show below checkbox. Please input {fee} if you want to show fee'),
				'desc_tip'  => true,
				'require'=> true
			);
			$settingNA[] = array('type'=>'sectionend','id'=>$this->id);
			return $settingNA;
		} else {
			return $settings;
		}
	}

	public function updateSettings() {
		$label = $_POST['na_gw_link_label'];
		// TODO filter
	}

	public function setCheckOutFormPosition() {
		$option = get_option('na_gw_location');
		switch ( $option ) {
			case 'shipping_before':
				add_action( 'woocommerce_before_checkout_shipping_form', array($this, 'checkout_add_field'));
				break;
			case 'shipping_after':
				add_action( 'woocommerce_after_checkout_shipping_form', array($this, 'checkout_add_field'));
				break;
			case 'billing_before':
				add_action( 'woocommerce_before_checkout_billing_form', array($this, 'checkout_add_field'));
				break;
			case 'billing_after':
				add_action( 'woocommerce_after_checkout_billing_form', array($this, 'checkout_add_field'));
				break;
			case 'order_before':
				add_action( 'woocommerce_before_order_notes', array($this, 'checkout_add_field'));
				break;
			case 'order_after':
				add_action( 'woocommerce_after_order_notes', array($this, 'checkout_add_field'));
				break;
			default:
				add_action( 'woocommerce_before_checkout_form', array($this, 'checkout_add_field'));
				break;
		}
	}
	public function checkout_add_field() {
		if ( get_option( 'na_gw_enable' ) ) {
			$checked = 0;
			if ( $this->checkFeeExists() && count( $this->getSessionFees() ) > 0 ) {
				$checked = 1;
			}

			add_filter('woocommerce_form_field_checkbox', array($this,'checkout_field_link'), 4, 15);
			echo '<div id="na-woo-fields"><h3>'.get_option('na_gw_checkout_label').'</h3>';
			woocommerce_form_field( 'na_gift_wrapping_checked', array(
				'type'          => 'checkbox',
				'class'         => array('na-gift-wrapping-check'),
				'label'         => get_option('na_gw_label')
			), $checked);
			$notification = str_replace('{fee}', get_option('na_gw_fee'), get_option('na_gw_notification'));
			echo '<p class="na-gift-wrapping-note">';
				echo '<span>'.__('Note').':</span> ';
				echo $notification;
			echo '</p>';
			echo '</div>';
		}
	}

	public function checkout_field_link($field, $key, $args, $value) {
		$link = get_term_link((int)get_option('na_gw_category'),'product_cat');
		//$notification = str_replace('{fee}', get_option('na_gw_fee'), get_option('na_gw_notification'));
		if ( is_wp_error( $link ) ) {
			$link = '';
			//$notification = __('Please check setting for gift card category');
		}
		if (!is_wp_error( $link ) ) {
			$field = substr($field,0,-4);
			$field .= '<span class="na-gw-link-separator">|</span>';
			$labelText = get_option('na_gw_link_label') ;
			$labelText = str_replace(array('[strong]','[/strong]'), array('<strong>','</strong>') , $labelText);
			$field .= '<a class="na-gw-link" href="' . $link . '">' . $labelText . '</a>';
			$field .= '</p>';
		}
		return $field;
	}

	public function checkout_field_progress( $order_id ) {
		if ( ! empty( $_POST['na_gift_wrapping_checked'] ) ) {
			update_post_meta( $order_id, $this->id, 1);
		} else {
			update_post_meta($order_id,$this->id, 0);
		}
	}
}

if ( ! function_exists('NA_WC_Gift_Wrapping_Start') ) {
	function NA_WC_Gift_Wrapping_Start() {
		new NA_WC_Gift_Wrapping();
	}
}
NA_WC_Gift_Wrapping_Start();