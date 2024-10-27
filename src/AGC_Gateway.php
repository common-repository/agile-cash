<?php

class AGC_Gateway extends WC_Payment_Gateway {
    private $cryptos;
    public function __construct() {
        $cryptoArray = AGC_Cryptocurrencies::get();

        // electrum at the top, then currencies with auto-payment, then the rest
        $keys = array_map(function($val) {
                return $val->get_name();
            }, $cryptoArray);
        array_multisort($keys, $cryptoArray);
        $this->cryptos = $cryptoArray;

        $this->id = 'agc_gateway';
        //$this->icon = AGILE_CASH_PLUGIN_DIR . '/assets/img/dogecoin_logo_small.png';
        $this->title = 'Pay using cryptocurrency';
        $this->has_fields = true;
        $this->method_title = 'Pay using cryptocurrency';
        $this->method_description = 'Allow customers to pay using cryptocurrency';
        $this->init_form_fields();
        $this->init_settings();

        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thank_you_page'));
    }

    public function admin_options() {
        
        ?>
        <h2>Agile Cash Crypto Payments</h2>
        <div class="agc-options">
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
        </div>
        <?php
    }

    // WooCommerce Admin Payment Method Settings
    public function init_form_fields() {
                
        // general settings
        $generalSettings = array(
            'general_settings' => array(
                'title' => 'General settings',
                'type' => 'title',
                'class' => 'section-title',
            ),
            'enabled' => array(
                'title' => 'Enable/Disable', 'woocommerce',
                'type' => 'checkbox',
                'label' => 'Enable cryptocurrency payments', 'woocommerce',
                'default' => 'yes',
                'class' => 'agc-setting',
            )
        );

        $cryptoSettings = array();

        $cryptoSettings['crypto wallets'] = array(
            'title' => 'Cryptocurrency Options',
            'type' => 'title',          
            'description' => 'Enable/Disable cryptocurrencies and store your public wallet addresses.',
            'class' => 'section-title',
        );

        foreach ($this->cryptos as $crypto) {
            $cryptoSettings[$crypto->get_name() . ' Options'] = array(
                'title' => $crypto->get_name() . ' (' . $crypto->get_id() . ')',
                'type' => 'title',
                'class' => 'crypto-title',                
            );

            if (!$crypto->has_electrum() && !$crypto->has_payment_verification()) {
                $cryptoSettings[$crypto->get_name() . ' Options']['description'] = $crypto->get_name() . ' does not support automatic order processing. Please reconcile these orders manually.';
            }

            $cryptoSettings[$crypto->get_id() . '_enabled'] = array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable ' . $crypto->get_name(),
            );
            
            $cryptoSettings[$crypto->get_id() . '_address'] = array(
                'title' => $crypto->get_name() . ' Wallet Address',
                'type' => 'text',
            );

            if ($crypto->has_electrum()) {
                $cryptoSettings[$crypto->get_id() . '_electrum_enabled'] = array(
                    'title' => 'Electrum Mode',
                    'type' => 'checkbox',
                    'default' => 'no',
                    'label' => 'Electrum enabled',
                );
                $cryptoSettings[$crypto->get_id() . '_electrum_mpk'] = array(
                    'title' => 'Electrum mpk',
                    'type' => 'text',
                    'description' => 'Your electrum master public key. (Legacy seed-type only) <br><a href="#woocommerce_agc_gateway_electrum_settings" >Electrum Settings</a>',
                );
                $cryptoSettings[$crypto->get_id() . '_electrum_percent_to_process'] = array (
                    'title' => 'Electrum Auto-Confirm Percentage',
                    'type' => 'number',
                    'default' => '0.970',
                    'description' => 'Electrum will automatically confirm payments that are this percentage of the total amount requested. (1 = 100%), (0.94 = 94%)',
                    'custom_attributes' => array(
                        'min'  => 0.001,
                        'max'  => 1.000,
                        'step' => 0.001,
                    ),
                );
                $cryptoSettings[$crypto->get_id() . '_electrum_required_confirmations'] = array (
                    'title' => 'Electrum Required Confirmations',
                    'type' => 'number',
                    'default' => '2',
                    'custom_attributes' => array(
                        'min'  => 0,
                        'max'  => 9999,
                        'step' => 1,
                    ),
                    'description' => 'This is the number of confirmations a payment needs to receive before it is considered a valid payment.',
                );
                $cryptoSettings[$crypto->get_id() . '_electrum_order_cancellation_time_hr'] = array (
                    'title' => 'Electrum Order Cancellation Timer (hr)',
                    'type' => 'number',
                    'default' => 24,
                    'custom_attributes' => array(
                        'min'  => 0.01,
                        'max'  => 24 * 7, // 7 days
                        'step' => 0.01,
                    ),
                    'description' => 'This is the amount of time in hours that has to elapse before an order is cancelled automatically. (1.5 = 1 hour 30 minutes)',
                );
            }

            if ($crypto->has_payment_verification()) {
                $cryptoSettings[$crypto->get_id() . '_autopayment_enabled'] = array (
                    'title' => 'Auto-Payment Mode',
                    'type' => 'Checkbox',
                    'default' => 'no',
                    'label' => 'Enable Auto-Payment Confirmation/Cancellation',
                );
                $cryptoSettings[$crypto->get_id() . '_autopayment_percent_to_process'] = array (
                    'title' => 'Auto-Confirm Percentage',
                    'type' => 'number',
                    'default' => '0.985',
                    'description' => 'Auto-Payment will automatically confirm payments that are within this percentage of the total amount requested. (1 = 100%), (0.94 = 94%)',
                    'custom_attributes' => array(
                        'min'  => 0.001,
                        'max'  => 1.000,
                        'step' => 0.001,
                    ),
                );
                if ($crypto->needs_confirmations()) {
                    $cryptoSettings[$crypto->get_id() . '_autopayment_required_confirmations'] = array (
                        'title' => 'Required Confirmations',
                        'type' => 'number',
                        'default' => '2',
                        'custom_attributes' => array(
                            'min'  => 0,
                            'max'  => 9999,
                            'step' => 1,
                        ),
                        'description' => 'This is the number of confirmations a payment needs to receive before it is considered a valid payment.',
                    );
                }
                $cryptoSettings[$crypto->get_id() . '_autopayment_order_cancellation_time_hr'] = array (
                    'title' => 'Order Cancellation Timer (hr)',
                    'type' => 'number',
                    'default' => 24,
                    'custom_attributes' => array(
                        'min'  => 0.01,
                        'max'  => 24 * 7, // 7 days
                        'step' => 0.01,
                    ),
                    'description' => 'This is the amount of time in hours that has to elapse before an order is cancelled automatically. (1.5 = 1 hour 30 minutes)',
                );
            }
            
            $cryptoSettings[$crypto->get_id() . '_carousel_enabled'] = array(
                'title' => 'Carousel Mode',
                'type' => 'checkbox',
                'default' => 'no',
                'label' => 'Carousel Addresses Enabled'
            );
            $cryptoSettings[$crypto->get_id() . '_address2'] = array(
                'title' => $crypto->get_name() . ' Wallet Address 2',
                'type' => 'text',
            );
            $cryptoSettings[$crypto->get_id() . '_address3'] = array(
                'title' => $crypto->get_name() . ' Wallet Address 3',
                'type' => 'text',
            );
            $cryptoSettings[$crypto->get_id() . '_address4'] = array(
                'title' => $crypto->get_name() . ' Wallet Address 4',
                'type' => 'text',
            );
            $cryptoSettings[$crypto->get_id() . '_address5'] = array(
                'title' => $crypto->get_name() . ' Wallet Address 5',
                'type' => 'text',
            );
            
        }        

