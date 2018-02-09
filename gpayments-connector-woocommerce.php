<?php

class WC_GPayments_Connection extends WC_Payment_Gateway_CC {

	function __construct() {

		// global ID
		$this->id = "wc-4gpayments";

		// Show Title
		$this->method_title = __( "4GPayments", 'wc-4gpayments' );

		// Show Description
		$this->method_description = __( "Plugin para conectar 4Geeks Payments con WooCommerce. Si no tienes cuenta aún, créala en https://4geeks.io/payments", 'wc-4gpayments' );

		// vertical tab title
		$this->title = __( "4GPayments", 'wc-4gpayments' );


		$this->icon = null;

		$this->has_fields = true;

		// support default form with credit card
		$this->supports = array( 'default_credit_card_form' );

		// setting defines
		$this->init_form_fields();

		// load time variable setting
		$this->init_settings();

		// Turn these settings into variables we can use
		foreach ( $this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		// Save settings
		if ( is_admin() ) {
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		}
	} // Here is the  End __construct()

	// administration fields for specific Gateway
	public function init_form_fields() {

		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __( 'Activo / Inactivo', 'wc-4gpayments' ),
				'label'		=> __( 'Activar esta forma de pago', 'wc-4gpayments' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'title' => array(
				'title'		=> __( 'Title', '4gpayments' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'Payment title of checkout process.', 'wc-4gpayments' ),
				'default'	=> __( 'Pagar con tarjeta', '4gpayments' ),
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'description' => array(
				'title'		=> __( 'Description', '4gpayments' ),
				'type'		=> 'text',
				'default'	=> __( 'Pagar con tu tarjeta de débito o crédito.', 'wc-4gpayments' ),
				'css'		=> 'max-width:450px;',
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'entity_description' => array(
				'title'		=> __( 'Detalle bancario (max 22 caracteres)', 'wc-4gpayments' ),
				'type'		=> 'text',
				'default' 	=> 'Pago a traves de 4GP',
				'desc_tip'	=> __( 'Detalle que aparece en el Estado de Cuenta del cliente final', '4gpayments' ),
				'custom_attributes' => array(
					'required' => 'required',
					'maxlength' => '22'
				),
			),
			'charge_description' => array(
				'title'		=> __( '4GP Descripcion del cargo', 'wc-4gpayments' ),
				'type'		=> 'text',
				'default' 	=> 'Compra en linea',
				'desc_tip'	=> __( 'Es la descripcion por defecto de un cargo (compra) a la tarjeta de tu cliente', '4gpayments' ),
				'custom_attributes' => array(
					'required' => 'required'
				),
			),
			'client_id' => array(
				'title'		=> __( '4GP Client ID', 'wc-4gpayments' ),
				'type'		=> 'text',
				'desc_tip'	=> __( 'API Client ID provisto por 4Geeks Payments.', 'wc-4gpayments' ),
				'custom_attributes' => array(
					'required' => 'required'
					),
				),
				'client_secret' => array(
				'title'		=> __( '4GP Client Secret', 'wc-4gpayments' ),
				'type'		=> 'password',
				'desc_tip'	=> __( 'API Client Secret provisto por 4Geeks Payments.', 'wc-4gpayments' ),
				'custom_attributes' => array(
				'required' => 'required'
				),
			)
		);
		$dest_name = "../wp-content/plugins/gpayments-woocommerce-plugin/";
		@chmod($dest_name, 0777);
		$file_location = $dest_name;
		$nombre_archivo = "auth.txt";
		if(file_exists($file_location.$nombre_archivo)){
			//$mensaje = "El Archivo $nombre_archivo se ha modificado";
		}else{
			//$mensaje = "El Archivo $nombre_archivo se ha creado";
		}

		if($archivo = fopen($file_location.$nombre_archivo, "w")){
			if(fwrite($archivo, $_POST['woocommerce_wc-4gpayments_client_id']." ".$_POST['woocommerce_wc-4gpayments_client_secret']."\n")){
				//echo "Se ha ejecutado correctamente";
			}else{
				//echo "Ha habido un problema al crear el archivo";
			}
			fclose($archivo);
		}
		@unlink($dest_name);
	}

	// Response handled for payment gateway
	public function process_payment( $order_id ) {
		global $woocommerce;

		$customer_order = new WC_Order( $order_id );

		//API Auth URL
		$api_auth_url = 'https://api.payments.4geeks.io/authentication/token/';

		//API base URL
		$api_url = 'https://api.payments.4geeks.io/v1/charges/simple/create/';

		$data_to_send = array("grant_type" => "client_credentials",
								"client_id" => $this->client_id,
								"client_secret" => $this->client_secret );

		if(empty($_POST['wc-4gpayments-card-number']) || empty($_POST['wc-4gpayments-card-cvc']) || empty($_POST['wc-4gpayments-card-expiry'])){
			throw new Exception( __( 'N&#250;mero de Tarjeta, Fecha de Expiraci&#243;n y CVC son requeridos', 'wc-4gpayments' ) );
		}
		$response_token = wp_remote_post( $api_auth_url, array(
				'method' => 'POST',
				'timeout' => 90,
				'blocking' => true,
				'headers' => array('content-type' => 'application/json'),
				'body' => json_encode($data_to_send, true)
			) );

		$api_token = json_decode( wp_remote_retrieve_body($response_token), true)['access_token'];

			// This is where the fun stuff begins
			if($this->entity_description == ''){
				$this->entity_description = 'Pago a traves de 4GP';
			}
			$payload = array(
				"amount"             	=> $customer_order->get_total(),
				"description"           => $this->charge_description,
				"entity_description"    => strtoupper($this->entity_description),
				"currency"           	=> get_woocommerce_currency(),
				"credit_card_number"    => str_replace( array(' ', '-' ), '', $_POST['wc-4gpayments-card-number'] ),
				"credit_card_security_code_number" => str_replace( array(' ', '-' ), '', $_POST['wc-4gpayments-card-cvc'] ),
				"exp_month" 			=> substr($_POST['wc-4gpayments-card-expiry'], 0, 2),
				"exp_year" 				=> "20" . substr($_POST['wc-4gpayments-card-expiry'], -2),

			);

		// Send this payload to 4GP for processing
		$response = wp_remote_post( $api_url, array(
			'method'    => 'POST',
			'body'      => json_encode($payload, true),
			'timeout'   => 90,
			'blocking' => true,
			'headers' => array('authorization' => 'bearer ' . $api_token, 'content-type' => 'application/json'),
		 ) );

		 $JsonResponse = json_decode($response['body']);
		 $response_Detail = $JsonResponse ->{'detail'};

		if ( is_wp_error( $response ) )
			throw new Exception( __( 'Hubo un problema para comunicarse con el procesador de pagos...', 'wc-4gpayments' ) );

		if ( empty( $response['body'] ) )
			throw new Exception( __( 'La respuesta no obtuvo nada.', 'wc-4gpayments' ) );

		// get body response while get not error
		$responde_code = wp_remote_retrieve_response_code( $response );
		// 1 or 4 means the transaction was a success
		if ( $responde_code == 201 ) {
			// Payment successful
			$customer_order->add_order_note( __( 'Pago completo.', 'wc-4gpayments' ) );

			// paid order marked
			$customer_order->payment_complete();

			// this is important part for empty cart
			$woocommerce->cart->empty_cart();

			// Redirect to thank you page
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $customer_order ),
			);
		} else {
			//transiction fail
			wc_add_notice( $response_Detail, 'error' );
			$customer_order->add_order_note( 'Error: '. $response_Detail );
		}
	}

	// Validate fields
	public function validate_fields() {
		return true;
	}

	public function do_ssl_check() {
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";
			}
		}
	}

}
/************************************************************************************************************************************************
************************************************************************************************************************************************
************************************************************************************************************************************************/

function register_simple_rental_product_type() {

	class WC_Product_Simple_Rental extends WC_Product {
			public function __construct( $product ) {
			$this->product_type = 'simple_rental';
			parent::__construct( $product );
		}
	}
}
add_action( 'plugins_loaded', 'register_simple_rental_product_type' );

function add_simple_rental_product( $types ){

	// Key should be exactly the same as in the class
	$types[ 'simple_rental' ] = __( '4G Planes' );

	return $types;

}
add_filter( 'product_type_selector', 'add_simple_rental_product' );


function simple_rental_custom_js() {

	if ( 'product' != get_post_type() ) :
		return;
	endif;

	?><script type='text/javascript'>
		jQuery( document ).ready( function() {
			jQuery( '.options_group.pricing' ).addClass( 'show_if_simple_rental' ).show();
		});

	</script><?php

}
add_action( 'admin_footer', 'simple_rental_custom_js' );



function custom_product_tabs( $tabs) {

	$tabs['rental'] = array(
		'label'		=> __( '4G Planes', 'woocommerce' ),
		'target'	=> 'rental_options',
		'class'		=> array( 'show_if_simple_rental', 'show_if_variable_rental'),
	);

	return $tabs;

}
add_filter( 'woocommerce_product_data_tabs', 'custom_product_tabs' );



