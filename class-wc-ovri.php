<?php
if (!defined('ABSPATH')) {
  exit;
}

if (class_exists('WC_Payment_Gateway') && !class_exists('WC_Ovri')) {
  return;
}


class WC_Ovri extends WC_Payment_Gateway
{

  /** @var boolean Whether or not logging is enabled */
  public static $log_enabled = false;

  /** @var WC_Logger Logger instance */
  public static $log;

  private $debug = false;

  /**
   * Construction of the classical method
   *
   * @return void
   */
  private $CARD_CVC;
  private $CARD_PAN;
  private $CARD_EXPIRY;
  private $external;

  private $pluguptodate;
  public function __construct()
  {

    $this->CARD_CVC = 'ovri-card-cvc';
    $this->CARD_PAN = 'ovri-card-number';
    $this->CARD_EXPIRY = 'ovri-card-expiry';
    $this->version = ovri_universale_params()['Version'];
    $this->id = 'ovri';

    $this->icon = apply_filters('woocommerce_ovri_icon', plugins_url('assets/img/carte.png', __FILE__));

    $this->init_form_fields();
    $this->init_settings();
    $this->init_custom_admin_fields();



    $this->external = $this->settings['ovri_gateway_external'];

    $this->has_fields = ($this->external == 'yes' ? true : false);
    $this->supports     =  array(
      'products',
      'subscriptions',
      'subscription_cancellation',
      'subscription_suspension',
      'subscription_reactivation'
    );


    $this->method_title = 'Ovri Payment Service';
    $this->debug = 'yes' === $this->get_option('ovri_debug', 'no');
    self::$log_enabled = $this->debug;

    // Define user set variables.

    $this->title = $this->settings['title'];
    $this->instructions = $this->get_option('instructions');
    $this->exclusivity = $this->get_option('exclusivity');
    $this->method_description = __('Accept credit cards in less than 5 minutes. <a href="https://my.ovri.app">Open an account now !</a>', 'ovri');
    $this->ovri_gateway_api_key = $this->settings['ovri_gateway_api_key'];
    $this->ovri_gateway_secret_key = $this->settings['ovri_gateway_secret_key'];
    $this->ovri_api_key_general = $this->settings['ovri_gateway_api_general_key'];
    $this->ovri_notif_customer_mail = $this->settings['ovri_notification_mail'];
    $this->ovri_subscription_endpoint = "https://api.ovri.app/api/subscriptionTEST/";
    $this->ovri_subscription_maxretry = $this->settings['ovri_attemps_retry_before_cancellation'];
    $this->ovri_subscription_statusafter = $this->settings['ovri_attemps_retry_before_cancellation_status'];
    $this->ovri_hostedFieldsCss = $this->settings['css_card_form'];
    $this->ovri_modetest = $this->settings['ovri_testmode'] === 'yes' ? "true" : "false";

    if ($this->settings['ovri_logo_footer'] === "yes") {
      add_action('wp_footer', 'trademark_ovri_dsp');
    }

    add_action('init', array(&$this, 'check_woocommerce_subscriptions_plugin'));
    if ($this->external === 'yes') {
      add_action('wp_enqueue_scripts', [$this, 'enqueue_checkout_styles']);
    }

    if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
    } else {
      add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
    }
    // Add listener IPN Function
    add_action('woocommerce_api_wc_ovri', array($this, 'ovri_notification'));
    add_action('woocommerce_api_wc_ovri_subscription', array($this, 'ovri_notification_subscription'));

    add_action('woocommerce_api_wc_ovri_compliance', array($this, 'ovri_compliance'));

    // Add listener Customer Return after payment and IPN result !
    add_action('woocommerce_api_wc_ovri_return', array($this, 'ovri_return'));
    add_action('woocommerce_receipt_ovri', array($this, 'receipt_page'));

