<?php

/*
Plugin Name: NETOPIA Payments Payment Gateway
Plugin URI: https://www.netopia-payments.ro
Description: accept payments through NETOPIA Payments
Author: Netopia
Version: 1.0
License: GPLv2
*/

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'netopiapayments_init', 0 );
function netopiapayments_init() {
	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
	DEFINE ('NTP_PLUGIN_DIR', plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) . '/' );
	
	// If we made it this far, then include our Gateway Class
	include_once( 'wc-netopiapayments-gateway.php' );

	// Now that we have successfully included our class,
	// Lets add it too WooCommerce
	add_filter( 'woocommerce_payment_gateways', 'add_netopiapayments_gateway' );
	function add_netopiapayments_gateway( $methods ) {
		$methods[] = 'netopiapayments';
		return $methods;
	}

    add_action( 'admin_enqueue_scripts', 'netopiapaymentsjs_init' );

	// Add custom action links
	add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'netopia_action_links' );
	function netopia_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=netopiapayments' ) . '">' . __( 'Settings', 'netopiapayments' ) . '</a>',
		);
		return array_merge( $plugin_links, $links );
	}

    function netopiapaymentsjs_init($hook) {
        if ( 'woocommerce_page_wc-settings' != $hook ) {
            return;
        }
        wp_enqueue_script( 'netopiapaymentsjs', plugin_dir_url( __FILE__ ) . 'js/netopiapayments_.js',array('jquery'),'2.0' ,true);
        wp_enqueue_script( 'netopiatoastrjs', plugin_dir_url( __FILE__ ) . 'js/toastr.min.js',array(),'2.0' ,true);
		wp_enqueue_style( 'netopiatoastrcss', plugin_dir_url( __FILE__ ) . 'css/toastr.min.css',array(),'2.0' ,false);
		wp_enqueue_script( 'netopia_payments_agreement', plugin_dir_url( __FILE__ ) . 'js/netopia_payments_agreement.js',array('jquery'),'2.0' ,true);
		wp_localize_script( 'netopia_payments_agreement', 'checkAddress_ajax', array( 'ajax_url' => admin_url( 'admin-ajax.php' )));
		
	}
	add_action( 'wp_ajax_send_agreement','send_agreement');
	// add_action( 'wp_ajax_send_curl','sendJsonCurl');
}

/**
 * Send agreement Json
 */
function send_agreement() {
	$ntpInstance = new netopiapayments();
	// setLog($ntpInstance->settings);
	$sacKey = $ntpInstance->settings['account_id'];
	
	$ntpDeclare = array (
        'completeDescription' => (bool) $ntpInstance->settings['declaration_description'] === "yes" ? true : false,
        'priceCurrency' =>  (bool) $ntpInstance->settings['declaration_price_currency'] === "yes" ? true : false,
        'contactInfo' =>  (bool) $ntpInstance->settings['declaration_contact_info'] === "yes" ? true : false,
        'forbiddenBusiness' =>  (bool)  $ntpInstance->settings['declaration_forbidden_business'] === "yes" ? true : false
	);

	
	$ntpUrl = array(
        'termsAndConditions' => $ntpInstance->settings['terms_conditions'],
        'privacyPolicy' => $ntpInstance->settings['privacy_policy'],
        'deliveryPolicy' => $ntpInstance->settings['delivery_policy'],
        'returnAndCancelPolicy' => $ntpInstance->settings['return_cancel_policy'],
        'gdprPolicy' => $ntpInstance->settings['gdpr_policy']
	);

	
	
	$ntpImg = array(
        'netopiaLogoLink' =>   (bool) $ntpInstance->settings['netopia_logo'] === "yes" ? true : false,
    );
	
	
	$jsonData = makeActivateJson($sacKey, $ntpDeclare, $ntpUrl, $ntpImg);
	


	$encryptData = encrypt($jsonData);
    
    $encData = array(
        'env_key' => $encryptData['EnvKey'],
        'data'    => $encryptData['EncData']
    );

	$sendFeedback = sendJsonCurl($encData);
    die($sendFeedback);
}

function makeActivateJson($sacKey, $declareatins, $urls, $images) {
    $jsonData = array(
      "sac_key" => $sacKey,
      "agreements" => array(
            "declare" => $declareatins,
            "urls"    => $urls,
            "images"  => $images,
            "ssl"     => ssl_validation(true)
          ),
      "lastUpdate" => date("c", strtotime(date("Y-m-d H:i:s"))), // To have Date & Time format on RFC3339
      "platform" => 'Woocomerce'
    );
    $post_data = json_encode($jsonData, JSON_FORCE_OBJECT);
    return $post_data;
}