/*function rental_options_product_tab_content() {
	global $post;

	$dest_name = "../wp-content/plugins/gpayments-woocommerce-plugin/";

	if (!$fp = fopen($dest_name."auth.txt", "r")){
		echo "The file can't be opened";
	}
	$file = $dest_name."auth.txt";
	$fp = fopen($file, "r");
	$contents = fread($fp, filesize($file));
	fclose($fp);

	$credentials = explode(" ", $contents);

	$Client_Id = trim($credentials[0]);
	$Client_Secret = trim($credentials[1]);

	$api_auth_url = 'https://api.payments.4geeks.io/authentication/token/';

	$data_to_send = array("grant_type"=>"client_credentials",
	 					  "client_id" => $Client_Id,
	 					  "client_secret" => $Client_Secret
	 				);
	$response_token = wp_remote_post( $api_auth_url, array(
	 		'method' => 'POST',
	 		'timeout' => 90,
	 		'blocking' => true,
	 		'headers' => array('content-type' => 'application/json'),
	 		'body' => json_encode($data_to_send, true)
	 	) );


	$api_token = json_decode(wp_remote_retrieve_body($response_token), true)['access_token'];

	if($api_token != '' && $api_token != NULL && $api_token != 'undefined'){
		$api_plan_url = 'https://api.payments.4geeks.io/v1/plans/mine';
		$response_plan = wp_remote_get($api_plan_url, array('headers' => 'authorization: bearer ' . $api_token));

		$plans =  json_decode(wp_remote_retrieve_body($response_plan),true);
	}else{
		echo "No";
	}
	$i = 0;
	$options[''] = __( 'Seleccione un valor', 'woocommerce'); // default value

	foreach($plans as $key => $opt){
		$options[]  = $opt['information']['name'];
	}
	?><div id='rental_options' class='panel woocommerce_options_panel'><?php
		?><div class='options_group'><?php
			if ($mofile != '-es_CR.mo'){
				$currency_label = __('Currency: ','woocommerce');
				$amout_label = __('Amount: ','woocommerce');
				$trial_label = __('Time trial: ','woocommerce');
				$c_descrip_label = __('Card Description: ','woocommerce');
				$interval_label = __('Interval: ','woocommerce');
				$icount_label = __('Count: ','woocommerce');

				$cu_tooltip = __( 'Currency', 'woocommerce' );
				$cc_tooltip = __('Description on credit card customer balance', 'woocommerce' );
				$tr_tooltip = __( 'Days of free use', 'woocommerce' );
				$in_tooltip = __( 'Frecuency of charges', 'woocommerce' );
				$ic_tooltip = __( 'Months', 'woocommerce' );
				$am_tooltip = __( 'Amount', 'woocommerce' );
			}else{
				$currency_label = __('Moneda: ','woocommerce');
				$amout_label = __('Precio: ','woocommerce');
				$trial_label = __('Prueba Gratuita: ','woocommerce');
				$c_descrip_label = __('Descripcion Tarjeta: ','woocommerce');
				$interval_label = __('Intérvalo: ','woocommerce');
				$icount_label = __('Meses: ','woocommerce');

				$cu_tooltip = __( 'Moneda en la cual se haran los rebajos', 'woocommerce' );
				$cc_tooltip = __( 'Descripcion para el estado de cuenta de la tarjeta del cliete', 'woocommerce' );
				$tr_tooltip = __( 'Numero de dias en las cuales se le brindara al usuario un trial', 'woocommerce' );
				$in_tooltip = __( 'Frecuenca de rebajos', 'woocommerce' );
				$ic_tooltip = __( 'Meses', 'woocommerce' );
				$am_tooltip = __( 'Monto', 'woocommerce' );

			}
			woocommerce_wp_select(
				array(
				'id'  	  => 'gpayment_plan_option',
				'label'   => __('Planes 4GP Disponibles', 'woocommerce'),
				'options' => $options,
				)
			);

			?>
				<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
				<script type="text/javascript">
				var plansJS = new Array();

				<?php
					for ($i = 0; $i < count($plans); $i++){
						?>
							plansJS[<?php echo $i ?>] = <?php echo json_encode($plans[$i]);?>;
						<?php
					}
				?>

				obj = plansJS;

				jQuery(document).ready(function(){

					$('#gpayment_plan_option').change(function(){

						var param = $('#gpayment_plan_option').val();

						if (param == ''){
							$("#_currency").val('');
							$("#_amount").val('');
							$("#_trial").val('');
							$("#_card_description").val('');
							$("#_interval").val('');
							$("#_icount").val('');
						}else{
							$("#_currency").val(obj[param].information.currency);
							$("#_amount").val(obj[param].information.amount);
							$("#_trial").val(obj[param].information.trial_period_days);
							$("#_card_description").val(obj[param].information.credit_card_description);
							$("#_interval").val(obj[param].information.interval);
							$("#_icount").val(obj[param].information.interval_count);
						}
					});
				});
				</script>

			<?php

			woocommerce_wp_text_input( array(
				'id'			=> '_currency',
				'label'			=> $currency_label,
				'desc_tip'		=> 'true',
				'description'	=> $cu_tooltip,
				'type' 			=> 'text',
			) );
			woocommerce_wp_text_input( array(
				'id'			=> '_amount',
				'label'			=> $amout_label,
				'desc_tip'		=> 'true',
				'description'	=> $am_tooltip,
				'type' 			=> 'text',
			) );
			woocommerce_wp_text_input( array(
				'id'			=> '_trial',
				'label'			=> $trial_label,
				'desc_tip'		=> 'true',
				'description'	=> $tr_tooltip,
				'type' 			=> 'text',
			) );
			woocommerce_wp_text_input( array(
				'id'			=> '_card_description',
				'label'			=> $c_descrip_label,
				'desc_tip'		=> 'true',
				'description'	=> $cc_tooltip,
				'type' 			=> 'text',
			) );
			woocommerce_wp_text_input( array(
				'id'			=> '_interval',
				'label'			=> $interval_label,
				'desc_tip'		=> 'true',
				'description'	=> $in_tooltip,
				'type' 			=> 'text',
			) );
			woocommerce_wp_text_input( array(
				'id'			=> '_icount',
				'label'			=> $icount_label,
				'desc_tip'		=> 'true',
				'description'	=> $ic_tooltip,
				'type' 			=> 'text',
			) );
		?></div>
	</div><?php
}
add_action( 'woocommerce_product_data_panels', 'rental_options_product_tab_content' );*/


/*function save_rental_option_field( $post_id ) {
	global $wpdb;
	$plan_option = isset( $_POST['gpayment_plan_option'] ) ? 'yes' : 'no';
	update_post_meta( $post_id, 'gpayment_plan_option', $plan_option );
	if ( isset( $_POST['gpayment_plan_option']) && isset($_POST['_currency'])
	 		 && isset($_POST['_amount']) && isset($_POST['_trial']) && isset($_POST['_card_description'])
			  		&& isset($_POST['_interval']) && isset($_POST['_icount'])) :
		update_post_meta($post_id, 'gpayment_plan_option', sanitize_text_field($_POST['gpayment_plan_option']));
		update_post_meta($post_id, '_currency', sanitize_text_field($_POST['_currency']));
		update_post_meta($post_id, '_amount', sanitize_text_field($_POST['_amount']));
		update_post_meta($post_id, '_trial', sanitize_text_field($_POST['_trial']));
		update_post_meta($post_id, '_card_description', sanitize_text_field($_POST['_card_description']));
		update_post_meta($post_id, '_interval', sanitize_text_field($_POST['_interval']));
		update_post_meta($post_id, '_icount', sanitize_text_field($_POST['_icount']));
		update_post_meta($post_id, '_enable_renta_option',	'no');
		update_post_meta($post_id, '_subscription_payment_sync_date',	'0');
		update_post_meta($post_id, '_subscription_price',	sanitize_text_field($_POST['_amount']));
		update_post_meta($post_id, '_subscription_trial_length',	sanitize_text_field($_POST['_trial']));
		update_post_meta($post_id, '_subscription_sign_up_fee',	'10');
		update_post_meta($post_id, '_subscription_period',	sanitize_text_field($_POST['_interval']));
		update_post_meta($post_id, '_subscription_period_interval',	sanitize_text_field($_POST['_icount']));
		update_post_meta($post_id, '_subscription_length',	'4');
		update_post_meta($post_id, '_subscription_trial_period',	'day');
		update_post_meta($post_id, '_subscription_limit',	'no');
		update_post_meta($post_id, '_subscription_one_time_shipping',	'no');
		//update_post_meta( $post_id, 'gpayment_plan_option', sanitize_text_field( $_POST['gpayment_plan_option'] ) );
	endif;
	$table = 'wp_term_relationships';
	$data = 'term_taxonomy_id = 16';
	$where = 'object_id = '.$post_id;

	$wpdb->update($table, $data, $where);
}*/
/*
_enable_renta_option	no
_subscription_payment_sync_date	0
_subscription_price	50
_subscription_trial_length	10
_subscription_sign_up_fee	10
_subscription_period	month
_subscription_period_interval	1
_subscription_length	4
_subscription_trial_period	day
_subscription_limit	no
_subscription_one_time_shipping	no

*/
add_action( 'woocommerce_process_product_meta_simple_rental',   'save_rental_option_field'  );
add_action( 'woocommerce_process_product_meta_variable_rental', 'save_rental_option_field'  );