        $pricingSettings = array(
            'pricing options' => array(
                'title' => 'Pricing Options',
                'type' => 'title',
                'description' => 'Price is average of APIs selected.',
                'class' => 'section-title',
            ),        
            'use_crypto_compare' => array(
                'title' => 'Use crypto compare',
                'type' => 'checkbox',
                'default' => 'yes',
                'description' => 'Use crypto compare in your cryptocurrency pricing.',            
            ),
            'use_hitbtc' => array(
                'title' => 'Use hitbtc.com ticker price',
                'type' => 'checkbox',
                'default' => 'no',
                'description' => 'Use hitbtc.com in your cryptocurrency pricing.',
            ),
            'use_gateio' => array(
                'title' => 'Use gateio.io ticker price',
                'type' => 'checkbox',
                'default' => 'no',
                'description' => 'Use gaitio.io in your cryptocurrency pricing.',
            ),
            'use_bittrex' => array(
                'title' => 'Use bittrex.com ticker price',
                'type' => 'checkbox',
                'default' => 'no',
                'description' => 'Use bittrex.com in your cryptocurrency pricing.',
            ),
            'use_poloniex' => array(
                'title' => 'Use poloniex.com average trade value',
                'type' => 'checkbox',
                'default' => 'no',
                'description' => 'Use poloniex in your cryptocurrency pricing.',
            ),
        );

