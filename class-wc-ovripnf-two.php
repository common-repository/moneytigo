<?php
class WC_OvriPnfTwo extends WC_Payment_Gateway
{
  /**
   * Construction of Ovri two-step payment method
   *
   * @return void
   */
  public function __construct()
  {
    global $woocommerce;
    $this->version = ovri_universale_params()['Version'];
    $this->id = 'ovripnftwo';
    $this->icon = ovri_get_file("assets/img/carte.png");
    $this->init_form_fields();

    // Load the settings.
    $this->init_settings();
    $this->has_fields = false;
    // Load parent configuration
    $parentConfiguration = get_option('woocommerce_ovri_settings');
    $this->method_title = __('Ovri - Payment in two instalments', 'ovri');

    // Define user set variables.
    $this->title = $this->settings['title'];
    $this->instructions = $this->get_option('instructions');
    $this->method_description = __('Accept payment in two instalments! <a href="https://my.ovri.app">Open an account now !</a>', 'ovri');
    $this->ovri_gateway_api_key = $parentConfiguration['ovri_gateway_api_key'];
    $this->ovri_gateway_secret_key = $parentConfiguration['ovri_gateway_secret_key'];
    // Actions.
    if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
      add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
    } else {
      add_action('woocommerce_update_options_payment_gateways', array(&$this, 'process_admin_options'));
    }
  }
  /* Admin Panel Options.*/
  public function admin_options()
  {
?>

    <h3>
      <?php _e('Ovri configuration (Payment in 2 times)', 'ovri'); ?>
    </h3>
    <div class="simplify-commerce-banner error">
      <p class="main" style="color:red;"><strong> <?php echo __('Attention, if you activate the payment in several times in this case your customer will pay his order in several times and you will receive the installments as they are due on your payment account! You can subscribe to an unpaid guarantee at Ovri to protect yourself against unpaid invoices... However, keep in mind that OVRI does not advance funds.', 'ovri'); ?> </strong></p>
    </div>
    <div class="simplify-commerce-banner updated"> <img src="<?php echo ovri_get_file("assets/img/ovri.png"); ?>" />
      <p class="main"><strong><?php echo __('Accepts payments by credit card with Ovri', 'ovri'); ?></strong></p>
      <p><?php echo __('Ovri is a secure payment solution on the Internet. As a virtual POS (Electronic Payment Terminal), Ovri makes it possible to cash payments made on the Internet 24 hours a day, 7 days a week. This service relieves your site of the entire payment phase; the latter takes place directly on our secure payment platform.', 'ovri'); ?></p>
      <p><?php echo __('For any problem or information contact: hello@ovri.app', 'ovri'); ?></p>
      <p><a href="https://my.ovri.app" target="_blank" class="button button-primary"><?php echo __('Get a Ovri account', 'ovri'); ?></a> <a href="https://my.ovri.app" target="_blank" class="button"><?php echo __('Test free', 'ovri'); ?></a> <a href="https://www.ovri.com" target="_blank" class="button"><?php echo __('Official site', 'ovri'); ?></a> <a href="https://docs.ovri.app" target="_blank" class="button"><?php echo __('Documentation', 'ovri'); ?></a></p>
    </div>
    <div class="simplify-commerce-banner error">
      <p class="main" style="color:red;"><strong> <?php echo __('If you want your customer to be automatically redirected to your site once the payment is accepted or failed, consider activating this option directly in the configuration of your website in your Ovri DashBoard', 'ovri'); ?> </strong></p>
    </div>
    <table class="form-table">
      <?php $this->generate_settings_html(); ?>
    </table>
<?php
  }


  /* Initialise Gateway Settings Form Fields for ADMIN. */
  public function init_form_fields()
  {
    global $woocommerce;


    $this->form_fields = array(
      'enabled' => array(
        'title' => __('Enable / Disable', 'ovri'),
        'type' => 'checkbox',
        'label' => __('Activate payment in two instalments', 'ovri'),

      ),
      'seuil' => array(
        'title' => __('Minimum amount', 'ovri'),
        'type' => 'text',
        'label' => __('Indicate the minimum amount of the cart to be able to use this payment method (Minimum 50 EUR)', 'ovri'),
        'desc_tip' => __('Define from which amount you want your customer to be able to use this payment method.', 'ovri'),
        'default' => '50'
      ),
      'title' => array(
        'title' => __('Method title', 'ovri'),
        'type' => 'text',
        'description' => __('This is the name displayed on your checkout', 'ovri'),
        'desc_tip' => true,
        'default' => __('Credit card in two instalments', 'ovri')
      ),
      'description' => array(
        'title' => __('Message before payment', 'ovri'),
        'type' => 'textarea',
        'description' => __('Message that the customer sees when he chooses this payment method', 'ovri'),
        'desc_tip' => true,
        'default' => __('You will be redirected to our secure server to make your payment', 'ovri')
      )
    );
  }

  /* Request authorization token from Ovri */
  /* Private function only accessible to internal execution */
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
   * */
  public function process_payment($order_id)
  {
    //obtain token for payment processing
    global $woocommerce;
    $order = new WC_Order($order_id);
    $email = method_exists($order, 'get_billing_email') ? $order->get_billing_email() : $order->billing_email;
    $custo_firstname = method_exists($order, 'get_billing_first_name') ? $order->get_billing_first_name() : $order->billing_first_name;
    $custo_lastname = method_exists($order, 'get_billing_last_name') ? $order->get_billing_last_name() : $order->billing_last_name;
    //$the_order_id = method_exists($order, 'get_id') ? $order->get_id() : $order->id;
    //$the_order_key = method_exists($order, 'get_order_key') ? $order->get_order_key() : $order->order_key;
    $requestToken = array(
      'MerchantKey' => $this->ovri_gateway_api_key,
      'amount' => $order->get_total(),
      'RefOrder' => $order_id,
      'Customer_Email' => "$email",
      'Customer_FirstName' => $custo_firstname ? $custo_firstname : $custo_lastname,
      'Customer_Name' => $custo_lastname ? $custo_lastname : $custo_firstname,
      'Lease' => '2',
      'urlOK' => get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '',
      'urlKO' => get_site_url() . '/?wc-api=wc_ovri_return&mtg_ord=' . $order_id . '',
      'urlIPN' => get_site_url() . '/?wc-api=wc_ovri',
    );
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

        wc_add_notice(__('Ovri : ' . $results['ErrorCode'] . ' - ' . $results['ErrorDescription'] . ' - ' . $explanationIS . '', 'ovri'), 'error');
        return;
      } else if ($getToken['response']['code'] === 400) {
        wc_add_notice(__('Ovri : ' . $results['ErrorCode'] . ' - ' . $results['ErrorDescription'] . ' - ' . $explanationIS . '', 'ovri'), 'error');
        return;
      } else if ($getToken['response']['code'] === 201) {
        return array(
          'result' => 'success',
          'redirect' => ovri_universale_params()['WebUriInstallment'] . $results['SACS']
        );
      } else {
        wc_add_notice('Ovri : Connection error', 'error');
        return;
      }
    } else {
      wc_add_notice('Ovri : Connection error', 'error');
      return;
    }
  }
}
?>