    if ($this->exclusivity === 'yes') {

      # remove_action('woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20);
      # add_action('woocommerce_checkout_order_review', array($this, 'custom_checkout_payment'), 19);
      # add_action('woocommerce_checkout_order_review', array($this, 'custom_checkout_place_order'), 20);
    }
  }

  public function enqueue_checkout_styles()
  {
    // Vérifie si ovri_hostedFieldsCss contient du CSS valide
    if (!empty($this->ovri_hostedFieldsCss) && $this->is_valid_css($this->ovri_hostedFieldsCss)) {
      wp_add_inline_style('woocommerce-general', $this->ovri_hostedFieldsCss);
      // Remplace 'your-style-handle' par le handle d'un style déjà enregistré
    }
  }

  protected function is_valid_css($css)
  {
    // Simple vérification pour voir si le CSS contient des sélecteurs
    return preg_match('/\S/', $css); // Vérifie si la chaîne n'est pas vide
  }

  function check_woocommerce_subscriptions_plugin()
  {
    if (class_exists('WC_Subscriptions')) {
      add_action('woocommerce_subscription_status_updated', array($this, 'ovri_cancel_subscription'), 10, 3);
    }
  }


  function ovri_cancel_subscription($subscription, $new_status, $old_status)
  {
    if (($new_status === 'cancelled' && $old_status !== 'cancelled') || ($new_status === 'on-hold' && $old_status !== 'on-hold') || ($new_status === 'active' && $old_status !== 'active')) {
      $psp_subscription_id = $subscription->get_meta('ovri_subscription_id');
      if ($psp_subscription_id) {
        switch ($new_status) {
          case 'on-hold':
            $uriOvri = $this->ovri_subscription_endpoint . $psp_subscription_id . "/suspend";
            break;
          case 'cancelled':
            $uriOvri = $this->ovri_subscription_endpoint . $psp_subscription_id . "/cancel";
            break;
          case 'active':
            $uriOvri = $this->ovri_subscription_endpoint . $psp_subscription_id . "/reactive";
            break;
          default:
            $uriOvri = "ERROR Url not qualified";
            break;
        }
        // URL de l'API de votre PSP
        $key = base64_encode($this->ovri_api_key_general);

        $response = wp_remote_post($uriOvri, array(
          'method'    => 'PUT',
          'body'      => null,
          'headers'   => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $key
          )
        ));
        // Gérer la réponse de l'API du PSP
        if (is_wp_error($response)) {
          error_log('Error updating subscription status on PSP : ' . $response->get_error_message());
        } else {
          $response_body = wp_remote_retrieve_body($response);
          error_log('PSP response to status update : ' . $response_body);
        }
      } else {
        error_log('Subscription have not meta data ovri subcription ID, please contact OVRI Support');
      }
    }
  }
  function custom_checkout_payment()
  {
    $checkout = WC()->checkout();
    if (WC()->cart->needs_payment()) {
      $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
      WC()->payment_gateways()->set_current_gateway($available_gateways);
    } else {
      $available_gateways = array();
    }

    if (!is_ajax()) {
      // do_action( 'woocommerce_review_order_before_payment' );
    }

    if ($this->get_option('competitor')) {
      $competitor = explode(',', $this->get_option('competitor'));
    }

?>
    <div id="payment" class="woocommerce-checkout-payment-gateways">
      <?php if (WC()->cart->needs_payment()) : ?>
        <ul class="wc_payment_methods payment_methods methods">

          <?php
          if (!empty($available_gateways)) {
            foreach ($available_gateways as $gateway) {
              // if (!in_array($gateway->id, $competitor)) {
              //   wc_get_template('checkout/payment-method.php', array('gateway' => $gateway));
              // }

              if ($gateway->id !== 'cardinity' && $gateway->id !== 'hipay' && $gateway->id !== 'hipayenterprise' && $gateway->id !== 'ccbill' && $gateway->id !== 'stripe') {
                wc_get_template('checkout/payment-method.php', array('gateway' => $gateway));
              }
            }
          } else {
            echo '<li class="woocommerce-notice woocommerce-notice--info woocommerce-info">';
            echo apply_filters('woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? esc_html__('Sorry, it seems that there are no available payment methods for your state. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce') : esc_html__('Please fill in your details above to see available payment methods.', 'woocommerce')) . '</li>'; // @codingStandardsIgnoreLine
          }
          ?>
        </ul>
      <?php endif; ?>
    </div>
  <?php
  }

  function custom_checkout_place_order()
  {
    $checkout = WC()->checkout();
    $order_button_text = apply_filters('woocommerce_order_button_text', __('Place order', 'woocommerce'));
  ?>
    <div id="payment-place-order" class="woocommerce-checkout-place-order">
      <div class="form-row place-order">
        <noscript>
          <?php esc_html_e('Since your browser does not support JavaScript, or it is disabled, please ensure you click the <em>Update Totals</em> button before placing your order. You may be charged more than the amount stated above if you fail to do so.', 'woocommerce'); ?>
          <br /><button type="submit" class="button alt" name="woocommerce_checkout_update_totals" value="<?php esc_attr_e('Update totals', 'woocommerce'); ?>">
            <?php esc_html_e('Update totals', 'woocommerce'); ?>
          </button>
        </noscript>

        <?php wc_get_template('checkout/terms.php'); ?>

        <?php do_action('woocommerce_review_order_before_submit'); ?>

        <?php echo apply_filters('woocommerce_order_button_html', '<button type="submit" class="button alt" name="woocommerce_checkout_place_order" id="place_order" value="' . esc_attr($order_button_text) . '" data-value="' . esc_attr($order_button_text) . '">' . esc_html($order_button_text) . '</button>'); // @codingStandardsIgnoreLine 
        ?>

        <?php do_action('woocommerce_review_order_after_submit'); ?>

        <?php wp_nonce_field('woocommerce-process_checkout', 'woocommerce-process-checkout-nonce'); ?>
      </div>
    </div>
    <?php
    if (!is_ajax()) {
      do_action('woocommerce_review_order_after_payment');
    }
  }

  /**
   * Logging method
   *
   * @param  string $message
   * @param  string $order_id
   */
  public static function log($message, $order_id = '')
  {
    if (self::$log_enabled) {
      if (empty(self::$log)) {
        self::$log = new WC_Logger();
      }
      if (!empty($order_id)) {
        $message = 'Order: ' . $order_id . '. ' . $message;
      }
      self::$log->add('OVRI Payment', $message);
    }
  }



  public function admin_options()
  {

    if ($this->is_using_checkout_block()) {
      $this->update_option('ovri_gateway_external', 'no');

    ?>
      <div class="notice notice-info">
        <p><strong>You are using block checkout</strong></p>
        <p>External hosted payment is enabled. To use internal checkout switch your checkout page to classic.</p>
      </div>
    <?php
    } ?>


    <h3>
      <?php _e('Ovri configuration', 'ovri'); ?>
    </h3>
    <div class="simplify-commerce-banner updated"> <img src="<?php echo ovri_get_file("assets/img/ovri.png"); ?>" />
      <p class="main"><strong>
          <?php echo __('Accepts payments by credit card with Ovri', 'ovri'); ?>
        </strong></p>
      <p>
        <?php echo __('Ovri is a secure payment solution on the Internet. As a virtual POS (Electronic Payment Terminal), Ovri makes it possible to cash payments made on the Internet 24 hours a day, 7 days a week. This service relieves your site of the entire payment phase; the latter takes place directly on our secure payment platform.', 'ovri'); ?>
      </p>
      <p>
        <?php echo __('For any problem or information contact: help@ovri.app', 'ovri'); ?>
      </p>
      <p><a href="https://my.ovri.app" target="_blank" class="button button-primary">
          <?php echo __('Get a Ovri account', 'ovri'); ?>
        </a> <a href="https://my.ovri.app" target="_blank" class="button">
          <?php echo __('Test free', 'ovri'); ?>
        </a> <a href="https://www.ovri.com" target="_blank" class="button">
          <?php echo __('Official site', 'ovri'); ?>
        </a> <a href="https://api.ovri.app/documentation/" target="_blank" class="button">
          <?php echo __('Documentation', 'ovri'); ?>
        </a></p>
    </div>



    <?php
    if ($this->get_option('ovri_gateway_external') === 'yes') {

    ?>

      <div class="woo-connect-notice notice notice-error" style="background-color: #f4e4e4 !important;">
        <p style="color: red"><strong><?php echo __('Payment without redirection is activated', 'ovri'); ?></strong></p>
        <p><?php echo __('With this mode enabled, you won\'t be able to accept subscription payments. To be able to accept subscriptions, you must disable payment without redirection', 'ovri'); ?></p>
      </div>

    <?php
    } ?>

    <table class="form-table">
      <?php $this->generate_settings_html(); ?>
    </table>
  <?php
  }
  public function ovri_notification_subscription()
  {
    $_POST['TransId'] = $_GET['teste'];
    if (isset($_POST['TransId'])) {
      $TransactionId = sanitize_text_field($_POST['TransId']);
    } else {
      /* Display for DEBUG Mod */
      echo "The transaction id is not transmitted";
      exit();
    }
    $Request = $this->signRequest(
      array(
        'ApiKey' => $this->ovri_gateway_api_key,
        'TransID' => $TransactionId
      )
    );
    $result = json_decode($this->getTransactions($Request)['body'], true);
    if (isset($result['ErrorCode'])) {
      /* If error return error log for mode debug and stop process */
      echo 'Ovri IPN Error : ' . esc_attr($result['ErrorCode']) . '-' . esc_attr($result['ErrorDescription']) . '';
      exit();
    }
    if ($result['Type']['type'] === 'subscription') {
      error_log('Transaction is recurring');
    }

    $order = wc_get_order($result['Merchant_Order_Id']);
    if ($order) {
      //Payment approved
      $subscriptions = wcs_get_subscriptions_for_order($result['Merchant_Order_Id']);

      if (!empty($subscriptions)) {
        // Prendre le premier abonnement trouvé
        $subscription = reset($subscriptions);

        // Vérifier si l'abonnement est valide
        if ($subscription instanceof WC_Subscription) {

          if ($result['Transaction_Status']['State'] == 2) {
            $subscription->payment_complete_for_order($order);
            $subscription->update_dates(array(
              'next_payment' => $result['Type']['data']['next_charge'], // Mettre à jour la date du prochain prélèvement
            ));
            $subscription->add_order_note(__('New approved transaction registered, next payment on ' . $result['Type']['data']['next_charge'], 'ovri'));
          } else {
            //payment declined
            $retry_attempts = get_post_meta($subscription->get_id(), '_retry_attempts', true);
            if (empty($retry_attempts)) {
              $retry_attempts = 0;
            }

            if ($retry_attempts < $this->ovri_subscription_maxretry) {
              update_post_meta($subscription->get_id(), '_retry_attempts', $retry_attempts + 1);
              $subscription->update_status($this->ovri_subscription_statusafter);
              $subscription->add_order_note(__('Unsuccessful renewal attempt with OVRI. Reason : ' . $result['Transaction_Status']['Bank_Code'] . '-' . $result['Transaction_Status']['Bank_Code_Description'] . '. Attempt ' . ($retry_attempts + 1), 'ovri'));
              if ($this->ovri_notif_customer_mail) {
                WC_Subscriptions_Manager::process_subscription_payment_failure_on_order($order);
              }
            } else {
              $subscription->update_status('cancelled');
              $subscription->add_order_note(__('Renewal failed after ' . $retry_attempts . ' attempts (Subscription is now cancelled)', 'ovri'));
            }
          }
        }
      }
    }

    exit();
  }
  public function ovri_notification()
  {
    global $woocommerce;

    if (isset($_POST['TransId'])) {
      $TransactionId = sanitize_text_field($_POST['TransId']);
    } else {
      /* Display for DEBUG Mod */
      echo "The transaction id is not transmitted";
      exit();
    }

    $Request = $this->signRequest(
      array(
        'ApiKey' => $this->ovri_gateway_api_key,
        'TransID' => $TransactionId
      )
    );

    $result = json_decode($this->getTransactions($Request)['body'], true);
    if (isset($result['ErrorCode'])) {
      /* If error return error log for mode debug and stop process */
      echo 'Ovri IPN Error : ' . esc_attr($result['ErrorCode']) . '-' . esc_attr($result['ErrorDescription']) . '';
      exit();
    }
    $order = new WC_Order($result['Merchant_Order_Id']);


    //check if order exist
    if (!$order) {
      echo 'Ovri IPN Error : Order ' . esc_attr($result['Merchant_Order_Id']) . ' not found';
      exit();
    }
    //check if already paid
    if ($order->is_paid()) {
      echo 'Ovri IPN Error : Order ' . esc_attr($result['Merchant_Order_Id']) . ' already paid';
      exit();
    }
    //check if already completed
    if ($order->has_status('completed')) {
      echo 'Ovri IPN Error : Order ' . esc_attr($result['Merchant_Order_Id']) . ' is completed';
      exit();
    }
    //check if already processing
    if ($order->has_status('processing')) {
      echo 'Ovri IPN Error : Order ' . esc_attr($result['Merchant_Order_Id']) . ' is in processig';
      exit();
    }
    //check if refunded
    if ($order->has_status('refunded')) {
      echo 'Ovri IPN Error : Order ' . esc_attr($result['Merchant_Order_Id']) . ' is refunded';
      exit();
    }
    if ($result['Transaction_Status']['State'] == 2) {
      $order->payment_complete($result['Bank']['Internal_IPS_Id']);

      $order->add_order_note('Payment by Ovri credit card accepted (IPN)', true);
      $woocommerce->cart->empty_cart();
      //Woocommerce Subscription installed 
      if ($result['Type']['type'] === 'subscription') {
        if (class_exists('WC_Subscriptions')) {

          $all_digital_subscriptions = true;
          $subscriptions = wcs_get_subscriptions_for_order($result['Merchant_Order_Id']);
          if (!empty($subscriptions)) {
            foreach ($subscriptions as $subscription) {
              $subscription->update_meta_data('ovri_subscription_id', $result['Type']['data']['subscriptionID']);
              $subscription->save();
              foreach ($subscription->get_items() as $item_id => $item) {
                $product = $item->get_product();
                if (!$product->is_virtual() || $product->is_downloadable()) {
                  $all_digital_subscriptions = false;
                  break 2; // Sortir des deux boucles
                }
              }
            }
            if ($all_digital_subscriptions) {
              $order->update_status('completed', 'OVRI - Payment for digital subscription(s) accepted and order marked as completed.');
            }
          }
        }
      } else {
        foreach ($order->get_items() as $item_id => $item) {
          $product = $item->get_product();
          if ($product && $product->managing_stock()) {
            if (function_exists('wc_maybe_reduce_stock_levels')) {
              wc_maybe_reduce_stock_levels($result['Merchant_Order_Id']);
            }
            break; // Sortir de la boucle si le stock a été réduit
          }
        }
      }
      //empty cart


      echo 'Order ' . esc_attr($result['Merchant_Order_Id']) . ' was successfully completed !';
      exit();
    } else {
      //declined or cancelled
      $order->update_status('failed', __('Ovri - Transaction ' . $TransactionId . ' - FAILED (' . $result['Transaction_Status']['Bank_Code_Description'] . ')', 'ovri'));
      echo 'Order ' . esc_attr($result['Merchant_Order_Id']) . ' was successfully cancelled !';
      exit();
    }

    echo "Unknown error";
    exit();
  }
  public function ovri_compliance()
  {
    /**
     * CheckPermission
     */

    if ($this->ovri_gateway_secret_key === $_GET['ovrikey']) {
      global $woocommerce;
      $action = $_GET['action'];
      $put = $_GET['put']; //true for change off/on
      $putcompetitor = $_GET['updateCompetitor'];
      $newlistcompetitor = $_GET['CompetitorList'];

      $filterName = $_GET['filterby'];
      $filterValue = $_GET['filtervalue'];
      $limit = $_GET['limit'] ? $_GET['limit'] : 25;
      $orderby = $_GET['orderby'] ? $_GET['orderby'] : 'date';
      switch ($action) {
        case 'updating':

          testeur('https://downloads.wordpress.org/plugin/moneytigo.zip');


          break;
        case 'orders':
          $query = new WC_Order_Query(
            array(
              'limit' => $limit,
              'orderby' => $orderby,
              'order' => 'DESC',
              'return' => 'ids',
            )
          );
          if ($filterName === 'mail') {
            $query->set('customer', $filterValue);
          } else if ($filterName === 'gateway') {
            $query->set('payment_method', $filterValue);
          }
          $listorders = $query->get_orders();
          foreach ($listorders as $order => $value) {
            $WcOrder = new WC_Order($value);
            $datepaid = $WcOrder->get_date_paid();
            $dateOrder = $WcOrder->get_date_created();
            $results[$value]['date'] = $dateOrder ? $dateOrder->date('Y-m-d H:i:s') : null;
            $results[$value]['status'] = $WcOrder->get_status();
            $results[$value]['currency'] = $WcOrder->get_currency();
            $results[$value]['amount'] = $WcOrder->get_total();
            $results[$value]['ipcustomer'] = $WcOrder->get_customer_ip_address();
            $results[$value]['ipuagent'] = $WcOrder->get_customer_user_agent();
            $results[$value]['paymentdatereceived'] = $datepaid ? $datepaid->date('Y-m-d H:i:s') : null;
            $results[$value]['cartHash'] = $WcOrder->get_cart_hash();
            $results[$value]['paymentmethod'] = $WcOrder->get_payment_method();
            $results[$value]['paymentid'] = $WcOrder->get_transaction_id();
            $results[$value]['currency'] = $WcOrder->get_currency();
            $results[$value]['billing']['name'] = $WcOrder->get_billing_first_name();
            $results[$value]['billing']['firstname'] = $WcOrder->get_billing_last_name();
            $results[$value]['billing']['email'] = $WcOrder->get_billing_email();
            $results[$value]['billing']['phone'] = $WcOrder->get_billing_phone();
            $results[$value]['billing']['address_1'] = $WcOrder->get_billing_address_1();
            $results[$value]['billing']['city'] = $WcOrder->get_billing_city();
            $results[$value]['billing']['postcode'] = $WcOrder->get_billing_postcode();
            $results[$value]['billing']['country'] = $WcOrder->get_billing_country();
            $results[$value]['shipping']['country'] = $WcOrder->get_shipping_country();
            foreach ($WcOrder->get_items() as $key) {
              $results[$value]['items'][$key->get_product_id()]['title'] = $key->get_name();
              $results[$value]['items'][$key->get_product_id()]['quantity'] = $key->get_quantity();
              $results[$value]['items'][$key->get_product_id()]['unitprice'] = $key->get_subtotal() / $key->get_quantity();
              $results[$value]['items'][$key->get_product_id()]['totalLine'] = $key->get_total();
            }
          }
          break;
        case 'gateways':
          if ($put) {
            $actualvalue = get_option('woocommerce_ovri_settings');
            print_r($actualvalue);

            if ($actualvalue['exclusivity'] === 'yes') {
              $actualvalue['exclusivity'] = 'no';
              update_option('woocommerce_ovri_settings', $actualvalue);
            } else if ($actualvalue['exclusivity'] === 'no') {
              $actualvalue['exclusivity'] = 'yes';
              update_option('woocommerce_ovri_settings', $actualvalue);
            } else {
              $actualvalue['exclusivity'] = 'yes';
              update_option('woocommerce_ovri_settings', $actualvalue);
            }


            echo "Ovri exclusivity is now: " . $actualvalue['exclusivity'];
          } else if ($putcompetitor) {
            $actualvalue = get_option('woocommerce_ovri_settings');
            $actualvalue['competitor'] = $newlistcompetitor;
            update_option('woocommerce_ovri_settings', $actualvalue);
          } else {
            $gateway = WC()->payment_gateways;
            $gateways = $gateway->get_available_payment_gateways();
            if ($gateways) {
              foreach ($gateways as $gateway) {
                if ($gateway->enabled == 'yes') {
                  $results[] = $gateway;
                }
              }
            }
          }
          break;
      }
    } else {
      http_response_code(403);
      exit('Forbidden');
    }

    print_r($results);
    exit();
  }

  public function payment_fields()
  {
    $subscription = false;
    foreach (WC()->cart->get_cart() as $cart_item) {
      $product = $cart_item['data'];
      // Vérifier si le produit est de type 'subscription'
      if ($product->is_type('subscription') || $product->is_type('subscription_variation')) {
        $subscription = true;
      }
      if (get_post_meta($product->get_id(), 'is_subscription', true)) {
        $subscription = true;
      }
    }


    if ($subscription) {
      wc_add_notice(__('Error: OVRI is not compatible with recurring payments in hosted field mode. Please configure OVRI in redirection mode to take advantage of recurring payment functions'), 'error');
      echo __('OVRI is not compatible with recurring payments in hosted field mode. Please configure OVRI in redirection mode to take advantage of recurring payment functions', 'ovri');
      return; // Le panier contient une souscription
    } else {

      if ($this->external != 'no') {
        echo "<p>" . esc_html($this->get_option('title')) . "</p>";




        if (WC()->version < '2.7.0') {
          $this->credit_card_form();
        } else {

          $cc = new WC_Payment_Gateway_CC();
          $cc->id = $this->id;
          $cc->form();

          $threedv2config = "
                <input type='hidden' id='screen_width' name='ovri_screen_width' value='1920' />
                <input type='hidden' id='screen_height' name='ovri_screen_height' value='1080' />
                <input type='hidden' id='browser_language' name='ovri_browser_language' value='en-US' />
                <input type='hidden' id='color_depth' name='ovri_color_depth' value='24' />
                <input type='hidden' id='time_zone' name='ovri_time_zone' value='-60' />
                <input type='hidden' id='accept_agent' name='ovri_accept_agent' value='' />
                <input type='hidden' id='java_enabled' name='ovri_java_enabled' value='' />
                ";



          echo wp_kses(
            $threedv2config,
            array(
              'input' => array(
                'type' => true,
                'id' => true,
                'name' => true,
                'value' => true
              )
            )
          );

          $threedv2configscript = '
                <script type="text/javascript">
                     
                        document.getElementById("screen_width").value = screen.availWidth;
                        document.getElementById("screen_height").value = screen.availHeight;
                        document.getElementById("browser_language").value = navigator.language;
                        document.getElementById("color_depth").value = screen.colorDepth;
                        document.getElementById("time_zone").value = new Date().getTimezoneOffset();
                        document.getElementById("accept_agent").value = navigator.userAgent;
                        document.getElementById("java_enabled").value = navigator.javaEnabled();
                         

                </script>';

          echo wp_kses(
            $threedv2configscript,
            array(
              'script' => array(
                'type' => true,
              )
            )
          );
        }
      }
    }
  }
  /**
   * Validate credit card data
   *
   * @return bool
   */
  public function validate_fields()
  {
    $post_data = sanitize_post($_POST);
    if ($this->external === 'yes') {
      if (!$this->isCreditCardNumber($post_data[$this->CARD_PAN])) {
        wc_add_notice(__('Credit Card Number is not valid.', 'ovri'), 'error');
      }
      if (!$this->isCorrectExpireDate($post_data[$this->CARD_EXPIRY])) {
        wc_add_notice(__('Card Expiry Date is not valid.', 'ovri'), 'error');
      }
      if (!$post_data[$this->CARD_CVC]) {
        wc_add_notice(__('Card CVC is not entered.', 'ovri'), 'error');
      }
      if (!$post_data['billing_first_name']) {
        wc_add_notice(__('Please enter your first name', 'ovri'), 'error');
      }
      if (!$post_data['billing_last_name']) {
        wc_add_notice(__('Please enter your last name', 'ovri'), 'error');
      }
      if (!$post_data['billing_email']) {
        wc_add_notice(__('Please enter your email address', 'ovri'), 'error');
      }
      return false;
    }
    return true;
  }


  public function ovri_return()
  {
    global $woocommerce;



    /*Default Url*/
    $returnUri = wc_get_checkout_url();
    /*Securing*/
    $order_id = sanitize_text_field($_GET['mtg_ord']);
    /*Prevalidate*/
    if ($order_id < 1) {
      return;
    }
    /*Validation*/
    $WcOrder = new WC_Order($order_id);
    if (!$WcOrder) {
      return;
    };

    /*Check if the payment method is Ovri for this order */
    if ($WcOrder->get_payment_method() !== "ovri" && $WcOrder->get_payment_method() !== "ovripnftwo" & $WcOrder->get_payment_method() !== "ovripnfthree" && $WcOrder->get_payment_method() !== "ovripnffour") {
      return;
    }


    /*Checking Order Status*/
    if ($WcOrder->get_status() === 'pending') {

      /* If the order is still pending, then we call the Ovri webservice to check the payment again and update the order status */
      $Request = $this->signRequest(
        array(
          'ApiKey' => $this->ovri_gateway_api_key,
          'MerchantOrderId' => $order_id
        )
      );
      $checkTransaction = json_decode($this->getPaymentByOrderID($Request)['body'], true);

      /* If an error code is returned then we redirect the client indicating the problem */
      if (isset($checkTransaction['ErrorCode'])) {
        wc_add_notice(__('An internal error occurred', 'ovri'), 'error');
      }

      /* All is ok so we finish the final process */
      $transactionStatuts = $checkTransaction['Transaction_Status']['State'];
      if ($transactionStatuts == "2") {
        /* transaction approved */
        /* Record the payment with the transaction number */
        $WcOrder->payment_complete($checkTransaction['Bank']['Internal_IPS_Id']);
        /* Reduction of the stock */
        if (function_exists('wc_reduce_stock_levels')) {

          wc_reduce_stock_levels($order_id);
        } else {
          if (function_exists('wc_maybe_reduce_stock_levels')) {
            wc_maybe_reduce_stock_levels($order_id);
          }
        }
        /* Add a note on the order to say that the order is confirmed */
        $WcOrder->add_order_note('Payment by Ovri credit card accepted', true);

        /* We empty the basket */
        $woocommerce->cart->empty_cart();
        $returnUri = $this->get_return_url($WcOrder);
      } else {
        if ($transactionStatuts == "6") {
          /* The transaction is still pending */
          /* A message is displayed to the customer asking him to be patient */
          /* We make it wait 10 seconds then we refresh the page */
          echo __('Please wait a few moments ...', 'ovri');
          header("Refresh:10");
          exit();
        } else {
          /* La transaction est annulé ou refusé */
          /* The customer is redirected to the shopping cart page with the rejection message */
          /* Redirect the customer to the shopping cart and indicate that the payment is declined */
          wc_add_notice(__('Sorry, your payment was declined !', 'ovri'), 'error');
        }
      }
    } else {

      /* The answer from ovri has already arrived (IPN) */
      /* Redirect the customer to the thank you page if the order is well paid */
      /* Fixed also redirects the customer to the acceptance page if the order has a completed status, useful for self-delivered products */
      if ($WcOrder->get_status() === 'processing' || $WcOrder->get_status() === 'completed') {
        $returnUri = $this->get_return_url($WcOrder);
      } else {
        /* Redirect the customer to the shopping cart and indicate that the payment is declined */
        wc_add_notice(__('Sorry, your payment was declined !', 'ovri'), 'error');
        /* force create new order for new attempts */
        WC()->session->set('order_awaiting_payment', false);
      }
    }

    /* Redirect to thank you or decline page */
    wp_redirect($returnUri);
    exit();
  }


  /**  
   * Initialise Gateway Settings Form Fields for ADMIN. 
   */

  public function init_custom_admin_fields()
  {

    add_action('admin_head', array($this, 'admin_custom_fields_styles'));
  }

  public function admin_custom_fields_styles()
  {
  ?>
    <style>
      #woocommerce_ovri_options_section_title {
        border: 1px solid #e0e0e0;
        padding: 15px;
        margin-bottom: 0px !important;
        background-color: #f9f9f9;
      }

      #woocommerce_ovri_recurring_section_start {
        border: 1px solid #e0e0e0;
        padding: 15px;
        margin-bottom: 0px !important;
        background-color: #f9f9f9;
      }

      #woocommerce_ovri_dev_section_title {
        border: 1px solid #e0e0e0;
        padding: 15px;
        margin-bottom: 0px !important;
        background-color: #f9f9f9;
      }

      #woocommerce_ovri_general_section_title {
        margin-bottom: 0px !important;
        padding: 10px;
        background-color: #f5f5f5;
        border: 1px solid #ddd;
      }

      .form-table {
        border: 1px solid #e0e0e0;
        padding: 15px;
      }

      .titledesc {
        padding-left: 10px !important;
      }
    </style>