        $this->form_fields = array_merge($generalSettings, $cryptoSettings, $pricingSettings);
        $cssPath = '/wp-content/plugins/agile-cash/assets/css/agc.css';
        $jsPath = '/wp-content/plugins/agile-cash/assets/js/agc.js';
        wp_enqueue_style('agc-styles', $cssPath);
        wp_enqueue_script('agc-scripts', $jsPath, array('jquery'), AGC_VERSION);

    }

    public function process_admin_options() {
        parent::process_admin_options();        
        
        foreach ($this->cryptos as $crypto) {
            if (!$crypto->has_electrum()) {
                
                $buffer = array();

                $buffer[] = $this->settings[$crypto->get_id() . '_address'];
                $buffer[] = $this->settings[$crypto->get_id() . '_address2'];
                $buffer[] = $this->settings[$crypto->get_id() . '_address3'];
                $buffer[] = $this->settings[$crypto->get_id() . '_address4'];
                $buffer[] = $this->settings[$crypto->get_id() . '_address5'];

                $sendWarningMessage = true;

                for ($i = 2; $i <= 5; $i++) {

                    $address = $this->settings[$crypto->get_id() . '_address' . $i];
                    $addressValid = AGC_Cryptocurrencies::is_valid_wallet_address($crypto->get_id(), $address);

                    if ($addressValid) {
                        $sendWarningMessage = false;
                    }   


                }
                if ($sendWarningMessage && $this->crypto_is_enabled($crypto) && $this->crypto_has_carousel_enabled($crypto)) {
                    WC_Admin_Settings::add_message('Carousel mode was activated for ' . $crypto->get_name() . ' but no valid carousel addresses were saved, falling back to static address.');
                }
                AGC_Util::log(__FILE__, __LINE__, 'saving buffer to database with count of: ' . count($buffer));
                $carouselRepo = new AGC_Carousel_Repo();
                $carouselRepo->set_buffer($crypto->get_id(), $buffer);
                
            }
        }
    }

    // This is called whenever the user saves the woocommerce admin settings, for some reason AFTER validate_enabled_field is called
    public function validate_BTC_electrum_enabled_field($key, $value) {
        
        $post_data = $this->get_post_data();
        $gatewaySettings = new AGC_Postback_Settings_Helper($this->id, $this->cryptos, $post_data);
        $validMpk = $gatewaySettings->crypto_has_valid_electrum_mpk('BTC');        
        
        if (! $value) {
            return 'no';
        }

        if (!$validMpk) {
            WC_Admin_Settings::add_error('Electrum was enabled for Bitcoin but mpk is invalid. Disabling Electrum for Bitcoin');
            return 'no';
        }

        return 'yes';
    }

    // This is called whenever the user saves the woocommerce admin settings, for some reason AFTER validate_enabled_field is called
    public function validate_LTC_electrum_enabled_field($key, $value) {
        
        $post_data = $this->get_post_data();
        $gatewaySettings = new AGC_Postback_Settings_Helper($this->id, $this->cryptos, $post_data);
        $validMpk = $gatewaySettings->crypto_has_valid_electrum_mpk('LTC');

        //$validAddress = $gatewaySettings->crypto_has_valid_wallet('BTC');
        if (! $value) {
            return 'no';
        }

        if (!$validMpk) {
            WC_Admin_Settings::add_error('Electrum was enabled for Litecoin but mpk is invalid. Disabling Electrum for Litecoin');
            return 'no';
        }

        return 'yes';
    }

    // This is called whenever the user saves the woocommerce admin settings, for some reason AFTER validate_enabled_field is called
    public function validate_QTUM_electrum_enabled_field($key, $value) {
        
        $post_data = $this->get_post_data();
        $gatewaySettings = new AGC_Postback_Settings_Helper($this->id, $this->cryptos, $post_data);
        $validMpk = $gatewaySettings->crypto_has_valid_electrum_mpk('QTUM');

        
        if (! $value) {
            return 'no';
        }

        if (!$validMpk) {
            WC_Admin_Settings::add_error('Electrum was enabled for Qtum but mpk is invalid. Disabling Electrum for Qtum');
            return 'no';
        }

        return 'yes';
    }

    // This is called whenever the user saves the woocommerce admin settings, server side validation based around the enable/disable plugin field
    public function validate_enabled_field($key, $value) {
        
        // if the gateway is not enabled do not do any validation
        if (! $value) {
            return 'no';
        }

        $result = 'yes';

        $post_data = $this->get_post_data();        

        $gatewaySettings = new AGC_Postback_Settings_Helper($this->id, $this->cryptos, $post_data);        

        // fail if no pricing options are selected
        if (! $gatewaySettings->has_one_enabled_pricing_options()) {
            WC_Admin_Settings::add_error('You must select at least one pricing option.');
            $result = 'no';
        }
        // fail if no cryptos are enabled
        if (! $gatewaySettings->has_one_enabled_crypto()) {
            WC_Admin_Settings::add_error('You must enable at least one cryptocurrency.');
            $result = 'no';
        }

        // validation for each crypto
        foreach ($this->cryptos as $crypto) {
            $cryptoId = $crypto->get_id();

            $cryptoEnabled = $gatewaySettings->is_crypto_enabled($cryptoId);
            $electrumEnabled = $gatewaySettings->is_electrum_enabled($cryptoId);
            $validMpk = $gatewaySettings->crypto_has_valid_electrum_mpk($cryptoId);
            $validAddress = $gatewaySettings->crypto_has_valid_wallet($cryptoId);

            
            // fall back to regular address but let user know
            if ($cryptoEnabled && $electrumEnabled && (!$validMpk) && $validAddress) {                
                // code in validate_BTC_enabled_field handles disabling electrum
                $errorMessage = sprintf(
                    'Invalid Master Public Key for %s. Falling back to regular wallet address.',
                    $crypto->get_name());

                // EVEN THOUGH WE THROW AN ERROR WE DO NOT DISABLE THE PLUGIN
                WC_Admin_Settings::add_error($errorMessage);            
            }
            if ($cryptoEnabled && $electrumEnabled && (!$validMpk) && (!$validAddress)) {

                $errorMessage = sprintf(
                    'Invalid wallet address for %s... Plug-in will be disabled until each enabled cryptocurrency has a valid wallet address',
                    $crypto->get_name());
                // code in validate_BTC_enabled_field handles disabling electrum
                WC_Admin_Settings::add_error($errorMessage);
                $result = 'no';
            }
            if ($cryptoEnabled && (!$electrumEnabled) && (!$validAddress)) {

                $errorMessage = sprintf(
                    'Invalid wallet address for %s... Plug-in will be disabled until each enabled cryptocurrency has a valid wallet address',
                    $crypto->get_name());

                WC_Admin_Settings::add_error($errorMessage);
                $result = 'no';                
            }
        }

        return $result;
    }

    // This runs when the user hits the checkout page
    // We load our crypto select with valid crypto currencies
    public function payment_fields() {

        $validCryptos = $this->cryptos_with_valid_settings();
        
        foreach ($validCryptos as $crypto) {
            if ($crypto->has_electrum() && $this->crypto_has_electrum_enabled($crypto)) {
                $mpk = $this->get_crypto_electrum_mpk($crypto);

                $electrumRepo = new AGC_Electrum_Repo($crypto->get_id(), $mpk);

                $count = $electrumRepo->count_ready();

                if ($count < 1) {
                    try {
                        AGC_Electrum::force_new_address($crypto->get_id(), $mpk);                        
                    }
                    catch ( \Exception $e) {
                        AGC_Util::log(__FILE__, __LINE__, 'UNABLE TO GENERATE ELECTRUM ADDRESS FOR ' . $crypto->get_name() . ' ADMIN MUST BE NOTIFIED. REMOVING CRYPTO FROM PAYMENT OPTIONS');
                        unset($validCryptos[$crypto->get_id()]);
                    }
                }
            }
        }
        
        $selectOptions = $this->get_select_options_for_cryptos($validCryptos);

        woocommerce_form_field(
            'agc_currency_id', array(
                'type'     => 'select',                
                'label'    => 'Choose a cryptocurrency',
                'required' => true,
                'default' => 'ZRX',
                'options'  => $selectOptions,
            )
        );    
    }

    // return list of cryptocurrencies that have valid settings
    private function cryptos_with_valid_settings() {
        $cryptosWithValidSettings = array();

        foreach ($this->cryptos as $crypto) {
            if ( $this->crypto_has_valid_settings($crypto) ) {
                $cryptosWithValidSettings[$crypto->get_id()] = $crypto;
            }
        }

        return $cryptosWithValidSettings;
    }

    // check if crypto has valid settings
    private function crypto_has_valid_settings($crypto) {
        if (! $this->crypto_is_enabled($crypto)) {
            return false;
        }

        $electrumValid = $this->crypto_has_electrum_enabled($crypto) && $this->crypto_has_electrum_mpk($crypto);

        if ($electrumValid || $this->crypto_has_wallet_address($crypto)) {
            return true;
        }

        return false;
    }

    // This runs when the user selects Place Order, before process_payment, has nothing to do with the other validation methods
    public function validate_fields() {
        // if the currently selected gateway is this gateway we set transients related to conversions and if something goes wrong we prevent the customer from hitting the thank you page  by throwing the WooCommerce Error Notice.
        if (WC()->session->get('chosen_payment_method') === $this->id) {
            try {
                $chosenCryptoId = $_POST['agc_currency_id'];
                $crypto = $this->cryptos[$chosenCryptoId];
                $curr = get_woocommerce_currency();
                $cryptoPerUsd = $this->get_crypto_value_in_usd($crypto->get_id(), $crypto->get_update_interval());
                
                // this is just a check to make sure we can hit the currency exchange if we need to
                $usdTotal = AGC_Exchange::get_order_total_in_usd(1.0, $curr);
            }
            catch ( \Exception $e) {
                AGC_Util::log(__FILE__, __LINE__, $e->getMessage());
                wc_add_notice($e->getMessage(), 'error');
            }
        }
    }

    // This is called when the user clicks Place Order, after validate_fields
    public function process_payment($order_id) {
        $order = new WC_Order($order_id);

        $selectedCryptoId = $_POST['agc_currency_id'];
        WC()->session->set('chosen_crypto_id', $selectedCryptoId);

        return array(
                      'result' => 'success',
                      'redirect'  => $this->get_return_url( $order ),
                    );
    }

    // This is called after process payment, when the customer places the order
    public function thank_you_page($order_id) {
        
        try {
            $walletCheck = get_post_meta($order_id, 'wallet_address');
            
            // if we already set this then we are on a page refresh, so handle refresh
            if (count($walletCheck) > 0) {

                $this->handle_thank_you_refresh(
                    get_post_meta($order_id, 'crypto_type_id', true),
                    get_post_meta($order_id, 'wallet_address', true),
                    get_post_meta($order_id, 'crypto_amount', true));
                
                return;
            }
            
            $chosenCryptoId = WC()->session->get('chosen_crypto_id');
            $order = new WC_Order($order_id);
            $crypto = $this->cryptos[$chosenCryptoId];
                        
            // get current price of crypto
            $cryptoPerUsd = $this->get_crypto_value_in_usd($crypto->get_id(), $crypto->get_update_interval());
            
            // handle different woocommerce currencies and get the order total in USD
            $curr = get_woocommerce_currency();
            $usdTotal = AGC_Exchange::get_order_total_in_usd($order->get_total(), $curr);
            
            // order total in cryptocurrency
            $cryptoTotal = round($usdTotal / $cryptoPerUsd, $crypto->get_round_precision(), PHP_ROUND_HALF_UP);

            // format the crypto amount based on crypto
            $formattedCryptoTotal = AGC_Cryptocurrencies::get_price_string($crypto->get_id(), $cryptoTotal);

            AGC_Util::log(__FILE__, __LINE__, 'Crypto total: ' . $cryptoTotal . ' Formatted Total: ' . $formattedCryptoTotal);

            $electrumEnabled = $crypto->has_electrum() && $this->crypto_has_electrum_enabled($crypto);

            // if electrum is enabled we have stuff to do
            if ($electrumEnabled) {
                $mpk = $this->get_crypto_electrum_mpk($crypto);
                
                $electrumRepo = new AGC_Electrum_Repo($crypto->get_id(), $mpk);

                // get fresh electrum wallet
                $walletAddress = $electrumRepo->get_oldest_ready();
                
                // if we couldnt find a fresh one, force a new one
                if (!$walletAddress) {
                    
                    try {
                        AGC_Electrum::force_new_address($crypto->get_id(), $mpk);
                        $walletAddress = $electrumRepo->get_oldest_ready();
                    }
                    catch ( \Exception $e) {
                        throw new \Exception('Unable to get payment address for order. This order has been cancelled. Please try again or contact the site administrator... Inner Exception: ' . $e->getMessage());
                    }
                }

                // set electrum wallet address to get later
                WC()->session->set('electrum_wallet_address', $walletAddress);

                // update the database
                $electrumRepo->set_status($walletAddress, 'assigned');
                $electrumRepo->set_order_id($walletAddress, $order_id);
                $electrumRepo->set_order_amount($walletAddress, $formattedCryptoTotal);

                $orderNote = sprintf(
                    'Electrum wallet address %s is awaiting payment of %s',
                    $walletAddress,
                    $formattedCryptoTotal);
                
            }
            // Electrum is not enabled, just handle static wallet or carousel mode
            else {
                $walletAddress = $this->get_crypto_wallet_address($crypto);

                // handle payment verification feature
                if ($crypto->has_payment_verification() && $this->settings[$crypto->get_id() . '_autopayment_enabled'] === 'yes') {
                    $paymentRepo = new AGC_Payment_Repo();

                    $paymentRepo->insert($walletAddress, $crypto->get_id(), $order_id, $formattedCryptoTotal, 'unpaid');
                }

                $orderNote = sprintf(
                    'Awaiting payment of %s %s to payment address %s.',
                    $formattedCryptoTotal,
                    $crypto->get_id(),
                    $walletAddress);
            }
            
            // For email
            WC()->session->set($crypto->get_id() . '_amount', $formattedCryptoTotal);

            // For customer reference and to handle refresh of thank you page
            update_post_meta($order_id, 'crypto_amount', $formattedCryptoTotal);
            update_post_meta($order_id, 'wallet_address', $walletAddress);
            update_post_meta($order_id, 'crypto_type_id', $crypto->get_id());

            // Emails are fired once we update status to on-hold, so hook additional email details here
            add_action('woocommerce_email_order_details', array( $this, 'additional_email_details' ), 10, 4);
            
            $order->update_status('wc-on-hold', $orderNote);

            // Output additional thank you page html
            $this->output_thank_you_html($crypto, $walletAddress, $formattedCryptoTotal);
        }
        catch ( \Exception $e ) {
            $order = new WC_Order($order_id);

            // cancel order if something went wrong
            $order->update_status('wc-failed', 'Error Message: ' . $e->getMessage());
            AGC_Util::log(__FILE__, __LINE__, 'Something went wrong during checkout: ' . $e->getMessage());
            echo '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">';
            echo '<ul class="woocommerce-error">';
            echo '<li>';
            echo 'Something went wrong.<br>';
            echo $e->getMessage();
            echo '</li>';
            echo '</ul>';
            echo '</div>';
        }
    }

    public function additional_email_details( $order, $sent_to_admin, $plain_text, $email ) {
        $chosenCrypto = WC()->session->get('chosen_crypto_id');
        $crypto =  $this->cryptos[$chosenCrypto];
        $orderCryptoTotal = WC()->session->get($crypto->get_id() . '_amount');

        $electrumEnabled = $crypto->has_electrum() && $this->crypto_has_electrum_enabled($crypto);

        if ($electrumEnabled) {
            // electrum wallet that was selected/generated on thank you page
            $walletAddress = WC()->session->get('electrum_wallet_address');
        }
        else {
            $walletAddress = get_post_meta($order->get_id(), 'wallet_address', true);
            AGC_Util::log(__FILE__, __LINE__, 'getting wallet address from post meta: ' . $walletAddress);
        }

        $qrCode = $this->get_qr_code($crypto->get_name(), $walletAddress, $orderCryptoTotal);

        ?>
        <h2>Additional Details</h2>
        <p>QR Code Payment: </p>
        <div style="margin-bottom:12px;">
            <img  src=<?php echo $qrCode; ?> />
        </div>
        <p>
            Wallet Address: <?php echo $walletAddress ?>
        </p>
        <p>
            Currency: <?php echo '<img src="' . $crypto->get_logo_file_path() . '" alt="" />' . $crypto->get_name(); ?>
        </p>
        <p>
            Total:
            <?php 
                if ($crypto->get_symbol() === '') {
                    echo AGC_Cryptocurrencies::get_price_string($crypto->get_id(), $orderCryptoTotal) . ' ' . $crypto->get_id();
                }
                else {
                    echo $crypto->get_symbol() . AGC_Cryptocurrencies::get_price_string($crypto->get_id(), $orderCryptoTotal);
                }
            ?>     
        </p>
        <?php
    }    

    // check admin settings to see if crypto is enabled
    private function crypto_is_enabled($crypto) {
        $enabledSetting = $crypto->get_id() . '_enabled';
        return $this->settings[$enabledSetting] === 'yes';
    }

    // check admin settings to see if crypto has a wallet address
    private function crypto_has_wallet_address($crypto) {        
        $walletSetting = $crypto->get_id() . '_address';
        return ! empty( $this->settings[$walletSetting] );
    }

    private function get_crypto_wallet_address($crypto) {
        $walletSetting = $crypto->get_id() . '_address';

        // we dont offer carousel mode for electrum cryptos so just return regular wallet address
        if ($crypto->has_electrum()) {
            return $this->settings[$walletSetting];
        }

        if (!$this->crypto_has_carousel_enabled($crypto)) {
            return $this->settings[$walletSetting];
        }
        else {
            $carousel = new AGC_Carousel($crypto->get_id());
            
            return $carousel->get_next_address();
        }
    }

    private function crypto_has_electrum_enabled($crypto) {
        $electrumEnabledSetting = $crypto->get_id() . '_electrum_enabled';
        if (! array_key_exists($electrumEnabledSetting, $this->settings)) {
            return false;
        }
        return $this->settings[$electrumEnabledSetting] === 'yes';
    }

    private function crypto_has_electrum_mpk($crypto) {
        $electrumMpkSetting = $crypto->get_id() . '_electrum_mpk';
        if ( !array_key_exists($electrumMpkSetting, $this->settings)) {
            return false;
        }
        return ! empty($this->settings[$electrumMpkSetting]);
    }

    private function get_crypto_electrum_mpk($crypto) {
        $electrumMpkSetting = $crypto->get_id() . '_electrum_mpk';
        return $this->settings[$electrumMpkSetting];
    }

    private function crypto_has_carousel_enabled($crypto) {
        $carouselEnabledSetting = $crypto->get_id() . '_carousel_enabled';
        return $this->settings[$carouselEnabledSetting] === 'yes';
    }

    // convert array of cryptos to option array
    private function get_select_options_for_cryptos($cryptos) {
        $selectOptionArray = array();

        foreach ($cryptos as $crypto) {
            $selectOptionArray[$crypto->get_id()] = $crypto->get_name();
        }

        return $selectOptionArray;
    }

    private function get_qr_code($cryptoName, $walletAddress, $cryptoTotal) {
        $endpoint = 'https://api.qrserver.com/v1/create-qr-code/?data=';

        $formattedName = strtolower(str_replace(' ', '', $cryptoName));
        $qrData = $formattedName . ':' . $walletAddress . '?amount=' . $cryptoTotal;

        return $endpoint . $qrData;
    }

    private function output_thank_you_html($crypto, $walletAddress, $cryptoTotal) {
        
        $formattedPrice = AGC_Cryptocurrencies::get_price_string($crypto->get_id(), $cryptoTotal);
        $qrCode = $this->get_qr_code($crypto->get_name(), $walletAddress, $formattedPrice);

        ?>
        <p>Here are your cryptocurrency payment details.</p>
        <ul class="woocommerce-order-overview woocommerce-thankyou-order-details order_details">
            <li class="woocommerce-order-overview__qr-code">
                <p style="word-wrap: break-word;">QR Code payment:</p>
                <strong>
                    <span class="woocommerce-Price-amount amount">
                        <img style="margin-top:3px;" src=<?php echo $qrCode; ?> />
                    </span>
                </strong>                
            </li>
            <li>
                <p style="word-wrap: break-word;">Wallet Address: 
                    <strong>
                        <span class="woocommerce-Price-amount amount">
                            <?php echo $walletAddress ?>                
                        </span>
                    </strong>
                </p>
            </li>
            <li>
                <p>Currency: 
                    <strong>
                        <span class="woocommerce-Price-amount amount">
                            <?php echo '<img src="' . $crypto->get_logo_file_path() . '" />' . ' ' . $crypto->get_name() ?>
                        </span>
                    </strong>
                </p>
            </li>
            <li>
                <p style="word-wrap: break-word;">Total: 
                    <strong>
                        <span class="woocommerce-Price-amount amount">
                            <?php 
                                if ($crypto->get_symbol() === '') {
                                    echo $formattedPrice . ' ' . $crypto->get_id();
                                }
                                else {
                                    echo $crypto->get_symbol() . $formattedPrice;
                                }
                            ?>
                        </span>
                    </strong>
                </p>
            </li>
        </ul>
        <?php
    }    

    private function handle_thank_you_refresh($chosenCrypto, $walletAddress, $cryptoTotal) {
        $this->output_thank_you_html($this->cryptos[$chosenCrypto], $walletAddress, $cryptoTotal);
    }

    // this function hits all the crypto exchange APIs that the user selected, then averages them and returns a conversion rate for USD
    // if the user has selected no exchanges to fetch data from it instead takes the average from all of them
    private function get_crypto_value_in_usd($cryptoId, $updateInterval) {

        $prices = array();

        if ($this->settings['use_crypto_compare'] === 'yes') {
            $ccPrice = AGC_Exchange::get_cryptocompare_price($cryptoId, $updateInterval);        

            if ($ccPrice > 0) {
                $prices[] = $ccPrice;
            }
        }

        if ($this->settings['use_hitbtc'] === 'yes') {
            $hitbtcPrice = AGC_Exchange::get_hitbtc_price($cryptoId, $updateInterval);

            if ($hitbtcPrice > 0) {
                $prices[] = $hitbtcPrice;
            }        
        }

        if ($this->settings['use_gateio'] === 'yes') {
            $gateioPrice = AGC_Exchange::get_gateio_price($cryptoId, $updateInterval);

            if ($gateioPrice > 0) {
                $prices[] = $gateioPrice;
            }        
        }

        if ($this->settings['use_bittrex'] === 'yes') {
            $bittrexPrice = AGC_Exchange::get_bittrex_price($cryptoId, $updateInterval);

            if ($bittrexPrice > 0) {
                $prices[] = $bittrexPrice;  
            }        
        }

        if ($this->settings['use_poloniex'] === 'yes') {
            $poloniexPrice = AGC_Exchange::get_poloniex_price($cryptoId, $updateInterval);

            // if there were no trades do not use this pricing method
            if ($poloniexPrice > 0) {
                $prices[] = $poloniexPrice;
            }        
        }

        $sum = 0;
        $count = count($prices);

        if ($count === 0) {        
            throw new \Exception( 'No cryptocurrency exchanges could be reached, please try again.' );
        }

        foreach ($prices as $price) {
            $sum += $price;        
        }

        $average_price = $sum / $count;

        return $average_price;
    }    
}

?>