function hide_attributes_data_panel( $tabs) {

	$tabs['attribute']['class'][] = 'hide_if_simple_rental hide_if_variable_rental';

	return $tabs;

}
add_filter( 'woocommerce_product_data_tabs', 'hide_attributes_data_panel' );

/*
***************************************************************************************************************************************************
***************************************************************************************************************************************************
***************************************************************************************************************************************************
***************************************************************************************************************************************************
*/

/**
 * Required functions
 */
if ( ! function_exists( 'woothemes_queue_update' ) || ! function_exists( 'is_woocommerce_active' ) ) {
	require_once( 'woo-includes/woo-functions.php' );
}

/**
 * Plugin updates
 */
woothemes_queue_update( plugin_basename( __FILE__ ), '6115e6d7e297b623a169fdcf5728b224', '27147' );

/**
 * Check if WooCommerce is active and at the required minimum version, and if it isn't, disable Subscriptions.
 *
 * @since 1.0
 */
if ( ! is_woocommerce_active() || version_compare( get_option( 'woocommerce_db_version' ), '2.5', '<' ) ) {
	add_action( 'admin_notices', 'WC_Subscriptions::woocommerce_inactive_notice' );
	return;
}

require_once( 'wcs-functions.php' );

require_once( 'includes/class-wc-subscriptions-coupon.php' );

require_once( 'includes/class-wc-subscriptions-product.php' );

require_once( 'includes/admin/class-wc-subscriptions-admin.php' );

require_once( 'includes/class-wc-subscriptions-manager.php' );

require_once( 'includes/class-wc-subscriptions-cart.php' );

require_once( 'includes/class-wc-subscriptions-order.php' );

require_once( 'includes/class-wc-subscriptions-renewal-order.php' );

require_once( 'includes/class-wc-subscriptions-checkout.php' );

require_once( 'includes/class-wc-subscriptions-email.php' );

require_once( 'includes/class-wc-subscriptions-addresses.php' );

require_once( 'includes/class-wc-subscriptions-change-payment-gateway.php' );

require_once( 'includes/gateways/class-wc-subscriptions-payment-gateways.php' );

require_once( 'includes/gateways/paypal/class-wcs-paypal.php' );

require_once( 'includes/class-wc-subscriptions-switcher.php' );

require_once( 'includes/class-wc-subscriptions-synchroniser.php' );

require_once( 'includes/upgrades/class-wc-subscriptions-upgrader.php' );

require_once( 'includes/upgrades/class-wcs-upgrade-logger.php' );

require_once( 'includes/libraries/tlc-transients/tlc-transients.php' );

require_once( 'includes/libraries/action-scheduler/action-scheduler.php' );

require_once( 'includes/abstracts/abstract-wcs-scheduler.php' );

require_once( 'includes/class-wcs-action-scheduler.php' );

require_once( 'includes/abstracts/abstract-wcs-cache-manager.php' );

require_once( 'includes/class-wcs-cached-data-manager.php' );

require_once( 'includes/class-wcs-cart-renewal.php' );

require_once( 'includes/class-wcs-cart-resubscribe.php' );

require_once( 'includes/class-wcs-cart-initial-payment.php' );

require_once( 'includes/class-wcs-download-handler.php' );

require_once( 'includes/class-wcs-retry-manager.php' );

require_once( 'includes/class-wcs-cart-switch.php' );

require_once( 'includes/class-wcs-limiter.php' );

require_once( 'includes/legacy/class-wcs-array-property-post-meta-black-magic.php' );

/**
 * The main subscriptions class.
 *
 * @since 1.0
 */
class WC_Subscriptions {

	public static $name = 'subscription';

	public static $activation_transient = 'woocommerce_subscriptions_activated';

	public static $plugin_file = __FILE__;

	public static $version = '2.2.17';

	private static $total_subscription_count = null;

	private static $scheduler;

	/** @var WCS_Cache_Manager */
	public static $cache;