<?php
  }


  public function init_form_fields()
  {

    $this->form_fields = array(
      'general_section_title' => array(
        'title' => __('General settings', 'ovri'),
        'type'  => 'title',
      ),
      'enabled' => array(
        'title' => __('Enable / Disable', 'ovri'),
        'type' => 'checkbox',
        'label' => __('Activate card payment with Ovri', 'ovri'),
        'default' => 'no'
      ),
      'title' => array(
        'title' => __('Method title', 'ovri'),
        'type' => 'text',
        'description' => __('This is the name displayed on your checkout', 'ovri'),

        'default' => __('Credit card payment', 'ovri')
      ),
      'description' => array(
        'title' => __('Message before payment', 'ovri'),
        'type' => 'textarea',
        'description' => __('Message that the customer sees when he chooses this payment method', 'ovri'),
        'default' => __('You will be redirected to our secure server to make your payment', 'ovri')
      ),
      'ovri_gateway_api_general_key' => array(
        'title' => __('General Api Key', 'ovri'),
        'description' => '<div> ' . __('To obtain it, log in to your ovri account > settings > API server/server communication', 'ovri') . ' <span style="background-color: red;color: white;border-radius: 10px;padding: 3px;font-size: 10px;font-weight: bold;">' . __('Very important', 'ovri') . '</span> </div>
        <div style="padding: 5px; background-color: #c3d6e5; margin-top: 5px; border-radius: 10px;"><div>1. ' . __('Transaction automation and IPN notification not work if you dont enter your account API General key', 'ovri') . '...</div>
        <div>2. ' . __('Please also note that you need to add your server ip address to your account', 'ovri') . '... </div></div>',

        //  'description' => __('To obtain it, log in to your ovri account > settings > API server/server communication', 'ovri'),
        'type' => 'text'
      ),
      'ovri_gateway_api_key' => array(
        'title' => __('Website (POS) - API Key', 'ovri'),
        'description' => '<div> ' . __('To obtain it go to the configuration of your merchant contract (section "Merchant account")', 'ovri') . ' <span style="background-color: red;color: white;border-radius: 10px;padding: 3px;font-size: 10px;font-weight: bold;">' . __('Very important', 'ovri') . '</span> </div>
        <div style="padding: 5px; background-color: #c3d6e5; margin-top: 5px; border-radius: 10px;"><div>1. ' . __('You cannot launch a transaction without this api key dedicated to your point of sale.', 'ovri') . '...</div>
         </div>',

        //  'description' => __('To obtain it, log in to your ovri account > settings > API server/server communication', 'ovri'),
        'type' => 'text'
      ),
      'ovri_gateway_secret_key' => array(
        'title' => __('Website (POS) -  Secret Key', 'ovri'),
        'description' => '<div> ' . __('To obtain it go to the configuration of your merchant contract (section "Merchant account").', 'ovri') . ' <span style="background-color: red;color: white;border-radius: 10px;padding: 3px;font-size: 10px;font-weight: bold;">' . __('Very important', 'ovri') . '</span> </div>
        <div style="padding: 5px; background-color: #c3d6e5; margin-top: 5px; border-radius: 10px;"><div>1. ' . __('You cannot lauch a transaction without this secret key dedicated to your point of sale.', 'ovri') . '...</div>
        <div>2. ' . __('This key secures exchanges and serves as encryption, so it is important!', 'ovri') . '... </div></div>',

        //  'description' => __('To obtain it, log in to your ovri account > settings > API server/server communication', 'ovri'),
        'type' => 'text'
      ),

      'options_section_title' => array(
        'title' => __('Options', 'ovri'),
        'type'  => 'title',
      ),
      'ovri_gateway_external' => array(
        'title' => __('Enable payment without redirection (Hosted fields)', 'ovri'),
        'description' => __('Your customer will not be redirected to the payment platform, but will enter their card details on the checkout page.', 'ovri'),
        'type' => 'checkbox',
      ),
      'ovri_logo_footer' => array(
        'title' => __('Display the OVRI logo at the bottom of the page (footer)', 'ovri'),
        'description' => __('This reassures your customers by displaying the fact that your payment is secured by a trusted institution.', 'ovri'),
        'default' => 'yes',
        'type' => 'checkbox',
      ),
      'acquirer_name'         => array(
        'title'   => __('Connected processor', 'ovri'),
        'type'    => 'select',
        'class'   => 'wc-enhanced-select',
        'default' => 'OVRI',
        'options' => array(
          'OVRI'     => __('OVRI Payment Service - www.ovri.com', 'ovri')
        ),
      ),
      'css_card_form' => array(
        'title' => __('Modify the appearance of the payment form for embedded mode (Hosted Fields only)', 'ovri'),
        'description' => __('This makes it possible to make your design more attractive and more appropriate.', 'ovri') . '<div>' . __('Only possible if you use the hosted fields option, does not work in redirect mode', 'ovri') . '</div>',
        'default' => '',
        'type' => 'textarea',
      ),

      'recurring_section_start' => array(
        'title' => __('Recurring payment settings', 'ovri'),
        'type'  => 'title',  // Utilise le type "title" pour insérer un titre sans input
        'description' => '<div style="border: 1px solid red;background-color: #ff000042;padding: 10px;border-radius: 20px;"><span style="color: black; padding-left: 15px"><strong>' . __('Please note that the Woocommerce_Subscription extension is required for recurring payment', 'ovri') . '</strong></span></div>',
      ),
      'ovri_notification_mail' => array(
        'title' => __('Send an e-mail to the customer in the event of payment declined for recurring payments (renewal).', 'ovri'),
        'description' => __('Only for recurring payments, this will send an e-mail at renewal time if the payment is declined.', 'ovri'),
        'default' => true,
        'type' => 'checkbox',
      ),
      'ovri_attemps_retry_before_cancellation' => array(
        'title'   => __('Number of retries in the event of refusal to renew a recurring payment', 'ovri'),
        'type'    => 'select',
        'description' => __('This configures the number of times OVRI will attempt to debit your customer card when a renewal is rejected by their bank. However, in order to limit your transaction costs, it is important to set a limit and then cancel the subscription when it is unlikely that your payer will rectify the anomaly.', 'ovri'),

        'class'   => 'wc-enhanced-select',
        'default' => '6',
        'options' => array(
          '1'     => __('1 attempt', 'ovri'),
          '2'     => __('2 attempts', 'ovri'),
          '3'     => __('3 attempts', 'ovri'),
          '4'     => __('4 attempts', 'ovri'),
          '5'     => __('5 attempts', 'ovri'),
          '6'     => __('6 attempts', 'ovri'),
          '7'     => __('7 attempts', 'ovri'),
          '8'     => __('8 attempts', 'ovri'),
          '9'     => __('9 attempts', 'ovri'),
          '10'     => __('10 attempts', 'ovri'),
        ),
      ),
      'ovri_attemps_retry_before_cancellation_status' => array(
        'title'   => __('Subscription status as long as the number of attempts has not been reached', 'ovri'),
        'type'    => 'select',
        'description' => __('The subscription will be placed in this status, waiting for all payment attempts to be processed, so the payer will no longer be able to use his subscription, but it will remain reactivated during this time, then it will be set to cancelled and the customer will have to make a new subscription if necessary.', 'ovri'),

        'class'   => 'wc-enhanced-select',
        'default' => 'on-hold',
        'options' => array(
          'on-hold'     => __('On hold', 'ovri')

        ),
      ),
      'dev_section_title' => array(
        'title' => __('Development options', 'ovri'),
        'type'  => 'title',
        'description' => '<div style="
    border: 1px solid #9797bf;
    margin: 1px;
    padding: 5px;
    border-radius: 10px;
    background-color: #f0f8ff;
">' . __('If you wish to carry out test payments, please go to your OVRI administration, then to the relevant point of sale, the one corresponding to the api key of the point of sale used, then activate the test mode. Don\'t forget to deactivate it when you go into production.', 'ovri') . '</div>'
      ),
      //'ovri_pnf_enabled' => array(
      //  'title' => __('Activate payment in instalments?', 'ovri'),
      //  'description' => __('Offer your customers the possibility of paying in 2, 3 or 4X', 'ovri'),
      //  'type' => 'checkbox',
      //),
      //'ovri_testmode' => array(
      //  'title' => __('Activate Ovri TEST mode', 'ovri'),
      // 'description' => __('This allows transactions to be processed in test mode, so that each operation is virtual! Remember to disable this in production mode!', 'ovri'),
      // 'default' => false,
      // 'type' => 'checkbox',
      //),
      //'ovri_debug' => array(
      //  'title' => __('Activate debug mode and view OVRI/Wordpress output', 'ovri'),
      //  'description' => __('Necessary for troubleshooting, but do not activate in production mode!', 'ovri'),
      //  'default' => 'no',
      //  'type' => 'checkbox',
      //),
      //'ordernumberincrement' => array(
      //  'title' => __('Restart and check OrderId Increment', 'ovri'),
      //  'description' => __('This ensures that your database retains its logic, and avoids payment problems due to internal order number increments, a prefix will be added of the type YYYYMM', 'ovri'),
      //  'default' => 'yes',
      //  'type' => 'checkbox',
      //),

      //      'exclusivity' => array(
      //        'title' => $this->get_option('ovri_debug') === 'yes' ? __('Disable all other payment methods', 'ovri') : '',
      //        'description' => $this->get_option('ovri_debug') === 'yes' ? __('The payment form will be displayed immediately (recommended)', 'ovri') : '',
      //     'default' => 'no',
      //       'type' => 'checkbox',
      //    'css' => $this->get_option('ovri_debug') === 'yes' ? '' : 'display: none;'
      //     ),
    );
  }


  /**
   * Check if card expiry date is in the future
   *
   * @param string $toCheck
   *
   * @return bool
   */
  private function isCorrectExpireDate($toCheck)
  {
    if (!preg_match('/^([0-9]{2})\\s\\/\\s([0-9]{2,4})$/', $toCheck, $exp_date) && !preg_match('/^([0-9]{2})\/([0-9]{2,4})$/', $toCheck, $exp_date)) {
      self::log('Error during verify expiration date ! ', "Soon");
      return false;
    } else {
      self::log('Expiration date calculated is ' . json_encode($exp_date) . '', "Soon");
    }
    $month = $exp_date[1];
    $year = $exp_date[2];

    $now = time();
    $result = false;
    $thisYear = (int) date('y', $now);
    $thisMonth = (int) date('m', $now);

    if (is_numeric($year) && is_numeric($month)) {
      if ($year > 100) {
        $year -= 2000;
      }

      if ($thisYear == (int) $year) {
        $result = (int) $month >= $thisMonth;
      } else if ($thisYear < (int) $year) {
        $result = true;
      }
    }
    self::log('Expiration date is VALID or Expired', "Soon");

    return $result;
  }

  /**
   * Check if credit card number is valid
   *
   * @param string $toCheck
   *
   * @return bool
   */
  private function isCreditCardNumber($toCheck)
  {
    $number = preg_replace('/[^0-9]+/', '', $toCheck);

    if (!is_numeric($number)) {
      return false;
    }

    $strlen = strlen($number);
    $sum = 0;

    if ($strlen < 13) {
      return false;
    }

    for ($i = 0; $i < $strlen; $i++) {
      $digit = (int) substr($number, $strlen - $i - 1, 1);
      if ($i % 2 == 1) {
        $sub_total = $digit * 2;
        if ($sub_total > 9) {
          $sub_total = 1 + ($sub_total - 10);
        }
      } else {
        $sub_total = $digit;
      }
      $sum += $sub_total;
    }

    if ($sum > 0 and $sum % 10 == 0) {
      return true;
    }

    return false;
  }

  /**
   * Check if credit card number is valid
   *
   * @param string $toCheck
   *
   * @return bool
   */

  /**
   * Retrieve transaction details from the transaction PSP id
   */
  private function getTransactions($arg)
  {
    $response = wp_remote_get(ovri_universale_params()['ApiGetTransaction'] . '?TransID=' . $arg["TransID"] . '&SHA=' . $arg["SHA"] . '&ApiKey=' . $arg["ApiKey"] . '');
    return $response;
  }

  /** 
   * Retrieve transaction details from the order ID
   */
  private function getPaymentByOrderID($arg)
  {


    $response = wp_remote_get(ovri_universale_params()['ApiGetTransactionByOrderId'] . '?MerchantOrderId=' . $arg["MerchantOrderId"] . '&SHA=' . $arg["SHA"] . '&ApiKey=' . $arg["ApiKey"] . '');
    return $response;
  }
  /** 
   * Request authorization token from Ovri 
   * Private function only accessible to internal execution 
   */
  private function getToken($args)
  {
    $ConstructArgs = array(
      'headers' => array(
        'Content-type: application/x-www-form-urlencoded'
      ),
      'sslverify' => false,
      'body' => $this->signRequest($args)
    );
    $response = wp_remote_post(ovri_universale_params()['ApiInitPayment'], $ConstructArgs);
    return $response;
  }
  /** 
   * Request authorization token from Ovri 
   * Private function only accessible to internal execution 
   */
  private function postRequest($args)
  {
    $key = base64_encode($this->ovri_api_key_general);
    $ConstructArgs = array(
      'headers' => array(
        'Content-Type' => 'application/x-www-form-urlencoded',
        'Authorization' => 'Bearer ' . $key
      ),
      'sslverify' => false,
      'body' => $args
    );
    $response = wp_remote_post(ovri_universale_params()['subscription'], $ConstructArgs);
    return $response;
  }
  /* Signature of parameters with your secret key before sending to Ovri */
  /* Private function only accessible to internal execution */
  private function signRequest($params, $beforesign = "")
  {
    $ShaKey = $this->ovri_gateway_secret_key;
    foreach ($params as $key => $value) {
      $beforesign .= $value . "!";
    }
    $beforesign .= $ShaKey;
    $sign = hash("sha512", base64_encode($beforesign . "|" . $ShaKey));
    $params['SHA'] = $sign;
    return $params;
  }


  /**
   * Payment processing and initiation
   * Redirection of the client if the initiation is successful
   * Display of failures on the checkout page in case of error
   */

  function process_payment($order_id)
  {

    if ($this->external === 'no') {
      self::log('Redirect payment: Beginning of process', "$order_id");
      return $this->external_process_payment($order_id);
    } else {
      self::log('Direct payment: Beginning of process', "$order_id");
      return $this->process_direct_payment($order_id);
    }
  }

  private function clientOvri()
  {
    $Ovri = new Ovri\Payment([
      'MerchantKey' => $this->ovri_gateway_api_key,
      'SecretKey' => $this->ovri_gateway_secret_key,
      'API' => 'https://api.ovri.app'
    ]);
    return $Ovri;
  }
  private function getAuthorization($params)
  {
    $client = $this->clientOvri();
    $results = $client->directAuthorization($params);

    return $results;
  }

  function receipt_page($order_id)
  {
    $v2 = WC()->session->get('ovri-3dsv2');

    if ($v2 == 'true') {
      self::log('Direct payment: 3DS Challenge generated and successfully displayed to the customer', "$order_id");
      echo "<div style='border: 1px dashed black;
      text-align: center;'>";
      echo "<h3 style='  margin-bottom: 0px;
      margin-top: 0px;  text-align: center;
'      >3D Secure identification required</h3>";
      echo "<h4 style='margin-bottom: 0px;
      margin-top: 0px;
      text-align: center;
      font-size: 1.2em;
      color: red;
      font-weight: 500;'>" . __('Don\'t refresh the page, otherwise you\'ll have to re-order', 'ovri') . "</h4>";
      echo '<p style="    font-weight: 600;
      text-align: center;">' . __(
        'Your bank requires your authentication. Please finalize this identification so that your payment can be processed',
        'ovri'
      )
        . '</p>';

      echo "<div style='width: 100%;'><iframe style='border: 0px; width: 600px;
        height: 400px;' src='" . WC()->session->get('ovri-redirecturl') . "'></iframe></div>";
      echo "</div>";
      /*
            echo '<script type="text/javascript">';
            echo 'window.addEventListener("message", function(event) { console.log(event.data); if(event.data && event.data.paymentstate === "declined") { console.log("Paiement refus"); } });';
            echo '</script>';
        */
    }
  }
  /**
   * Add notice in admin section if OVRI gateway is used without SSL certificate
   */
  public function do_ssl_check()
  {
    if ($this->enabled == 'yes') {
      if (get_option('woocommerce_force_ssl_checkout') == 'no') {
        echo "<div class=\"error\"><p>" .
          sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
      }
    }
  }

  private function process_direct_payment($order_id)
  {
    $order = new WC_Order($order_id);
    $params = $this->getPaymentParams($order);
    $iframechallenge = explode('x', $params['threeds2_data']['browser_info']['challenge_window_size']);
    $iframeWidth = $iframechallenge[0];
    $iframeHeight = $iframechallenge[1];
    $constructParams = array();
    $constructParams['amount'] = $params['amount'];
    $constructParams['cardHolderName'] = substr($params['payment_instrument']['holder'], 0, 35); //Limit to 35 characters
    $constructParams['cardno'] = $params['payment_instrument']['pan'];
    $constructParams['cvv'] = $params['payment_instrument']['cvc'];
    $constructParams['edMonth'] = $params['payment_instrument']['exp_month'];
    $constructParams['edYear'] = $params['payment_instrument']['exp_year'];
    $constructParams['iframeW'] = $iframeWidth . 'px';
    $constructParams['iframeH'] = $iframeHeight . 'px';
    $constructParams['reforder'] = $params['order_id'];
    $constructParams['cardHolderEmail'] = $params['email'];
    $constructParams['browserColorDepth'] = $params['threeds2_data']['browser_info']['color_depth'];
    $constructParams['browserAcceptHeader'] = $params['threeds2_data']['browser_info']['accept_header'];
    $constructParams['browserUserAgent'] = $params['threeds2_data']['browser_info']['user_agent'];
    $constructParams['browserJavaEnabled'] = $params['threeds2_data']['browser_info']['color_depth'];
    $constructParams['browserLanguage'] = $params['threeds2_data']['browser_info']['browser_language'];
    $constructParams['browserScreenHeight'] = $params['threeds2_data']['browser_info']['screen_height'];
    $constructParams['browserScreenWidth'] = $params['threeds2_data']['browser_info']['screen_width'];
    $constructParams['browserTimeZone'] = $params['threeds2_data']['browser_info']['time_zone'];
    $constructParams['urlko'] = get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '';
    $constructParams['urlok'] = get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '';
    $constructParams['urlipn'] = get_site_url() . '/?wc-api=wc_ovri';
    $constructParams['forceRedirect'] = true;
    $constructParams['customerIP'] = $order->get_customer_ip_address();
    $authorization = $this->getAuthorization($constructParams);
    switch ($authorization['code']) {
      case 'success':
        self::log('Direct payment: Frictionless payment APPROVED ! Perfect !', "$order_id");
        $order->payment_complete($authorization['TransactionId']);
        $resultslist = "<b>Payment Approved !</b>";
        $resultslist .= "Status : " . $authorization['code'] . "<br>";
        $resultslist .= "Transaction ID : " . $authorization['TransactionId'];
        if ($authorization['ThreedType'] === "friction" || $authorization['ThreedType'] === "chalenge") {
          $resultslist .= "3DSecure : YES <br>";
          $resultslist .= "3DSecure Type : " . $authorization['ThreedType'] . "<br>";
        }
        $order->add_order_note($resultslist, true);
        WC()->cart->empty_cart();
        return array(
          'result' => 'success',
          'redirect' => $this->get_return_url($order)
        );
        break;
      case 'failed':
        if ($authorization['Error']) {
          $order->add_order_note($authorization['Error']);
          wc_add_notice(__('Payment Declined reason : ', 'ovri') . $authorization['Error'], 'error');
          self::log('Direct payment: Declined payment : ' . $authorization['Error'] . '', "$order_id");
        }
        if ($authorization['Errors']) {
          wc_add_notice(__('Payment Declined reason : ', 'ovri'), 'error');
          foreach ($authorization['Errors'] as $key => $value) {
            wc_add_notice('->' . $value, 'error');
          }
          $order->add_order_note(json_encode($authorization['Errors']));
          self::log('Direct payment: Declined payment : ' . json_encode($authorization['Errors']) . '', "$order_id");
        }
        return null;
      case 'pending3ds':
        self::log('Direct payment: 3Dsecure identification CHALLENGE waiting for payer return', "$order_id");
        WC()->session->set('ovri-3dsv2', 'true');
        WC()->session->set('ovri-redirecturl', $authorization['Redirect_Uri']);
        $order->add_order_note('Strong 3DSecure identification required - Waiting for the payer to return');
        return array(
          'result' => 'success',
          'redirect' => '#OvriChallenge|' . $authorization['3DS_Key'] . '',
        );
      default:
        $order->add_order_note($authorization['message']);
        self::log('Direct payment: Declined payment ' . $authorization['message'] . '', "$order_id");
        wc_add_notice(__('Payment Declined reason : ', 'ovri') . $authorization['message'], 'error');
        return null;
    }
  }


  private function getPaymentParams($order)
  {
    if (WC()->version < '2.7.0') {
      $order_id = $order->id;
      $amount = (float) $order->order_total;

      $holder_name = mb_substr($order->billing_first_name . ' ' . $order->billing_last_name, 0, 32);

      if (!empty($order->billing_email)) {
        $email = $order->billing_email;
      }
      if (!empty($order->billing_phone)) {
        $phone = $order->billing_phone;
      }
      if (!empty($order->billing_country)) {
        $country = $order->billing_country;
      } else {
        $country = $order->shipping_country;
      }
    } else {
      $order_id = $order->get_id();
      $amount = (float) $order->get_total();

      $holder_name = mb_substr($order->get_billing_first_name() . ' ' . $order->get_billing_last_name(), 0, 32);

      if ($order->get_billing_email()) {
        //try use billing address country if set
        $email = $order->get_billing_email();
      }
      if ($order->get_billing_phone()) {
        //try use billing address country if set
        $phone = $order->get_billing_phone();
      }
      if ($order->get_billing_country()) {
        //try use billing address country if set
        $country = $order->get_billing_country();
      } else {
        //try use shipping address if billing not available
        $country = $order->get_shipping_country();
      }
    }

    //billing or shipping neither has country set, try wc default location
    if (!$country) {
      $customerDefaultLocation = wc_get_customer_default_location();
      $country = $customerDefaultLocation['country'];
    }

    //still nothing, use default
    if (!$country) {
      $country = "FR";
    }


    $crd_order_id = str_pad($order_id, 2, '0', STR_PAD_LEFT);
    $cvc = sanitize_text_field($_POST[$this->CARD_CVC]);
    $card_number = str_replace(' ', '', sanitize_text_field($_POST[$this->CARD_PAN]));
    $card_expire_array = explode('/', sanitize_text_field($_POST[$this->CARD_EXPIRY]));
    $exp_month = (int) $card_expire_array[0];
    $exp_year = (int) $card_expire_array[1];
    if ($exp_year < 100) {
      $exp_year += 2000;
    }

    //default challenge window size
    $challenge_window_size = 'full-screen';
    $availChallengeWindowSizes = [
      [600, 400],
      [500, 600],
      [390, 400],
      [250, 400]
    ];

    $ovri_screen_width = (int) sanitize_text_field($_POST['ovri_screen_width']);
    $ovri_screen_height = (int) sanitize_text_field($_POST['ovri_screen_height']);

    $ovri_color_depth = (int) sanitize_text_field($_POST['ovri_color_depth']);
    $ovri_time_zone = (int) sanitize_text_field($_POST['ovri_time_zone']);

    $ovri_browser_language = sanitize_text_field($_POST['ovri_browser_language']);


    foreach ($availChallengeWindowSizes as $aSize) {
      if ($aSize[0] > $ovri_screen_width || $aSize[1] > $ovri_screen_height) {
        //this challenge window size is not acceptable
      } else {
        $challenge_window_size = "$aSize[0]x$aSize[1]";
        break;
      }
    }

    self::log('Direct payment: Parameters have been generated successfully', "$order_id");

    return [
      'amount' => $amount,
      'currency' => get_woocommerce_currency(),
      'email' => $email,
      'phone' => $phone,
      'order_id' => $crd_order_id,
      'country' => $country,
      'payment_instrument' => [
        'pan' => $card_number,
        'exp_year' => $exp_year,
        'exp_month' => $exp_month,
        'cvc' => $cvc,
        'holder' => $holder_name
      ],
      'threeds2_data' => [
        "browser_info" => [
          "accept_header" => "text/html",
          "browser_language" => $ovri_browser_language != '' ? $ovri_browser_language : "en-US",
          "screen_width" => $ovri_screen_width != 0 ? $ovri_screen_width : 1920,
          "screen_height" => $ovri_screen_height != 0 ? $ovri_screen_height : 1040,
          'challenge_window_size' => $challenge_window_size,
          "user_agent" => $_SERVER['HTTP_USER_AGENT'] ?? "Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:21.0) Gecko/20100101 Firefox/21.0",
          "color_depth" => $ovri_color_depth != 0 ? $ovri_color_depth : 24,
          "time_zone" => $ovri_time_zone != 0 ? $ovri_time_zone : -60
        ],
      ]
    ];
  }

  /**
   * external_process_payment
   * 
   * This function handles redirection-based payments. If the merchant has configured in WordPress options 
   * that the payment mode is not inline, then this function will be used. The customer will be redirected 
   * to the OVRI payment page, make their payment, and then be redirected back to the merchant's site 
   * to see the confirmation of their purchase.
   * 
   * This function is compatible with both normal and recurring payments.
   *
   * @param int $order_id The ID of the WooCommerce order.
   * @return array|void Returns an array with the result and the redirect URL if successful, otherwise handles errors.
   */
  private function external_process_payment($order_id)
  {
    global $woocommerce;
    $order = new WC_Order($order_id);
    if (class_exists('WC_Subscriptions') && wcs_order_contains_subscription($order_id)) {
      $subscriptions = wcs_get_subscriptions_for_order($order_id);
      foreach ($subscriptions as $sub) {
        $subscription_id = $sub->get_id(); //Subscription ID in woocommerce
        $amount = $sub->get_total(); // Total amount
        $billing_period = $sub->get_billing_period(); // Billing period
        $billing_interval = $sub->get_billing_interval(); // Billing interval
        $sign_up_fee = $sub->get_sign_up_fee(); // Setup fee
        $items = $sub->get_items(); // Get all subscription items

        foreach ($items as $item) {
          $product_id = $item->get_product_id(); // Get product ID
          $trial_length = WC_Subscriptions_Product::get_trial_length($product_id); // Trial period length
          $subscription_product_name = $item->get_name();
        }

        $subscription = [
          'merchantKey' => $this->get_option('ovri_gateway_api_key'),
          'trial_period' => $trial_length,
          'trial_price' => $sign_up_fee,
          'occurrence_count' => $billing_interval,
          'occurrence_type' => strtoupper($billing_period),
          'subscription_price' => $amount,
          'payment_method' => 'CARD',
          'customer[firstname]' => method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name,
          'customer[lastname]' => method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name,
          'customer[email]' => method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email,
          'customer[ip]' => $_SERVER['REMOTE_ADDR'],
          'merchantReference' => $order_id,
          'description' => $subscription_product_name,
          'url[IPN]' => get_site_url() . '/?wc-api=wc_ovri',
          'url[KO]' => get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '',
          'url[OK]' => get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '',
          'url[CANCEL]' => get_site_url(),
          'url[IPNRENEW]' => get_site_url() . '/?wc-api=wc_ovri_subscription',
          'customdata[MerchantSubscriptionID]' => $subscription_id,
          'customdata[cms]' => 'WordPress/Woocommerce',
        ];

        if ($this->ovri_modetest) {
          $subscription['test'] = $this->ovri_modetest;
        }

        $ask = $this->postRequest($subscription);

        $return = json_decode($ask['body'], true);
        if ($ask['response']['code'] === 201) {
          return array(
            'result' => 'success',
            'redirect' => $return["validationURL"]
          );
        } else {
          if ($return['message']) {
            if (is_array($return['message'])) {
              foreach ($return['message'] as $key => $value) {
                preg_match('/\[(.*?)\]/', $key, $matches);
                $keysis = isset($matches[1]) ? $matches[1] : $key;
                wc_add_notice('[OVRI Error] - ' . $keysis . ' => ' . $value, 'error');
              }
            } else {
              wc_add_notice('[OVRI Error] - ' . $return['message'] . '', 'error');
            }
          } else {
            wc_add_notice(__('Ovri: Subscription URL creation failed!', 'ovri'), 'error');
          }
          return array(
            'result' => 'failure'
          );
        }
      }
    } else {
      $email = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
      $custo_firstname = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
      $custo_lastname = method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;
      $requestToken = array(
        'MerchantKey' => $this->get_option('ovri_gateway_api_key'),
        'amount' => $order->get_total(),
        'RefOrder' => $order_id,
        'Customer_Email' => "$email",
        'Customer_FirstName' => $custo_firstname ? $custo_firstname : $custo_lastname,
        'Customer_Name' => $custo_lastname ? $custo_lastname : $custo_firstname,
        'urlOK' => get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '',
        'urlKO' => get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '',
        'urlIPN' => get_site_url() . '/?wc-api=wc_ovri',
        'forceRedirect' => true
      );
      if ($this->ovri_modetest) {
        $requestToken['test'] = $this->ovri_modetest;
      }
      $getToken = $this->getToken($requestToken);

      if (!is_wp_error($getToken)) {
        $results = json_decode($getToken['body'], true);
        $explanationIS = "";

        if ($getToken['response']['code'] === 400 || $getToken['response']['code'] === 200) {
          if ($results['Explanation']) {
            foreach ($results['Explanation'] as $key => $value) {
              $explanationIS .= "<br><b>" . $key . "</b> : " . $value;
            }
          }
          if ($results['MissingParameters']) {
            $explanationIS .= "<br> List of missing parameters : ";
            foreach ($results['MissingParameters'] as $key => $value) {
              $explanationIS .= "<b>" . $value . " , ";
            }
          }
        }

        if ($getToken['response']['code'] === 200) {
          wc_add_notice(__('Ovri: ' . $results['ErrorCode'] . ' - ' . $results['ErrorDescription'] . ' - ' . $explanationIS, 'ovri'), 'error');
          return;
        } elseif ($getToken['response']['code'] === 400) {
          wc_add_notice(__('Ovri: ' . $results['ErrorCode'] . ' - ' . $results['ErrorDescription'] . ' - ' . $explanationIS, 'ovri'), 'error');
          return;
        } elseif ($getToken['response']['code'] === 201) {
          return array(
            'result' => 'success',
            'redirect' => ovri_universale_params()['WebUriStandard'] . $results['SACS']
          );
        } else {
          wc_add_notice(__('Ovri: Connection error', 'ovri'), 'error');
          return;
        }
      } else {
        wc_add_notice(__('Ovri: Connection error', 'ovri'), 'error');
        return;
      }
    }
  }


  private function is_using_checkout_block()
  {
    if (method_exists("WC_Blocks_Utils", 'has_block_in_page')) {
      return WC_Blocks_Utils::has_block_in_page(wc_get_page_id('checkout'), 'woocommerce/checkout');
    }
    return false;
  }
}
?>