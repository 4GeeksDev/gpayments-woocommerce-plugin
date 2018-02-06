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

		$nombre_archivo = "auth.txt";
		if(file_exists($nombre_archivo)){
			//$mensaje = "El Archivo $nombre_archivo se ha modificado";
		}else{
			//$mensaje = "El Archivo $nombre_archivo se ha creado";
		}

		if($archivo = fopen($nombre_archivo, "w")){
			if(fwrite($archivo, $_POST['woocommerce_wc-4gpayments_client_id']." ".$_POST['woocommerce_wc-4gpayments_client_secret']."\n")){
				//echo "Se ha ejecutado correctamente";
			}else{
				//echo "Ha habido un problema al crear el archivo";
			}
			fclose($archivo);
		}
		//const client_id = $_POST['woocommerce_wc-4gpayments_client_id'];
		//const client_secret = $_POST['woocommerce_wc-4gpayments_client_secret'];
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



function rental_options_product_tab_content() {
	global $post;

	if (!$fp = fopen("auth.txt", "r")){
		echo "The file can't be opened";
	}
	$file = "auth.txt";
	$fp = fopen($file, "r");
	$contents = fread($fp, filesize($file));
	fclose($fp);

	$credentials = explode(" ", $contents);

	$Client_Id = trim($credentials[0]);
	$Client_Secret = trim($credentials[1]);

	/*$classname = 'WC_GPayments_Connection';
	$classname::client_id;
	$classname::client_secret;

	$Client_Id = MyClass::client_id;
	$Client_Secret = MyClass::client_secret;*/


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
		echo "Save again your 4Geeks Payments Credencials on" . "<br>";
		echo "Woocommerce/Settings/Checkout/4GPayments -> Save changes button";
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
add_action( 'woocommerce_product_data_panels', 'rental_options_product_tab_content' );


function save_rental_option_field( $post_id ) {
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
		//update_post_meta( $post_id, 'gpayment_plan_option', sanitize_text_field( $_POST['gpayment_plan_option'] ) );
	endif;
}
add_action( 'woocommerce_process_product_meta_simple_rental', 'save_rental_option_field'  );
add_action( 'woocommerce_process_product_meta_variable_rental', 'save_rental_option_field'  );



function hide_attributes_data_panel( $tabs) {

	$tabs['attribute']['class'][] = 'hide_if_simple_rental hide_if_variable_rental';

	return $tabs;

}
add_filter( 'woocommerce_product_data_tabs', 'hide_attributes_data_panel' );
/*
***************************************************************************************************************************************************
***************************************************************************************************************************************************
*/


?>