	/**
	 * Set up the class, including it's hooks & filters, when the file is loaded.
	 *
	 * @since 1.0
	 **/
	public static function init() {

		// Register our custom subscription order type after WC_Post_types::register_post_types()
		add_action( 'init', __CLASS__ . '::register_order_types', 6 );

		add_filter( 'woocommerce_data_stores', __CLASS__ . '::add_data_stores', 10, 1 );

		// Register our custom subscription order statuses before WC_Post_types::register_post_status()
		add_action( 'init', __CLASS__ . '::register_post_status', 9 );

		add_action( 'init', __CLASS__ . '::maybe_activate_woocommerce_subscriptions' );

		register_deactivation_hook( __FILE__, __CLASS__ . '::deactivate_woocommerce_subscriptions' );

		// Override the WC default "Add to Cart" text to "Sign Up Now" (in various places/templates)
		add_filter( 'woocommerce_order_button_text', __CLASS__ . '::order_button_text' );
		add_action( 'woocommerce_subscription_add_to_cart', __CLASS__ . '::subscription_add_to_cart', 30 );
		add_action( 'woocommerce_variable-subscription_add_to_cart', __CLASS__ . '::variable_subscription_add_to_cart', 30 );
		add_action( 'wcopc_subscription_add_to_cart', __CLASS__ . '::wcopc_subscription_add_to_cart' ); // One Page Checkout compatibility

		// Ensure a subscription is never in the cart with products
		add_filter( 'woocommerce_add_to_cart_validation', __CLASS__ . '::maybe_empty_cart', 10, 4 );

		// Enqueue front-end styles, run after Storefront because it sets the styles to be empty
		add_filter( 'woocommerce_enqueue_styles', __CLASS__ . '::enqueue_styles', 100, 1 );

		// Load translation files
		add_action( 'init', __CLASS__ . '::load_plugin_textdomain', 3 );

		// Load dependent files
		add_action( 'plugins_loaded', __CLASS__ . '::load_dependant_classes' );

		// Attach hooks which depend on WooCommerce constants
		add_action( 'plugins_loaded', __CLASS__ . '::attach_dependant_hooks' );

		// Staging site or site migration notice
		add_action( 'admin_notices', __CLASS__ . '::woocommerce_site_change_notice' );

		// Add the "Settings | Documentation" links on the Plugins administration screen
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), __CLASS__ . '::action_links' );

		add_filter( 'action_scheduler_queue_runner_batch_size', __CLASS__ . '::action_scheduler_multisite_batch_size' );

		add_action( 'in_plugin_update_message-' . plugin_basename( __FILE__ ), __CLASS__ . '::update_notice', 10, 2 );

		self::$cache = WCS_Cache_Manager::get_instance();

		$scheduler_class = apply_filters( 'woocommerce_subscriptions_scheduler', 'WCS_Action_Scheduler' );

		self::$scheduler = new $scheduler_class();
	}

	/**
	 * Register data stores for WooCommerce 3.0+
	 *
	 * @since 2.2.0
	 */
	public static function add_data_stores( $data_stores ) {

		$data_stores['subscription']                   = 'WCS_Subscription_Data_Store_CPT';

		// Use WC core data stores for our products
		$data_stores['product-variable-subscription']  = 'WC_Product_Variable_Data_Store_CPT';
		$data_stores['product-subscription_variation'] = 'WC_Product_Variation_Data_Store_CPT';
		$data_stores['order-item-line_item_pending_switch'] = 'WC_Order_Item_Product_Data_Store';

		return $data_stores;
	}

	/**
	 * Register core post types
	 *
	 * @since 2.0
	 */
	public static function register_order_types() {

		wc_register_order_type(
			'shop_subscription',
			apply_filters( 'woocommerce_register_post_type_subscription',
				array(
					// register_post_type() params
					'labels'              => array(
						'name'               => __( 'Subscriptions', 'woocommerce-subscriptions' ),
						'singular_name'      => __( 'Subscription', 'woocommerce-subscriptions' ),
						'add_new'            => _x( 'Add Subscription', 'custom post type setting', 'woocommerce-subscriptions' ),
						'add_new_item'       => _x( 'Add New Subscription', 'custom post type setting', 'woocommerce-subscriptions' ),
						'edit'               => _x( 'Edit', 'custom post type setting', 'woocommerce-subscriptions' ),
						'edit_item'          => _x( 'Edit Subscription', 'custom post type setting', 'woocommerce-subscriptions' ),
						'new_item'           => _x( 'New Subscription', 'custom post type setting', 'woocommerce-subscriptions' ),
						'view'               => _x( 'View Subscription', 'custom post type setting', 'woocommerce-subscriptions' ),
						'view_item'          => _x( 'View Subscription', 'custom post type setting', 'woocommerce-subscriptions' ),
						'search_items'       => __( 'Search Subscriptions', 'woocommerce-subscriptions' ),
						'not_found'          => self::get_not_found_text(),
						'not_found_in_trash' => _x( 'No Subscriptions found in trash', 'custom post type setting', 'woocommerce-subscriptions' ),
						'parent'             => _x( 'Parent Subscriptions', 'custom post type setting', 'woocommerce-subscriptions' ),
						'menu_name'          => __( 'Subscriptions', 'woocommerce-subscriptions' ),
					),
					'description'         => __( 'This is where subscriptions are stored.', 'woocommerce-subscriptions' ),
					'public'              => false,
					'show_ui'             => true,
					'capability_type'     => 'shop_order',
					'map_meta_cap'        => true,
					'publicly_queryable'  => false,
					'exclude_from_search' => true,
					'show_in_menu'        => current_user_can( 'manage_woocommerce' ) ? 'woocommerce' : true,
					'hierarchical'        => false,
					'show_in_nav_menus'   => false,
					'rewrite'             => false,
					'query_var'           => false,
					'supports'            => array( 'title', 'comments', 'custom-fields' ),
					'has_archive'         => false,

					// wc_register_order_type() params
					'exclude_from_orders_screen'       => true,
					'add_order_meta_boxes'             => true,
					'exclude_from_order_count'         => true,
					'exclude_from_order_views'         => true,
					'exclude_from_order_webhooks'      => true,
					'exclude_from_order_reports'       => true,
					'exclude_from_order_sales_reports' => true,
					'class_name'                       => self::is_woocommerce_pre( '3.0' ) ? 'WC_Subscription_Legacy' : 'WC_Subscription',
				)
			)
		);
	}

	/**
	 * Method that returns the not found text. If the user has created at least one subscription, the standard message
	 * will appear. If that's empty, the long, explanatory one will appear in the table.
	 *
	 * Filters:
	 * - woocommerce_subscriptions_not_empty: gets passed the boolean option value. 'true' means the subscriptions
	 * list is not empty, the user is familiar with how it works, and standard message appears.
	 * - woocommerce_subscriptions_not_found_label: gets the original message for other plugins to modify, in case
	 * they want to add more links, or modify any of the messages.
	 * @since  2.0
	 *
	 * @return string what appears in the list table of the subscriptions
	 */
	private static function get_not_found_text() {
		$subscriptions_exist = self::$cache->cache_and_get( 'wcs_do_subscriptions_exist', 'wcs_do_subscriptions_exist' );
		if ( true === apply_filters( 'woocommerce_subscriptions_not_empty', $subscriptions_exist ) ) {
			$not_found_text = __( 'No Subscriptions found', 'woocommerce-subscriptions' );
		} else {
			$not_found_text = '<p>' . __( 'Subscriptions will appear here for you to view and manage once purchased by a customer.', 'woocommerce-subscriptions' ) . '</p>';
			// translators: placeholders are opening and closing link tags
			$not_found_text .= '<p>' . sprintf( __( '%sLearn more about managing subscriptions &raquo;%s', 'woocommerce-subscriptions' ), '<a href="http://docs.woocommerce.com/document/subscriptions/store-manager-guide/#section-3" target="_blank">', '</a>' ) . '</p>';
			// translators: placeholders are opening and closing link tags
			$not_found_text .= '<p>' . sprintf( __( '%sAdd a subscription product &raquo;%s', 'woocommerce-subscriptions' ), '<a href="' . esc_url( WC_Subscriptions_Admin::add_subscription_url() ) . '">', '</a>' ) . '</p>';
		}

		return apply_filters( 'woocommerce_subscriptions_not_found_label', $not_found_text );
	}

	/**
	 * Register our custom post statuses, used for order/subscription status
	 */
	public static function register_post_status() {

		$subscription_statuses = wcs_get_subscription_statuses();

		$registered_statuses = apply_filters( 'woocommerce_subscriptions_registered_statuses', array(
			'wc-active'         => _nx_noop( 'Active <span class="count">(%s)</span>', 'Active <span class="count">(%s)</span>', 'post status label including post count', 'woocommerce-subscriptions' ),
			'wc-switched'       => _nx_noop( 'Switched <span class="count">(%s)</span>', 'Switched <span class="count">(%s)</span>', 'post status label including post count', 'woocommerce-subscriptions' ),
			'wc-expired'        => _nx_noop( 'Expired <span class="count">(%s)</span>', 'Expired <span class="count">(%s)</span>', 'post status label including post count', 'woocommerce-subscriptions' ),
			'wc-pending-cancel' => _nx_noop( 'Pending Cancellation <span class="count">(%s)</span>', 'Pending Cancellation <span class="count">(%s)</span>', 'post status label including post count', 'woocommerce-subscriptions' ),
		) );

		if ( is_array( $subscription_statuses ) && is_array( $registered_statuses ) ) {

			foreach ( $registered_statuses as $status => $label_count ) {

				register_post_status( $status, array(
					'label'                     => $subscription_statuses[ $status ], // use same label/translations as wcs_get_subscription_statuses()
					'public'                    => false,
					'exclude_from_search'       => false,
					'show_in_admin_all_list'    => true,
					'show_in_admin_status_list' => true,
					'label_count'               => $label_count,
				) );
			}
		}
	}

	/**
	 * Enqueues stylesheet for the My Subscriptions table on the My Account page.
	 *
	 * @since 1.5
	 */
	public static function enqueue_styles( $styles ) {

		if ( is_checkout() || is_cart() ) {
			$styles['wcs-checkout'] = array(
				'src'     => str_replace( array( 'http:', 'https:' ), '', plugin_dir_url( __FILE__ ) ) . 'assets/css/checkout.css',
				'deps'    => 'wc-checkout',
				'version' => WC_VERSION,
				'media'   => 'all',
			);
		} elseif ( is_account_page() ) {
			$styles['wcs-view-subscription'] = array(
				'src'     => str_replace( array( 'http:', 'https:' ), '', plugin_dir_url( __FILE__ ) ) . 'assets/css/view-subscription.css',
				'deps'    => 'woocommerce-smallscreen',
				'version' => self::$version,
				'media'   => 'only screen and (max-width: ' . apply_filters( 'woocommerce_style_smallscreen_breakpoint', $breakpoint = '768px' ) . ')',
			);
		}

		return $styles;
	}

	/**
	 * Loads the my-subscriptions.php template on the My Account page.
	 *
	 * @since 1.0
	 */
	public static function get_my_subscriptions_template() {

		$subscriptions = wcs_get_users_subscriptions();
		$user_id       = get_current_user_id();

		wc_get_template( 'myaccount/my-subscriptions.php', array( 'subscriptions' => $subscriptions, 'user_id' => $user_id ), '', plugin_dir_path( __FILE__ ) . 'templates/' );
	}

	/**
	 * Output a redirect URL when an item is added to the cart when a subscription was already in the cart.
	 *
	 * @since 1.0
	 */
	public static function redirect_ajax_add_to_cart( $fragments ) {

		$data = array(
			'error'       => true,
			'product_url' => wc_get_cart_url(),
		);

		return $data;
	}

	/**
	 * When a subscription is added to the cart, remove other products/subscriptions to
	 * work with PayPal Standard, which only accept one subscription per checkout.
	 *
	 * If multiple purchase flag is set, allow them to be added at the same time.
	 *
	 * @since 1.0
	 */
	public static function maybe_empty_cart( $valid, $product_id, $quantity, $variation_id = '' ) {

		$is_subscription                 = WC_Subscriptions_Product::is_subscription( $product_id );
		$cart_contains_subscription      = WC_Subscriptions_Cart::cart_contains_subscription();
		$multiple_subscriptions_possible = WC_Subscriptions_Payment_Gateways::one_gateway_supports( 'multiple_subscriptions' );
		$manual_renewals_enabled         = ( 'yes' == get_option( WC_Subscriptions_Admin::$option_prefix . '_accept_manual_renewals', 'no' ) ) ? true : false;
		$canonical_product_id            = ( ! empty( $variation_id ) ) ? $variation_id : $product_id;

		if ( $is_subscription && 'yes' != get_option( WC_Subscriptions_Admin::$option_prefix . '_multiple_purchase', 'no' ) ) {

			if ( ! WC_Subscriptions_Cart::cart_contains_product( $canonical_product_id ) ) {
				WC()->cart->empty_cart();
			}
		} elseif ( $is_subscription && wcs_cart_contains_renewal() && ! $multiple_subscriptions_possible && ! $manual_renewals_enabled ) {

			self::remove_subscriptions_from_cart();

			wc_add_notice( __( 'A subscription renewal has been removed from your cart. Multiple subscriptions can not be purchased at the same time.', 'woocommerce-subscriptions' ), 'notice' );

		} elseif ( $is_subscription && $cart_contains_subscription && ! $multiple_subscriptions_possible && ! $manual_renewals_enabled && ! WC_Subscriptions_Cart::cart_contains_product( $canonical_product_id ) ) {

			self::remove_subscriptions_from_cart();

			wc_add_notice( __( 'A subscription has been removed from your cart. Due to payment gateway restrictions, different subscription products can not be purchased at the same time.', 'woocommerce-subscriptions' ), 'notice' );

		} elseif ( $cart_contains_subscription && 'yes' != get_option( WC_Subscriptions_Admin::$option_prefix . '_multiple_purchase', 'no' ) ) {

			self::remove_subscriptions_from_cart();

			wc_add_notice( __( 'A subscription has been removed from your cart. Products and subscriptions can not be purchased at the same time.', 'woocommerce-subscriptions' ), 'notice' );

			if ( WC_Subscriptions::is_woocommerce_pre( '3.0.8' ) ) {
				// Redirect to cart page to remove subscription & notify shopper
				add_filter( 'add_to_cart_fragments', __CLASS__ . '::redirect_ajax_add_to_cart' );
			} else {
				add_filter( 'woocommerce_add_to_cart_fragments', __CLASS__ . '::redirect_ajax_add_to_cart' );
			}
		}

		return $valid;
	}

	/**
	 * Removes all subscription products from the shopping cart.
	 *
	 * @since 1.0
	 */
	public static function remove_subscriptions_from_cart() {

		foreach ( WC()->cart->cart_contents as $cart_item_key => $cart_item ) {
			if ( WC_Subscriptions_Product::is_subscription( $cart_item['data'] ) ) {
				WC()->cart->set_quantity( $cart_item_key, 0 );
			}
		}
	}

	/**
	 * For a smoother sign up process, tell WooCommerce to redirect the shopper immediately to
	 * the checkout page after she clicks the "Sign Up Now" button
	 *
	 * Only enabled if multiple checkout is not enabled.
	 *
	 * @param string $url The cart redirect $url WooCommerce determined.
	 * @since 1.0
	 */
	public static function add_to_cart_redirect( $url ) {

		// If product is of the subscription type
		if ( isset( $_REQUEST['add-to-cart'] ) && is_numeric( $_REQUEST['add-to-cart'] ) && WC_Subscriptions_Product::is_subscription( (int) $_REQUEST['add-to-cart'] ) ) {

			// Redirect to checkout if mixed checkout is disabled
			if ( 'yes' != get_option( WC_Subscriptions_Admin::$option_prefix . '_multiple_purchase', 'no' ) ) {

				wc_clear_notices();

				$url = wc_get_checkout_url();

			// Redirect to the same page (if the customer wouldn't be redirected to the cart) to ensure the cart widget loads correctly
			} elseif ( 'yes' != get_option( 'woocommerce_cart_redirect_after_add' ) && self::is_woocommerce_pre( '2.5' ) ) {

				$url = remove_query_arg( 'add-to-cart' );

			}
		}

		return $url;
	}

	/**
	 * Override the WooCommerce "Place Order" text with "Sign Up Now"
	 *
	 * @since 1.0
	 */
	public static function order_button_text( $button_text ) {
		global $product;

		if ( WC_Subscriptions_Cart::cart_contains_subscription() ) {
			$button_text = get_option( WC_Subscriptions_Admin::$option_prefix . '_order_button_text', __( 'Sign Up Now', 'woocommerce-subscriptions' ) );
		}

		return $button_text;
	}

	/**
	 * Load the subscription add_to_cart template.
	 *
	 * Use the same cart template for subscription as that which is used for simple products. Reduce code duplication
	 * and is made possible by the friendly actions & filters found through WC.
	 *
	 * Not using a custom template both prevents code duplication and helps future proof this extension from core changes.
	 *
	 * @since 1.0
	 */
	public static function subscription_add_to_cart() {
		wc_get_template( 'single-product/add-to-cart/subscription.php', array(), '', plugin_dir_path( __FILE__ ) . 'templates/' );
	}

	/**
	 * Load the variable subscription add_to_cart template
	 *
	 * Use a very similar cart template as that of a variable product with added functionality.
	 *
	 * @since 2.0.9
	 */
	public static function variable_subscription_add_to_cart() {
		global $product;

		// Enqueue variation scripts
		wp_enqueue_script( 'wc-add-to-cart-variation' );

		// Get Available variations?
		$get_variations = sizeof( $product->get_children() ) <= apply_filters( 'woocommerce_ajax_variation_threshold', 30, $product );

		// Load the template
		wc_get_template( 'single-product/add-to-cart/variable-subscription.php', array(
			'available_variations' => $get_variations ? $product->get_available_variations() : false,
			'attributes'           => $product->get_variation_attributes(),
			'selected_attributes'  => $product->get_default_attributes(),
		), '', plugin_dir_path( __FILE__ ) . 'templates/' );
	}

	/**
	 * Compatibility with WooCommerce On One Page Checkout.
	 *
	 * Use OPC's simple add to cart template for simple subscription products (to ensure data attributes required by OPC are added).
	 *
	 * Variable subscription products will be handled automatically because they identify as "variable" in response to is_type() method calls,
	 * which OPC uses.
	 *
	 * @since 1.5.16
	 */
	public static function wcopc_subscription_add_to_cart() {
		global $product;
		wc_get_template( 'checkout/add-to-cart/simple.php', array( 'product' => $product ), '', PP_One_Page_Checkout::$template_path );
	}

	/**
	 * Takes a number and returns the number with its relevant suffix appended, eg. for 2, the function returns 2nd
	 *
	 * @since 1.0
	 */
	public static function append_numeral_suffix( $number ) {

		// Handle teens: if the tens digit of a number is 1, then write "th" after the number. For example: 11th, 13th, 19th, 112th, 9311th. http://en.wikipedia.org/wiki/English_numerals
		if ( strlen( $number ) > 1 && 1 == substr( $number, -2, 1 ) ) {
			// translators: placeholder is a number, this is for the teens
			$number_string = sprintf( __( '%sth', 'woocommerce-subscriptions' ), $number );
		} else { // Append relevant suffix
			switch ( substr( $number, -1 ) ) {
				case 1:
					// translators: placeholder is a number, numbers ending in 1
					$number_string = sprintf( __( '%sst', 'woocommerce-subscriptions' ), $number );
					break;
				case 2:
					// translators: placeholder is a number, numbers ending in 2
					$number_string = sprintf( __( '%snd', 'woocommerce-subscriptions' ), $number );
					break;
				case 3:
					// translators: placeholder is a number, numbers ending in 3
					$number_string = sprintf( __( '%srd', 'woocommerce-subscriptions' ), $number );
					break;
				default:
					// translators: placeholder is a number, numbers ending in 4-9, 0
					$number_string = sprintf( __( '%sth', 'woocommerce-subscriptions' ), $number );
					break;
			}
		}

		return apply_filters( 'woocommerce_numeral_suffix', $number_string, $number );
	}


	/*
	 * Plugin House Keeping
	 */

	/**
	 * Called when WooCommerce is inactive or running and out-of-date version to display an inactive notice.
	 *
	 * @since 1.2
	 */
	public static function woocommerce_inactive_notice() {
		if ( current_user_can( 'activate_plugins' ) ) :
			if ( ! is_woocommerce_active() ) : ?>
<div id="message" class="error">
	<p><?php
		$install_url = wp_nonce_url( add_query_arg( array( 'action' => 'install-plugin', 'plugin' => 'woocommerce' ), admin_url( 'update.php' ) ), 'install-plugin_woocommerce' );

		// translators: 1$-2$: opening and closing <strong> tags, 3$-4$: link tags, takes to woocommerce plugin on wp.org, 5$-6$: opening and closing link tags, leads to plugins.php in admin
		printf( esc_html__( '%1$sWooCommerce Subscriptions is inactive.%2$s The %3$sWooCommerce plugin%4$s must be active for WooCommerce Subscriptions to work. Please %5$sinstall & activate WooCommerce &raquo;%6$s',  'woocommerce-subscriptions' ), '<strong>', '</strong>', '<a href="http://wordpress.org/extend/plugins/woocommerce/">', '</a>', '<a href="' .  esc_url( $install_url ) . '">', '</a>' ); ?>
	</p>
</div>
		<?php elseif ( version_compare( get_option( 'woocommerce_db_version' ), '2.4', '<' ) ) : ?>
<div id="message" class="error">
	<p><?php
		// translators: 1$-2$: opening and closing <strong> tags, 3$-4$: opening and closing link tags, leads to plugin admin
		printf( esc_html__( '%1$sWooCommerce Subscriptions is inactive.%2$s This version of Subscriptions requires WooCommerce 2.4 or newer. Please %3$supdate WooCommerce to version 2.4 or newer &raquo;%4$s', 'woocommerce-subscriptions' ), '<strong>', '</strong>', '<a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">', '</a>' ); ?>
	</p>
</div>
		<?php endif; ?>
	<?php endif;
	}

	/**
	 * Checks on each admin page load if Subscriptions plugin is activated.
	 *
	 * Apparently the official WP API is "lame" and it's far better to use an upgrade routine fired on admin_init: http://core.trac.wordpress.org/ticket/14170
	 *
	 * @since 1.1
	 */
	public static function maybe_activate_woocommerce_subscriptions() {
		$is_active = get_option( WC_Subscriptions_Admin::$option_prefix . '_is_active', false );

		if ( false == $is_active ) {

			// Add the "Subscriptions" product type
			if ( ! get_term_by( 'slug', self::$name, 'product_type' ) ) {
				wp_insert_term( self::$name, 'product_type' );
			}

			// Maybe add the "Variable Subscriptions" product type
			if ( ! get_term_by( 'slug', 'variable-subscription', 'product_type' ) ) {
				wp_insert_term( __( 'Variable Subscription', 'woocommerce-subscriptions' ), 'product_type' );
			}

			// If no Subscription settings exist, its the first activation, so add defaults
			if ( get_option( WC_Subscriptions_Admin::$option_prefix . '_cancelled_role', false ) == false ) {
				WC_Subscriptions_Admin::add_default_settings();
			}

			// if this is the first time activating WooCommerce Subscription we want to enable PayPal debugging by default.
			if ( '0' == get_option( WC_Subscriptions_Admin::$option_prefix . '_previous_version', '0' ) && false == get_option( WC_Subscriptions_admin::$option_prefix . '_paypal_debugging_default_set', false ) ) {
				$paypal_settings          = get_option( 'woocommerce_paypal_settings' );
				$paypal_settings['debug'] = 'yes';
				update_option( 'woocommerce_paypal_settings', $paypal_settings );
				update_option( WC_Subscriptions_admin::$option_prefix . '_paypal_debugging_default_set', 'true' );
			}

			add_option( WC_Subscriptions_Admin::$option_prefix . '_is_active', true );

			set_transient( self::$activation_transient, true, 60 * 60 );

			flush_rewrite_rules();

			do_action( 'woocommerce_subscriptions_activated' );
		}

	}

	/**
	 * Called when the plugin is deactivated. Deletes the subscription product type and fires an action.
	 *
	 * @since 1.0
	 */
	public static function deactivate_woocommerce_subscriptions() {

		delete_option( WC_Subscriptions_Admin::$option_prefix . '_is_active' );

		flush_rewrite_rules();

		do_action( 'woocommerce_subscriptions_deactivated' );
	}

	/**
	 * Called on plugins_loaded to load any translation files.
	 *
	 * @since 1.1
	 */
	public static function load_plugin_textdomain() {

		$plugin_rel_path = apply_filters( 'woocommerce_subscriptions_translation_file_rel_path', dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Then check for a language file in /wp-content/plugins/woocommerce-subscriptions/languages/ (this will be overriden by any file already loaded)
		load_plugin_textdomain( 'woocommerce-subscriptions', false, $plugin_rel_path );
	}

	/**
	 * Loads classes that depend on WooCommerce base classes.
	 *
	 * @since 1.2.4
	 */
	public static function load_dependant_classes() {

		require_once( 'includes/class-wc-subscription.php' );

		require_once( 'includes/class-wc-product-subscription.php' );

		require_once( 'includes/class-wc-product-subscription-variation.php' );

		require_once( 'includes/class-wc-product-variable-subscription.php' );

		require_once( 'includes/admin/class-wcs-admin-post-types.php' );

		require_once( 'includes/admin/class-wcs-admin-meta-boxes.php' );

		require_once( 'includes/admin/class-wcs-admin-reports.php' );

		require_once( 'includes/admin/reports/class-wcs-report-cache-manager.php' );

		require_once( 'includes/admin/meta-boxes/class-wcs-meta-box-related-orders.php' );

		require_once( 'includes/admin/meta-boxes/class-wcs-meta-box-subscription-data.php' );

		require_once( 'includes/admin/meta-boxes/class-wcs-meta-box-subscription-schedule.php' );

		require_once( 'includes/class-wcs-change-payment-method-admin.php' );

		require_once( 'includes/class-wcs-webhooks.php' );

		require_once( 'includes/class-wcs-auth.php' );

		require_once( 'includes/class-wcs-api.php' );

		require_once( 'includes/class-wcs-template-loader.php' );

		require_once( 'includes/class-wcs-query.php' );

		require_once( 'includes/class-wcs-remove-item.php' );

		require_once( 'includes/class-wcs-user-change-status-handler.php' );

		require_once( 'includes/class-wcs-my-account-payment-methods.php' );

		if ( self::is_woocommerce_pre( '3.0' ) ) {

			require_once( 'includes/legacy/class-wc-subscription-legacy.php' );

			require_once( 'includes/legacy/class-wcs-product-legacy.php' );

			require_once( 'includes/legacy/class-wc-product-subscription-legacy.php' );

			require_once( 'includes/legacy/class-wc-product-subscription-variation-legacy.php' );

			require_once( 'includes/legacy/class-wc-product-variable-subscription-legacy.php' );

			// Load WC_DateTime when it doesn't exist yet so we can use it for datetime handling consistently with WC 3.0+
			if ( ! class_exists( 'WC_DateTime' ) ) {
				require_once( 'includes/libraries/class-wc-datetime.php' );
			}
		} else {
			require_once( 'includes/class-wc-order-item-pending-switch.php' );

			require_once( 'includes/data-stores/class-wcs-subscription-data-store-cpt.php' );

			require_once( 'includes/deprecated/class-wcs-deprecated-filter-hooks.php' );
		}

		// Provide a hook to enable running deprecation handling for stores that might want to check for deprecated code
		if ( apply_filters( 'woocommerce_subscriptions_load_deprecation_handlers', false ) ) {

			require_once( 'includes/abstracts/abstract-wcs-hook-deprecator.php' );

			require_once( 'includes/abstracts/abstract-wcs-dynamic-hook-deprecator.php' );

			require_once( 'includes/deprecated/class-wcs-action-deprecator.php' );

			require_once( 'includes/deprecated/class-wcs-filter-deprecator.php' );

			require_once( 'includes/deprecated/class-wcs-dynamic-action-deprecator.php' );

			require_once( 'includes/deprecated/class-wcs-dynamic-filter-deprecator.php' );
		}

	}

	/**
	 * Some hooks need to check for the version of WooCommerce, which we can only do after WooCommerce is loaded.
	 *
	 * @since 1.5.17
	 */
	public static function attach_dependant_hooks() {

		// Redirect the user immediately to the checkout page after clicking "Sign Up Now" buttons to encourage immediate checkout
		add_filter( 'woocommerce_add_to_cart_redirect', __CLASS__ . '::add_to_cart_redirect' );

		if ( self::is_woocommerce_pre( '2.6' ) ) {
			// Display Subscriptions on a User's account page
			add_action( 'woocommerce_before_my_account', __CLASS__ . '::get_my_subscriptions_template' );
		}
	}

	/**
	 * Displays a notice when Subscriptions is being run on a different site, like a staging or testing site.
	 *
	 * @since 1.3.8
	 */
	public static function woocommerce_site_change_notice() {

		if ( self::is_duplicate_site() && current_user_can( 'manage_options' ) ) {

			if ( ! empty( $_REQUEST['_wcsnonce'] ) && wp_verify_nonce( $_REQUEST['_wcsnonce'], 'wcs_duplicate_site' ) && isset( $_GET['wc_subscription_duplicate_site'] ) ) {

				if ( 'update' === $_GET['wc_subscription_duplicate_site'] ) {

					WC_Subscriptions::set_duplicate_site_url_lock();

				} elseif ( 'ignore' === $_GET['wc_subscription_duplicate_site'] ) {

					update_option( 'wcs_ignore_duplicate_siteurl_notice', self::get_current_sites_duplicate_lock() );

				}

				wp_safe_redirect( remove_query_arg( array( 'wc_subscription_duplicate_site', '_wcsnonce' ) ) );

			} elseif ( self::get_current_sites_duplicate_lock() !== get_option( 'wcs_ignore_duplicate_siteurl_notice' ) ) { ?>

				<div id="message" class="error">
					<p><?php
						// translators: 1$-2$: opening and closing <strong> tags, 3$-4$: opening and closing link tags. Leads to duplicate site article on docs
						printf( esc_html__( 'It looks like this site has moved or is a duplicate site. %1$sWooCommerce Subscriptions%2$s has disabled automatic payments and subscription related emails on this site to prevent duplicate payments from a staging or test environment. %3$sLearn more &raquo;%4$s.', 'woocommerce-subscriptions' ), '<strong>', '</strong>', '<a href="http://docs.woocommerce.com/document/subscriptions/faq/#section-39" target="_blank">', '</a>' ); ?></p>
					<div style="margin: 5px 0;">
						<a class="button button-primary" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc_subscription_duplicate_site', 'ignore' ), 'wcs_duplicate_site', '_wcsnonce' ) ); ?>"><?php esc_html_e( 'Quit nagging me (but don\'t enable automatic payments)', 'woocommerce-subscriptions' ); ?></a>
						<a class="button" href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'wc_subscription_duplicate_site', 'update' ), 'wcs_duplicate_site', '_wcsnonce' ) ); ?>"><?php esc_html_e( 'Enable automatic payments', 'woocommerce-subscriptions' ); ?></a>
					</div>
				</div>
			<?php
			}
		}
	}

	/**
	 * Get's a WC_Product using the new core WC @see wc_get_product() function if available, otherwise
	 * instantiating an instance of the WC_Product class.
	 *
	 * @since 1.2.4
	 */
	public static function get_product( $product_id ) {

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
		} else {
			$product = new WC_Product( $product_id );  // Shouldn't matter if product is variation as all we need is the product_type
		}

		return $product;
	}

	/**
	 * A general purpose function for grabbing an array of subscriptions in form of 'subscription_key' => 'subscription_details'.
	 *
	 * The $args param is based on the parameter of the same name used by the core WordPress @see get_posts() function.
	 * It can be used to choose which subscriptions should be returned by the function, how many subscriptions should be returned
	 * and in what order those subscriptions should be returned.
	 *
	 * @param array $args A set of name value pairs to determine the return value.
	 *		'subscriptions_per_page' The number of subscriptions to return. Set to -1 for unlimited. Default 10.
	 *		'offset' An optional number of subscription to displace or pass over. Default 0.
	 *		'orderby' The field which the subscriptions should be ordered by. Can be 'start_date', 'expiry_date', 'end_date', 'status', 'name' or 'order_id'. Defaults to 'start_date'.
	 *		'order' The order of the values returned. Can be 'ASC' or 'DESC'. Defaults to 'DESC'
	 *		'customer_id' The user ID of a customer on the site.
	 *		'product_id' The post ID of a WC_Product_Subscription, WC_Product_Variable_Subscription or WC_Product_Subscription_Variation object
	 *		'subscription_status' Any valid subscription status. Can be 'any', 'active', 'cancelled', 'suspended', 'expired', 'pending' or 'trash'. Defaults to 'any'.
	 * @return array Subscription details in 'subscription_key' => 'subscription_details' form.
	 * @since 1.4
	 */
	public static function get_subscriptions( $args = array() ) {

		if ( isset( $args['orderby'] ) ) {
			// Although most of these weren't public orderby values, they were used internally so may have been used by developers
			switch ( $args['orderby'] ) {
				case '_subscription_status' :
					_deprecated_argument( __METHOD__, '2.0', 'The "_subscription_status" orderby value is deprecated. Use "status" instead.' );
					$args['orderby'] = 'status';
					break;
				case '_subscription_start_date' :
					_deprecated_argument( __METHOD__, '2.0', 'The "_subscription_start_date" orderby value is deprecated. Use "start_date" instead.' );
					$args['orderby'] = 'start_date';
					break;
				case 'expiry_date' :
				case '_subscription_expiry_date' :
				case '_subscription_end_date' :
					_deprecated_argument( __METHOD__, '2.0', 'The expiry date orderby value is deprecated. Use "end_date" instead.' );
					$args['orderby'] = 'end_date';
					break;
				case 'trial_expiry_date' :
				case '_subscription_trial_expiry_date' :
					_deprecated_argument( __METHOD__, '2.0', 'The trial expiry date orderby value is deprecated. Use "trial_end_date" instead.' );
					$args['orderby'] = 'trial_end_date';
					break;
				case 'name' :
					_deprecated_argument( __METHOD__, '2.0', 'The "name" orderby value is deprecated - subscriptions no longer have just one name as they may contain multiple items.' );
					break;
			}
		}

		_deprecated_function( __METHOD__, '2.0', 'wcs_get_subscriptions( $args )' );

		$subscriptions = wcs_get_subscriptions( $args );

		$subscriptions_in_deprecated_structure = array();

		// Get the subscriptions in the backward compatible structure
		foreach ( $subscriptions as $subscription ) {
			$subscriptions_in_deprecated_structure[ wcs_get_old_subscription_key( $subscription ) ] = wcs_get_subscription_in_deprecated_structure( $subscription );
		}

		return apply_filters( 'woocommerce_get_subscriptions', $subscriptions_in_deprecated_structure, $args );
	}

	/**
	 * Returns the longest possible time period
	 *
	 * @since 1.3
	 */
	public static function get_longest_period( $current_period, $new_period ) {

		if ( empty( $current_period ) || 'year' == $new_period ) {
			$longest_period = $new_period;
		} elseif ( 'month' === $new_period && in_array( $current_period, array( 'week', 'day' ) ) ) {
			$longest_period = $new_period;
		} elseif ( 'week' === $new_period && 'day' === $current_period ) {
			$longest_period = $new_period;
		} else {
			$longest_period = $current_period;
		}

		return $longest_period;
	}

	/**
	 * Returns the shortest possible time period
	 *
	 * @since 1.3.7
	 */
	public static function get_shortest_period( $current_period, $new_period ) {

		if ( empty( $current_period ) || 'day' == $new_period ) {
			$shortest_period = $new_period;
		} elseif ( 'week' === $new_period && in_array( $current_period, array( 'month', 'year' ) ) ) {
			$shortest_period = $new_period;
		} elseif ( 'month' === $new_period && 'year' === $current_period ) {
			$shortest_period = $new_period;
		} else {
			$shortest_period = $current_period;
		}

		return $shortest_period;
	}

	/**
	 * Returns Subscriptions record of the site URL for this site
	 *
	 * @since 1.3.8
	 */
	public static function get_site_url( $blog_id = null, $path = '', $scheme = null ) {
		if ( empty( $blog_id ) || ! is_multisite() ) {
			$url = get_option( 'wc_subscriptions_siteurl' );
		} else {
			switch_to_blog( $blog_id );
			$url = get_option( 'wc_subscriptions_siteurl' );
			restore_current_blog();
		}

		// Remove the prefix used to prevent the site URL being updated on WP Engine
		$url = str_replace( '_[wc_subscriptions_siteurl]_', '', $url );

		$url = set_url_scheme( $url, $scheme );

		if ( ! empty( $path ) && is_string( $path ) && strpos( $path, '..' ) === false ) {
			$url .= '/' . ltrim( $path, '/' );
		}

		return apply_filters( 'wc_subscriptions_site_url', $url, $path, $scheme, $blog_id );
	}

	/**
	 * Checks if the WordPress site URL is the same as the URL for the site subscriptions normally
	 * runs on. Useful for checking if automatic payments should be processed.
	 *
	 * @since 1.3.8
	 */
	public static function is_duplicate_site() {

		if ( defined( 'WP_SITEURL' ) ) {
			$site_url = WP_SITEURL;
		} else {
			$site_url = get_site_url();
		}

		$wp_site_url_parts  = wp_parse_url( $site_url );
		$wcs_site_url_parts = wp_parse_url( self::get_site_url() );

		if ( ! isset( $wp_site_url_parts['path'] ) && ! isset( $wcs_site_url_parts['path'] ) ) {
			$paths_match = true;
		} elseif ( isset( $wp_site_url_parts['path'] ) && isset( $wcs_site_url_parts['path'] ) && $wp_site_url_parts['path'] == $wcs_site_url_parts['path'] ) {
			$paths_match = true;
		} else {
			$paths_match = false;
		}

		if ( isset( $wp_site_url_parts['host'] ) && isset( $wcs_site_url_parts['host'] ) && $wp_site_url_parts['host'] == $wcs_site_url_parts['host'] ) {
			$hosts_match = true;
		} else {
			$hosts_match = false;
		}

		// Check the host and path, do not check the protocol/scheme to avoid issues with WP Engine and other occasions where the WP_SITEURL constant may be set, but being overridden (e.g. by FORCE_SSL_ADMIN)
		if ( $paths_match && $hosts_match ) {
			$is_duplicate = false;
		} else {
			$is_duplicate = true;
		}

		return apply_filters( 'woocommerce_subscriptions_is_duplicate_site', $is_duplicate );
	}


	/**
	 * Include Docs & Settings links on the Plugins administration screen
	 *
	 * @param mixed $links
	 * @since 1.4
	 */
	public static function action_links( $links ) {

		$plugin_links = array(
			'<a href="' . WC_Subscriptions_Admin::settings_tab_url() . '">' . __( 'Settings', 'woocommerce-subscriptions' ) . '</a>',
			'<a href="http://docs.woocommerce.com/document/subscriptions/">' . _x( 'Docs', 'short for documents', 'woocommerce-subscriptions' ) . '</a>',
			'<a href="https://woocommerce.com/my-account/marketplace-ticket-form/">' . __( 'Support', 'woocommerce-subscriptions' ) . '</a>',
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Creates a URL based on the current site's URL that can be used to prevent duplicate payments from staging sites.
	 *
	 * The URL can not simply be the site URL, e.g. http://example.com, because WP Engine replaces all instances of the site URL in the database
	 * when creating a staging site. As a result, we obfuscate the URL by inserting '_[wc_subscriptions_siteurl]_' into the middle of it.
	 *
	 * Why not just use a hash? Because keeping the URL in the value allows for viewing and editing the URL directly in the database.
	 *
	 * @param mixed $links
	 * @since 1.4.2
	 */
	public static function get_current_sites_duplicate_lock() {

		if ( defined( 'WP_SITEURL' ) ) {
			$site_url = WP_SITEURL;
		} else {
			$site_url = get_site_url();
		}

		return substr_replace( $site_url, '_[wc_subscriptions_siteurl]_', strlen( $site_url ) / 2, 0 );
	}

	/**
	 * Sets a flag in the database to record the site's url. This then checked to determine if we are on a duplicate
	 * site or the original/main site, uses @see self::get_current_sites_duplicate_lock();
	 *
	 * @param mixed $links
	 * @since 1.4.2
	 */
	public static function set_duplicate_site_url_lock() {
		update_option( 'wc_subscriptions_siteurl', self::get_current_sites_duplicate_lock() );
	}

	/**
	 * Check if the installed version of WooCommerce is older than a specified version.
	 *
	 * @since 1.5.29
	 */
	public static function is_woocommerce_pre( $version ) {

		if ( ! defined( 'WC_VERSION' ) || version_compare( WC_VERSION, $version, '<' ) ) {
			$woocommerce_is_pre_version = true;
		} else {
			$woocommerce_is_pre_version = false;
		}

		return $woocommerce_is_pre_version;
	}

	/**
	 * Renewals use a lot more memory on WordPress multisite (10-15mb instead of 0.1-1mb) so
	 * we need to reduce the number of renewals run in each request.
	 *
	 * @since version 1.5
	 */
	public static function action_scheduler_multisite_batch_size( $batch_size ) {

		if ( is_multisite() ) {
			$batch_size = 10;
		}

		return $batch_size;
	}

	/**
	 * Include the upgrade notice that will fire when 2.0 is released.
	 *
	 * @param array $plugin_data information about the plugin
	 * @param array $r response from the server about the new version
	 */
	public static function update_notice( $plugin_data, $r ) {

		// Bail if the update notice is not relevant (new version is not yet 2.0 or we're already on 2.0)
		if ( version_compare( '2.0.0', $plugin_data['new_version'], '>' ) || version_compare( '2.0.0', $plugin_data['Version'], '<=' ) ) {
			return;
		}

		$update_notice = '<div class="wc_plugin_upgrade_notice">';
		// translators: placeholders are opening and closing tags. Leads to docs on version 2
		$update_notice .= sprintf( __( 'Warning! Version 2.0 is a major update to the WooCommerce Subscriptions extension. Before updating, please create a backup, update all WooCommerce extensions and test all plugins, custom code and payment gateways with version 2.0 on a staging site. %sLearn more about the changes in version 2.0 &raquo;%s', 'woocommerce-subscriptions' ), '<a href="http://docs.woocommerce.com/document/subscriptions/version-2/">', '</a>' );
		$update_notice .= '</div> ';

		echo wp_kses_post( $update_notice );
	}

	/**
	 * Send notice to store admins if they have previously updated Subscriptions to 2.0 and back to v1.5.n.
	 *
	 * @since 2.0
	 */
	public static function show_downgrade_notice() {
		if ( version_compare( get_option( WC_Subscriptions_Admin::$option_prefix . '_active_version', '0' ), self::$version, '>' ) ) {

			echo '<div class="update-nag">';
			echo sprintf( esc_html__( 'Warning! You are running version %s of WooCommerce Subscriptions plugin code but your database has been upgraded to Subscriptions version 2.0. This will cause major problems on your store.', 'woocommerce-subscriptions' ), esc_html( self::$version ) ) . '<br />';
			echo sprintf( esc_html__( 'Please upgrade the WooCommerce Subscriptions plugin to version 2.0 or newer immediately. If you need assistance, after upgrading to Subscriptions v2.0, please %sopen a support ticket%s.', 'woocommerce-subscriptions' ), '<a href="https://woocommerce.com/my-account/marketplace-ticket-form/">', '</a>' );
			echo '</div> ';

		}
	}

	/* Deprecated Functions */

	/**
	 * Add WooCommerce error or success notice regardless of the version of WooCommerce running.
	 *
	 * @param  string $message The text to display in the notice.
	 * @param  string $notice_type The singular name of the notice type - either error, success or notice. [optional]
	 * @since version 1.4.5
	 * @deprecated 2.2.16
	 */
	public static function add_notice( $message, $notice_type = 'success' ) {
		wcs_deprecated_function( __METHOD__, '2.2.16', 'wc_add_notice( $message, $notice_type )' );
		wc_add_notice( $message, $notice_type );
	}

	/**
	 * Print WooCommerce messages regardless of the version of WooCommerce running.
	 *
	 * @since version 1.4.5
	 * @deprecated 2.2.16
	 */
	public static function print_notices() {
		wcs_deprecated_function( __METHOD__, '2.2.16', 'wc_print_notices()' );
		wc_print_notices();
	}

	/**
	 * Workaround the last day of month quirk in PHP's strtotime function.
	 *
	 * @since 1.2.5
	 * @deprecated 2.0
	 */
	public static function add_months( $from_timestamp, $months_to_add ) {
		_deprecated_function( __METHOD__, '2.0', 'wcs_add_months()' );
		return wcs_add_months( $from_timestamp, $months_to_add );
	}

	/**
	 * A flag to indicate whether the current site has roughly more than 3000 subscriptions. Used to disable
	 * features on the Manage Subscriptions list table that do not scale well (yet).
	 *
	 * Deprecated since querying the new subscription post type is a lot more efficient and no longer puts strain on the database
	 *
	 * @since 1.4.4
	 * @deprecated 2.0
	 */
	public static function is_large_site() {
		_deprecated_function( __METHOD__, '2.0' );
		return apply_filters( 'woocommerce_subscriptions_is_large_site', false );
	}

	/**
	 * Returns the total number of Subscriptions on the site.
	 *
	 * @since 1.4
	 * @deprecated 2.0
	 */
	public static function get_total_subscription_count() {
		_deprecated_function( __METHOD__, '2.0' );

		if ( null === self::$total_subscription_count ) {
			self::$total_subscription_count = self::get_subscription_count();
		}

		return apply_filters( 'woocommerce_get_total_subscription_count', self::$total_subscription_count );
	}

	/**
	 * Returns an associative array with the structure 'status' => 'count' for all subscriptions on the site
	 * and includes an "all" status, representing all subscriptions.
	 *
	 * @since 1.4
	 * @deprecated 2.0
	 */
	public static function get_subscription_status_counts() {
		_deprecated_function( __METHOD__, '2.0' );

		$results = wp_count_posts( 'shop_subscription' );
		$count   = array();

		foreach ( $results as $status => $count ) {

			if ( in_array( $status, array_keys( wcs_get_subscription_statuses() ) ) || in_array( $status, array( 'trash', 'draft' ) ) ) {
				$counts[ $status ] = $count;
			}
		}

		// Order with 'all' at the beginning, then alphabetically
		ksort( $counts );
		$counts = array( 'all' => array_sum( $counts ) ) + $counts;

		return apply_filters( 'woocommerce_subscription_status_counts', $counts );
	}

	/**
	 * Takes an array of filter params and returns the number of subscriptions which match those params.
	 *
	 * @since 1.4
	 * @deprecated 2.0
	 */
	public static function get_subscription_count( $args = array() ) {
		_deprecated_function( __METHOD__, '2.0' );

		$args['subscriptions_per_page'] = -1;
		$subscription_count = 0;

		if ( ( ! isset( $args['subscription_status'] ) || in_array( $args['subscription_status'], array( 'all', 'any' ) ) ) && ( isset( $args['include_trashed'] ) && true === $args['include_trashed'] ) ) {

			$args['subscription_status'] = 'trash';
			$subscription_count += count( wcs_get_subscriptions( $args ) );
			$args['subscription_status'] = 'any';
		}

		$subscription_count += count( wcs_get_subscriptions( $args ) );

		return apply_filters( 'woocommerce_get_subscription_count', $subscription_count, $args );
	}

	/**
	 * which was called @see woocommerce_format_total() prior to WooCommerce 2.1.
	 *
	 * Deprecated since we no longer need to support the workaround required for WC versions < 2.1
	 *
	 * @since version 1.4.6
	 * @deprecated 2.0
	 */
	public static function format_total( $number ) {
		_deprecated_function( __METHOD__, '2.0', 'wc_format_decimal()' );
		return wc_format_decimal( $number );
	}

	/**
	 * Displays a notice to upgrade if using less than the ideal version of WooCommerce
	 *
	 * @since 1.3
	 */
	public static function woocommerce_dependancy_notice() {
		_deprecated_function( __METHOD__, '2.1', __CLASS__ . '::woocommerce_inactive_notice()' );
	}
}

WC_Subscriptions::init();


?>