/**
 * to check if web site has valid SSl Certificate
 * @param $toSend is define to display or retuen ssl status
 */
function ssl_validation($toSend = false) {
    $temp = false;
    $serverName =   'http://netopia-system.com';
//    $serverName =   $_SERVER['HTTP_HOST'];
    $stream = stream_context_create (array("ssl" => array("capture_peer_cert" => true)));
    $read   = @fopen($serverName, "rb", false, $stream);
    $cont   = @stream_context_get_params($read);
    $var    = @($cont["options"]["ssl"]["peer_certificate"]);
    $result = (!is_null($var)) ? true : false;
    $response = json_encode($result);
    if(!$toSend)
        echo $response;
    else
        return $response;
    wp_die();
}

function getCertificateDir(){
	return dirname(__FILE__)."/netopia/live.AJ98-D7C5-LGCJ-T4D5-MHR1.public.cer";
}

function encrypt($jsonData) {
    $x509FilePath = getCertificateDir();
    $publicKey = openssl_pkey_get_public("file://{$x509FilePath}");
    if($publicKey === false)
      {
        $outEncData = null;
        $outEnvKey  = null;
        $errorMessage = "Error while loading X509 public key certificate! Reason:";
        while(($errorString = openssl_error_string()))
        {
          $errorMessage .= $errorString . "\n";
        }
        throw new \Exception($errorMessage, 'ERROR_LOAD_X509_CERTIFICATE');
      }
    $srcData = $jsonData;
    $publicKeys = array($publicKey);
    $encData  = null;
    $envKeys  = null;
    $result   = openssl_seal($srcData, $encData, $envKeys, $publicKeys);

    if($result === false)
      {
        $outEncData = null;
        $outEnvKey  = null;
        $errorMessage = "Error while encrypting data! Reason:";
        while(($errorString = openssl_error_string()))
        {
          $errorMessage .= $errorString . "\n";
        }
        throw new Exception($errorMessage, 'ERROR_ENCRYPT_DATA');
      }
    $outEncData = base64_encode($encData);
    $outEnvKey  = base64_encode($envKeys[0]);

    return(array("EnvKey"=> $outEnvKey, "EncData"=> $outEncData));
}


function sendJsonCurl($encData) {
    $url = 'https://netopia-payments-user-service-api-fqvtst6pfa-ew.a.run.app/financial/agreement/add2';
    $ch = curl_init($url);

    $payload = json_encode($encData);
	
    // Attach encoded JSON string to the POST fields
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

    // Set the content type to application/json
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));

    // Return response instead of outputting
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Execute the POST request
    $result = curl_exec($ch);

    if (!curl_errno($ch)) {
          switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
              case 200:  # OK
                  $arr = array(
                      'code'    => $http_code,
                      'message' => "You send your request, successfully",
                      'data'    => json_decode($result)
                  );
                  break;
              case 404:  # Not Found
                  $arr = array(
                      'code'    => $http_code,
                      'message' => "You send request to wrong URL"
                  );
                  break;
              case 400:  # Bad Request
                  $arr = array(
                      'code'    => $http_code,
                      'message' => "You send Bad Request"
                  );
                  break;
              case 405:  # Method Not Allowed
                  $arr = array(
                      'code'    => $http_code,
                      'message' => "Your method of sending data are Not Allowed"
                  );
                  break;
              default:
                  $arr = array(
                      'code'    => $http_code,
                      'message' => "Opps! Something happened, verify how you send data & try again!!!"
                  );
          }
      } else {
          $arr = array(
              'code'    => 0,
              'message' => "Opps! There is some problem, you are not able to send data!!!"
          );
      }
    
    // Close cURL resource
    curl_close($ch);
    
    $finalResult = json_encode($arr, JSON_FORCE_OBJECT);
    return $finalResult;
}

function setLog($log) {
	$logPoint = date(" - H:i:s - ")."| ".rand(1,1000)." |";
	ob_start();                    // start buffer capture
	print_r( $log );           // dump the values
	$contents = ob_get_contents(); // put the buffer into a variable
	ob_end_clean();
	   file_put_contents('/var/www/html/woocommerce/wp-content/plugins/netopiaWpLog.log', "$logPoint  - ".$contents."\n", FILE_APPEND | LOCK_EX);
}