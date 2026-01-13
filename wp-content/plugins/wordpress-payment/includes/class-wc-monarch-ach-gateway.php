<?php

if (!defined('ABSPATH')) {
    exit;
}

class WC_Monarch_ACH_Gateway extends WC_Payment_Gateway {
    
    public function __construct() {
        $this->id = 'monarch_ach';
        $this->icon = '';
        $this->has_fields = true;
        $this->method_title = 'Monarch ACH';
        $this->method_description = 'Secure ACH bank transfers via Monarch payment gateway';
        $this->supports = array('products');
        
        $this->init_form_fields();
        $this->init_settings();
        
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        
        $this->api_key = $this->testmode ? $this->get_option('test_api_key') : $this->get_option('live_api_key');
        $this->app_id = $this->testmode ? $this->get_option('test_app_id') : $this->get_option('live_app_id');
        $this->merchant_org_id = $this->testmode ? $this->get_option('test_merchant_org_id') : $this->get_option('live_merchant_org_id');
        $this->partner_name = $this->get_option('partner_name');
        
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Show transaction details to customers on order view page (My Account)
        add_action('woocommerce_order_details_after_order_table', array($this, 'display_transaction_details_for_customer'), 10, 1);
        
        // AJAX hooks
        add_action('wp_ajax_monarch_create_customer', array($this, 'ajax_create_customer'));
        add_action('wp_ajax_nopriv_monarch_create_customer', array($this, 'ajax_create_customer'));
        add_action('wp_ajax_monarch_add_bank_account', array($this, 'ajax_add_bank_account'));
        add_action('wp_ajax_nopriv_monarch_add_bank_account', array($this, 'ajax_add_bank_account'));
        add_action('wp_ajax_monarch_create_organization', array($this, 'ajax_create_organization'));
        add_action('wp_ajax_nopriv_monarch_create_organization', array($this, 'ajax_create_organization'));
        add_action('wp_ajax_monarch_bank_connection_complete', array($this, 'ajax_bank_connection_complete'));
        add_action('wp_ajax_nopriv_monarch_bank_connection_complete', array($this, 'ajax_bank_connection_complete'));
        add_action('wp_ajax_monarch_check_bank_status', array($this, 'ajax_check_bank_status'));
        add_action('wp_ajax_nopriv_monarch_check_bank_status', array($this, 'ajax_check_bank_status'));
        add_action('wp_ajax_monarch_get_latest_paytoken', array($this, 'ajax_get_latest_paytoken'));
        add_action('wp_ajax_nopriv_monarch_get_latest_paytoken', array($this, 'ajax_get_latest_paytoken'));
        add_action('wp_ajax_monarch_disconnect_bank', array($this, 'ajax_disconnect_bank'));
        add_action('wp_ajax_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
        add_action('wp_ajax_nopriv_monarch_manual_bank_entry', array($this, 'ajax_manual_bank_entry'));
        add_action('wp_ajax_monarch_get_bank_linking_url', array($this, 'ajax_get_bank_linking_url'));
        add_action('wp_ajax_nopriv_monarch_get_bank_linking_url', array($this, 'ajax_get_bank_linking_url'));
    }
    
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => 'Enable/Disable',
                'type' => 'checkbox',
                'label' => 'Enable Monarch ACH Payment',
                'default' => 'no'
            ),
            'title' => array(
                'title' => 'Title',
                'type' => 'text',
                'description' => 'This controls the title displayed during checkout.',
                'default' => 'ACH Bank Transfer',
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => 'Description',
                'type' => 'textarea',
                'description' => 'This controls the description displayed during checkout.',
                'default' => 'Pay securely using your bank account via ACH transfer.',
            ),
            'testmode' => array(
                'title' => 'Test mode',
                'label' => 'Enable Test Mode',
                'type' => 'checkbox',
                'description' => 'Place the payment gateway in test mode using sandbox API credentials.',
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'api_credentials_heading' => array(
                'title' => 'API Credentials',
                'type' => 'title',
                'description' => 'Enter your Monarch API credentials below. You can get these from your Monarch dashboard.',
            ),
            'partner_name' => array(
                'title' => 'Partner Name',
                'type' => 'text',
                'description' => 'Your partner name as registered with Monarch (e.g., "yourcompany").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter your partner name',
            ),
            'test_credentials_heading' => array(
                'title' => 'Sandbox/Test Credentials',
                'type' => 'title',
                'description' => 'These credentials are used when Test Mode is enabled.',
            ),
            'test_api_key' => array(
                'title' => 'Sandbox API Key',
                'type' => 'password',
                'description' => 'Your sandbox API key from Monarch (e.g., "xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter sandbox API key',
            ),
            'test_app_id' => array(
                'title' => 'Sandbox App ID',
                'type' => 'text',
                'description' => 'Your sandbox App ID from Monarch (e.g., "a1b2c3d4").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter sandbox App ID',
            ),
            'test_merchant_org_id' => array(
                'title' => 'Sandbox Merchant Org ID',
                'type' => 'text',
                'description' => 'Your sandbox merchant organization ID from Monarch (e.g., "1234567890").',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter sandbox Merchant Org ID',
            ),
            'live_credentials_heading' => array(
                'title' => 'Production/Live Credentials',
                'type' => 'title',
                'description' => 'These credentials are used when Test Mode is disabled.',
            ),
            'live_api_key' => array(
                'title' => 'Live API Key',
                'type' => 'password',
                'description' => 'Your production API key from Monarch.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter live API key',
            ),
            'live_app_id' => array(
                'title' => 'Live App ID',
                'type' => 'text',
                'description' => 'Your production App ID from Monarch.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter live App ID',
            ),
            'live_merchant_org_id' => array(
                'title' => 'Live Merchant Org ID',
                'type' => 'text',
                'description' => 'Your production merchant organization ID from Monarch.',
                'default' => '',
                'desc_tip' => true,
                'placeholder' => 'Enter live Merchant Org ID',
            ),
        );
    }
    
    public function payment_scripts() {
        if (!is_checkout() && !isset($_GET['pay_for_order'])) {
            return;
        }
        
        if ('no' === $this->enabled) {
            return;
        }
        
        if (empty($this->api_key) || empty($this->app_id)) {
            return;
        }
        
        wp_enqueue_script(
            'wc-monarch-ach',
            WC_MONARCH_ACH_PLUGIN_URL . 'assets/js/monarch-ach.js',
            array('jquery', 'wc-checkout'),
            WC_MONARCH_ACH_VERSION,
            true
        );
        
        wp_enqueue_style(
            'wc-monarch-ach',
            WC_MONARCH_ACH_PLUGIN_URL . 'assets/css/monarch-ach.css',
            array(),
            WC_MONARCH_ACH_VERSION
        );
        
        wp_localize_script('wc-monarch-ach', 'monarch_ach_params', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('monarch_ach_nonce'),
            'test_mode' => $this->testmode ? 'yes' : 'no'
        ));
    }
    
    public function is_available() {
        // Check if gateway is enabled
        if ('yes' !== $this->enabled) {
            return false;
        }
        
        // For Store API, don't block on missing credentials
        if (defined('REST_REQUEST') && REST_REQUEST) {
            return parent::is_available();
        }
        
        // Check if required API credentials are configured
        if (empty($this->api_key) || empty($this->app_id)) {
            return false;
        }
        
        // Check if merchant org ID is configured
        if (empty($this->merchant_org_id)) {
            return false;
        }
        
        // Check if partner name is configured
        if (empty($this->partner_name)) {
            return false;
        }
        
        return parent::is_available();
    }
    
    public function payment_fields() {
        if ($this->description) {
            echo '<p>' . wp_kses_post($this->description) . '</p>';
        }
        
        // Check if customer is already registered with Monarch
        $customer_id = get_current_user_id();
        $monarch_org_id = get_user_meta($customer_id, '_monarch_org_id', true);
        $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);
        
        if ($monarch_org_id && $paytoken_id) {
            // User has both org_id and valid paytoken - ready to pay
            echo '<div class="monarch-bank-connected">';
            echo '<p><strong>Bank account connected</strong></p>';
            echo '<p><a href="#" id="monarch-disconnect-bank" class="monarch-disconnect-link">Use a different bank account</a></p>';
            echo '<input type="hidden" name="monarch_org_id" value="' . esc_attr($monarch_org_id) . '">';
            echo '<input type="hidden" name="monarch_paytoken_id" value="' . esc_attr($paytoken_id) . '">';
            echo '</div>';
            return;
        }

        if ($monarch_org_id && !$paytoken_id) {
            // Returning user: has org_id but paytoken expired after last transaction
            // They need to get a new paytoken by selecting their bank again
            ?>
            <div class="monarch-returning-user">
                <p><strong>Welcome back!</strong></p>
                <p>Please click "Continue with Bank" to authorize this payment.</p>
                <input type="hidden" id="monarch_org_id" name="monarch_org_id" value="<?php echo esc_attr($monarch_org_id); ?>">
                <input type="hidden" id="monarch_paytoken_id" name="monarch_paytoken_id" value="">
                <p class="form-row form-row-wide">
                    <button type="button" id="monarch-reconnect-bank" class="button alt">Continue with Bank</button>
                    <span id="monarch-reconnect-spinner" class="spinner" style="display:none; float:none; margin-left:10px;"></span>
                </p>
                <p><a href="#" id="monarch-use-different-bank" class="monarch-disconnect-link">Use a different bank account</a></p>
            </div>
            <?php
            return;
        }

        ?>
        <div id="monarch-ach-form">
            <div id="monarch-ach-errors" class="woocommerce-error" style="display:none;"></div>

            <!-- Phone Number Field with Edit Mode -->
            <div class="form-row form-row-wide monarch-editable-field" id="monarch-phone-wrapper">
                <label for="monarch_phone">Phone Number <span class="required">*</span></label>
                <div class="monarch-field-input">
                    <input id="monarch_phone" name="monarch_phone" type="tel" required>
                </div>
                <div class="monarch-field-display" style="display:none;">
                    <span class="monarch-field-value" id="monarch_phone_display"></span>
                    <button type="button" class="monarch-edit-btn" data-field="phone">Edit</button>
                </div>
            </div>

            <!-- Date of Birth Field with Edit Mode -->
            <div class="form-row form-row-wide monarch-editable-field" id="monarch-dob-wrapper">
                <label for="monarch_dob">Date of Birth <span class="required">*</span></label>
                <div class="monarch-field-input">
                    <input id="monarch_dob" name="monarch_dob" type="date" required>
                </div>
                <div class="monarch-field-display" style="display:none;">
                    <span class="monarch-field-value" id="monarch_dob_display"></span>
                    <button type="button" class="monarch-edit-btn" data-field="dob">Edit</button>
                </div>
            </div>

            <p class="form-row form-row-wide">
                <button type="button" id="monarch-connect-bank" class="button alt">Connect Bank Account</button>
                <span id="monarch-connect-spinner" class="spinner" style="display:none; float:none; margin-left:10px;"></span>
            </p>

            <p class="monarch-security-notice">
                Your bank details are securely transmitted and encrypted.
            </p>

            <input type="hidden" id="monarch_org_id" name="monarch_org_id" value="">
            <input type="hidden" id="monarch_paytoken_id" name="monarch_paytoken_id" value="">
            <input type="hidden" id="monarch_bank_verified" name="monarch_bank_verified" value="">
            <input type="hidden" id="monarch_entry_method" name="monarch_entry_method" value="auto">
            <input type="hidden" id="monarch_info_confirmed" name="monarch_info_confirmed" value="">
        </div>
        <?php
    }
    
    public function validate_fields() {
        $customer_id = get_current_user_id();
        $monarch_org_id = get_user_meta($customer_id, '_monarch_org_id', true);
        $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);

        // Bank account must be connected through Monarch's verification flow
        if (!$monarch_org_id || !$paytoken_id) {
            wc_add_notice('Please connect your bank account before placing an order.', 'error');
            return false;
        }

        return true;
    }
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $customer_id = $order->get_user_id();
        $is_guest = ($customer_id == 0);

        try {
            if ($is_guest) {
                // For guest checkout, get data from session
                $org_id = WC()->session->get('monarch_org_id');
                $paytoken_id = WC()->session->get('monarch_paytoken_id');
                $org_api_key = WC()->session->get('monarch_org_api_key');
                $org_app_id = WC()->session->get('monarch_org_app_id');
                $monarch_user_id = WC()->session->get('monarch_user_id');
                
                // Store data in order meta for future reference
                if ($org_id) $order->update_meta_data('_monarch_org_id', $org_id);
                if ($paytoken_id) $order->update_meta_data('_monarch_paytoken_id', $paytoken_id);
                if ($monarch_user_id) $order->update_meta_data('_monarch_user_id', $monarch_user_id);
                if ($org_api_key) $order->update_meta_data('_monarch_org_api_key', $org_api_key);
                if ($org_app_id) $order->update_meta_data('_monarch_org_app_id', $org_app_id);
                $order->save();
            } else {
                // For logged-in users, get verified org_id and paytoken_id from user meta
                $org_id = get_user_meta($customer_id, '_monarch_org_id', true);
                $paytoken_id = get_user_meta($customer_id, '_monarch_paytoken_id', true);
                $org_api_key = get_user_meta($customer_id, '_monarch_org_api_key', true);
                $org_app_id = get_user_meta($customer_id, '_monarch_org_app_id', true);

                // Check if the org was created with the same merchant_org_id and environment
                // If settings changed, the old org won't work with new merchant credentials
                $stored_merchant_org_id = get_user_meta($customer_id, '_monarch_merchant_org_id', true);
                $stored_testmode = get_user_meta($customer_id, '_monarch_testmode', true);

                if ($org_id && $stored_merchant_org_id && $stored_merchant_org_id !== $this->merchant_org_id) {
                    // Merchant org changed - old org won't work
                    $logger = WC_Monarch_Logger::instance();
                    $logger->warning('Merchant org ID mismatch - clearing stored credentials', array(
                        'stored_merchant_org_id' => $stored_merchant_org_id,
                        'current_merchant_org_id' => $this->merchant_org_id,
                        'customer_id' => $customer_id
                    ));

                    // Clear all stored Monarch data
                    $this->clear_user_monarch_data($customer_id);
                    $org_id = null;
                    $paytoken_id = null;
                }

                // Check if environment (sandbox/prod) changed
                $current_testmode = $this->testmode ? 'yes' : 'no';
                if ($org_id && $stored_testmode && $stored_testmode !== $current_testmode) {
                    // Environment changed - orgs don't transfer between sandbox and production
                    $logger = WC_Monarch_Logger::instance();
                    $logger->warning('Environment mismatch - clearing stored credentials', array(
                        'stored_testmode' => $stored_testmode,
                        'current_testmode' => $current_testmode,
                        'customer_id' => $customer_id
                    ));

                    $this->clear_user_monarch_data($customer_id);
                    $org_id = null;
                    $paytoken_id = null;
                }
            }

            // Bank account must be connected through Monarch's verification flow
            if (!$org_id || !$paytoken_id) {
                throw new Exception('Please connect your bank account before placing an order.');
            }

            // Credential Strategy: Try purchaser credentials first, fall back to merchant credentials
            // The purchaser's API credentials were returned when the organization was created
            $use_purchaser_credentials = false;

            if (!$is_guest && !empty($org_api_key) && !empty($org_app_id)) {
                // Use purchaser's own credentials for the transaction
                $api_key_for_sale = $org_api_key;
                $app_id_for_sale = $org_app_id;
                $use_purchaser_credentials = true;
                $order->add_order_note('Processing payment with purchaser credentials for org: ' . $org_id);
            } else {
                // Fall back to merchant credentials
                $api_key_for_sale = $this->api_key;
                $app_id_for_sale = $this->app_id;
                $order->add_order_note('Processing payment with merchant credentials for org: ' . $org_id);
            }

            $monarch_api = new Monarch_API(
                $api_key_for_sale,
                $app_id_for_sale,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            $order->add_order_note('Using bank account - orgId: ' . $org_id . ', payTokenId: ' . $paytoken_id . ', credentials: ' . ($use_purchaser_credentials ? 'purchaser' : 'merchant'));

            // Create Sale Transaction
            $transaction_result = $monarch_api->create_sale_transaction(array(
                'amount' => $order->get_total(),
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'comment' => 'Order #' . $order_id . ' - ' . get_bloginfo('name')
            ));

            if (!$transaction_result['success']) {
                $error_message = $transaction_result['error'];
                $status_code = $transaction_result['status_code'] ?? 0;

                $logger = WC_Monarch_Logger::instance();
                $logger->error('Transaction failed', array(
                    'order_id' => $order_id,
                    'error' => $error_message,
                    'status_code' => $status_code,
                    'org_id' => $org_id,
                    'paytoken_id' => $paytoken_id,
                    'merchant_org_id' => $this->merchant_org_id,
                    'testmode' => $this->testmode ? 'yes' : 'no',
                    'full_response' => $transaction_result['response'] ?? null
                ));

                // Log the error for debugging - don't auto-clear user data
                // Show the actual error to the user so they can understand what went wrong
                $logger->debug('Transaction error details', array(
                    'error_message' => $error_message,
                    'status_code' => $status_code,
                    'org_id' => $org_id,
                    'paytoken_id' => $paytoken_id
                ));

                // Simply throw the error - don't try to auto-detect and clear data
                // This prevents false positives and lets the user see the actual problem
                throw new Exception($error_message);
            }

            // Extract transaction ID - Monarch API may return it in various field names
            $data = $transaction_result['data'];
            $transaction_id = $data['id']
                ?? $data['_id']
                ?? $data['transactionId']
                ?? $data['transaction_id']
                ?? $data['txnId']
                ?? $data['txn_id']
                ?? $data['referenceId']
                ?? $data['reference_id']
                ?? '';

            // Log the full response for debugging if transaction ID not found
            $logger = WC_Monarch_Logger::instance();
            if (empty($transaction_id)) {
                $logger->warning('Transaction ID not found in response', array(
                    'order_id' => $order_id,
                    'response_keys' => array_keys($data),
                    'full_response' => $data
                ));
            } else {
                $logger->debug('Transaction created successfully', array(
                    'order_id' => $order_id,
                    'transaction_id' => $transaction_id
                ));
            }

            $order->add_order_note('Transaction created - ID: ' . ($transaction_id ?: 'N/A'));

            // Save transaction ID to order meta (visible in admin) - HPOS compatible
            // Always save even if empty so we can track and debug
            $order->set_transaction_id($transaction_id ?: 'pending');
            $order->update_meta_data('_monarch_transaction_id', $transaction_id ?: 'pending');
            $order->update_meta_data('_monarch_org_id', $org_id);
            $order->update_meta_data('_monarch_paytoken_id', $paytoken_id);
            $order->update_meta_data('_monarch_api_response', json_encode($data));
            $order->save();

            // Save transaction data - include order total since API response may not have it
            $this->save_transaction_data($order_id, $data, $org_id, $paytoken_id, $order->get_total());

            $order->payment_complete($transaction_id);
            $order->add_order_note('ACH payment processed. Transaction ID: ' . ($transaction_id ?: 'N/A'));

            // IMPORTANT: Clear the paytoken after successful transaction
            // Per Monarch: "once the latest pay token is used for a transaction, it expires and cannot be reused"
            // The org_id remains valid - user can get a new paytoken by selecting their bank again
            if (!$is_guest && $customer_id) {
                delete_user_meta($customer_id, '_monarch_paytoken_id');
                $logger->debug('Cleared expired paytoken after successful transaction', array(
                    'customer_id' => $customer_id,
                    'order_id' => $order_id,
                    'used_paytoken' => $paytoken_id
                ));
            } elseif ($is_guest) {
                // For guest users, clear from session
                WC()->session->__unset('monarch_paytoken_id');
            }

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );

        } catch (Exception $e) {
            wc_add_notice('Payment failed: ' . $e->getMessage(), 'error');
            return array('result' => 'fail', 'redirect' => '');
        }
    }
    
    private function setup_customer_and_bank_account($order, $monarch_api) {
        $customer_data = $this->prepare_customer_data($order);
        
        // Create organization
        $org_result = $monarch_api->create_organization($customer_data);
        if (!$org_result['success']) {
            return array('success' => false, 'error' => $org_result['error']);
        }
        
        $user_id = $org_result['data']['_id'];
        $org_id = $org_result['data']['orgId'];
        
        // Create PayToken
        $bank_data = $this->prepare_bank_data();
        $paytoken_result = $monarch_api->create_paytoken($user_id, $bank_data);
        if (!$paytoken_result['success']) {
            return array('success' => false, 'error' => $paytoken_result['error']);
        }
        
        $paytoken_id = $paytoken_result['data']['_id'];
        
        // Assign PayToken
        $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);
        if (!$assign_result['success']) {
            return array('success' => false, 'error' => $assign_result['error']);
        }
        
        // Save to user meta
        $customer_id = $order->get_user_id();
        if ($customer_id) {
            update_user_meta($customer_id, '_monarch_org_id', $org_id);
            update_user_meta($customer_id, '_monarch_user_id', $user_id);
            update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);
            update_user_meta($customer_id, '_monarch_connected_date', current_time('mysql'));

            // Log customer creation
            $logger = WC_Monarch_Logger::instance();
            $logger->log_customer_event('customer_created', $customer_id, array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id
            ));
        }
        
        return array(
            'success' => true,
            'org_id' => $org_id,
            'paytoken_id' => $paytoken_id
        );
    }
    
    private function prepare_customer_data($order) {
        return array(
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'email' => $order->get_billing_email(),
            'password' => wp_generate_password(),
            'phone' => sanitize_text_field($_POST['monarch_phone']),
            'company_name' => sanitize_text_field($_POST['monarch_company']) ?: $order->get_billing_company(),
            'dob' => sanitize_text_field($_POST['monarch_dob']),
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'state' => $order->get_billing_state(),
            'zip' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country()
        );
    }
    
    private function prepare_bank_data() {
        return array(
            'bank_name' => sanitize_text_field($_POST['monarch_bank_name']),
            'account_number' => sanitize_text_field($_POST['monarch_account_number']),
            'routing_number' => sanitize_text_field($_POST['monarch_routing_number']),
            'account_type' => sanitize_text_field($_POST['monarch_account_type'])
        );
    }
    
    private function save_transaction_data($order_id, $transaction_data, $org_id, $paytoken_id, $order_total = 0) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'monarch_ach_transactions';

        // Extract transaction ID from various possible field names
        $transaction_id = $transaction_data['id']
            ?? $transaction_data['_id']
            ?? $transaction_data['transactionId']
            ?? $transaction_data['transaction_id']
            ?? $transaction_data['txnId']
            ?? $transaction_data['txn_id']
            ?? $transaction_data['referenceId']
            ?? $transaction_data['reference_id']
            ?? 'txn_' . uniqid();

        // Use order total if provided, otherwise try to get from API response
        $amount = floatval($order_total) > 0 ? floatval($order_total) : floatval($transaction_data['amount'] ?? 0);

        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'transaction_id' => $transaction_id,
                'monarch_org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'amount' => $amount,
                'currency' => 'USD',
                'status' => $transaction_data['status'] ?? 'pending',
                'api_response' => json_encode($transaction_data)
            ),
            array('%d', '%s', '%s', '%s', '%f', '%s', '%s', '%s')
        );
    }
    
    public function thankyou_page() {
        echo '<p>Thank you for your payment. Your ACH transaction is being processed and you will receive confirmation once complete.</p>';
    }

    /**
     * Display transaction details to customers on order view page (My Account � Orders � View)
     */
    public function display_transaction_details_for_customer($order) {
        // Only show for Monarch ACH payments
        if ($order->get_payment_method() !== 'monarch_ach') {
            return;
        }

        // Get transaction data from database
        global $wpdb;
        $table_name = $wpdb->prefix . 'monarch_ach_transactions';
        $transaction = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE order_id = %d LIMIT 1",
            $order->get_id()
        ));

        if (!$transaction) {
            return;
        }

        // Map status to user-friendly text
        $status_labels = array(
            'pending' => 'Processing',
            'processing' => 'Processing',
            'submitted' => 'Submitted',
            'completed' => 'Completed',
            'success' => 'Completed',
            'settled' => 'Completed',
            'approved' => 'Approved',
            'failed' => 'Failed',
            'declined' => 'Declined',
            'rejected' => 'Rejected',
            'returned' => 'Returned',
            'refunded' => 'Refunded',
            'voided' => 'Cancelled',
            'cancelled' => 'Cancelled'
        );

        $status_text = $status_labels[strtolower($transaction->status)] ?? ucfirst($transaction->status);

        // Status colors
        $status_colors = array(
            'pending' => '#0366d6',
            'processing' => '#0366d6',
            'submitted' => '#0366d6',
            'completed' => '#22863a',
            'success' => '#22863a',
            'settled' => '#22863a',
            'approved' => '#22863a',
            'failed' => '#cb2431',
            'declined' => '#cb2431',
            'rejected' => '#cb2431',
            'returned' => '#cb2431',
            'refunded' => '#6f42c1',
            'voided' => '#6a737d',
            'cancelled' => '#6a737d'
        );

        $status_color = $status_colors[strtolower($transaction->status)] ?? '#6a737d';

        ?>
        <section class="woocommerce-monarch-transaction-details">
            <h2>ACH Payment Details</h2>
            <table class="woocommerce-table shop_table monarch-transaction-table">
                <tbody>
                    <tr>
                        <th>Payment Method</th>
                        <td>ACH Bank Transfer</td>
                    </tr>
                    <tr>
                        <th>Transaction ID</th>
                        <td><code style="font-size: 12px;"><?php echo esc_html($transaction->transaction_id); ?></code></td>
                    </tr>
                    <tr>
                        <th>Payment Status</th>
                        <td>
                            <span style="background: <?php echo esc_attr($status_color); ?>15; color: <?php echo esc_attr($status_color); ?>; padding: 4px 10px; border-radius: 4px; font-weight: 500; font-size: 13px;">
                                <?php echo esc_html($status_text); ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <th>Amount</th>
                        <td><?php echo wc_price($transaction->amount); ?></td>
                    </tr>
                    <tr>
                        <th>Date</th>
                        <td><?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($transaction->created_at))); ?></td>
                    </tr>
                </tbody>
            </table>

            <?php if (in_array(strtolower($transaction->status), array('pending', 'processing', 'submitted'))): ?>
            <p class="monarch-processing-notice" style="margin-top: 15px; padding: 12px; background: #f0f6fc; border-left: 4px solid #0366d6; font-size: 14px;">
                <strong>Note:</strong> ACH bank transfers typically take 2-5 business days to complete.
                You will receive an email notification once your payment has been processed.
            </p>
            <?php endif; ?>
        </section>
        <?php
    }
    
    /**
     * AJAX handler for creating organization and getting bank linking URL
     */
    public function ajax_create_organization() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        // Support both logged in users and guest checkout
        $is_guest = !is_user_logged_in();
        
        if ($is_guest) {
            // For guest checkout, require session ID for data storage
            if (!WC()->session || !WC()->session->get_session_cookie()) {
                wp_send_json_error('Session required for guest checkout');
                return;
            }
        }

        // Log credentials being used for debugging
        $logger = WC_Monarch_Logger::instance();
        $logger->debug('ajax_create_organization called', array(
            'api_key_last_4' => substr($this->api_key, -4),
            'app_id_last_4' => substr($this->app_id, -4),
            'merchant_org_id' => $this->merchant_org_id,
            'parent_org_id' => $this->merchant_org_id,
            'testmode' => $this->testmode ? 'yes' : 'no',
            'base_url' => $this->testmode ? 'https://devapi.monarch.is/v1' : 'https://api.monarch.is/v1'
        ));

        try {
            $monarch_api = new Monarch_API(
                $this->api_key,
                $this->app_id,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );
            
            // Get user data - support both logged in users and guest checkout
            // IMPORTANT: Always use billing_email from form (real-time input), NOT cached WordPress user email
            $form_email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';

            if ($is_guest) {
                // For guest checkout, use billing email from form
                $user_email = $form_email;
                $user_id = 'guest_' . substr(md5($user_email . time()), 0, 8);
            } else {
                $current_user = wp_get_current_user();
                // Use form email if provided, otherwise fall back to WordPress user email
                $user_email = !empty($form_email) ? $form_email : $current_user->user_email;
                $user_id = $current_user->ID;
            }

            // IMPORTANT: Always prioritize form data (real-time input) over cached WordPress user data
            // Get form values first
            $form_first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : '';
            $form_last_name = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : '';

            // Use form data if provided, otherwise fall back to WordPress user data
            if ($is_guest) {
                $first_name = $form_first_name;
                $last_name = $form_last_name;
            } else {
                $first_name = !empty($form_first_name) ? $form_first_name : $current_user->user_firstname;
                $last_name = !empty($form_last_name) ? $form_last_name : $current_user->user_lastname;
            }

            $logger->debug('Customer data being used for organization', array(
                'form_email' => $form_email,
                'form_first_name' => $form_first_name,
                'form_last_name' => $form_last_name,
                'wp_user_email' => $is_guest ? 'N/A (guest)' : $current_user->user_email,
                'wp_first_name' => $is_guest ? 'N/A (guest)' : $current_user->user_firstname,
                'wp_last_name' => $is_guest ? 'N/A (guest)' : $current_user->user_lastname,
                'final_email' => $user_email,
                'final_first_name' => $first_name,
                'final_last_name' => $last_name,
                'is_guest' => $is_guest
            ));

            // Prepare customer data - ALL fields use form data (real-time input)
            $phone = preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['monarch_phone']));
            $phone = substr($phone, -10); // Last 10 digits

            $dob_raw = sanitize_text_field($_POST['monarch_dob']);
            $dob = date('m/d/Y', strtotime($dob_raw)); // Convert to mm/dd/yyyy

            $customer_data = array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $user_email,
                'password' => wp_generate_password(16, true, true),
                'phone' => $phone,
                'company_name' => sanitize_text_field($_POST['monarch_company']),
                'dob' => $dob,
                'address_1' => sanitize_text_field($_POST['billing_address_1']),
                'address_2' => sanitize_text_field($_POST['billing_address_2']),
                'city' => sanitize_text_field($_POST['billing_city']),
                'state' => sanitize_text_field($_POST['billing_state']),
                'zip' => sanitize_text_field($_POST['billing_postcode']),
                'country' => sanitize_text_field($_POST['billing_country'])
            );

            // STEP 1: Try to create organization first
            $logger->debug('Creating organization', array('email' => $user_email));
            $org_result = $monarch_api->create_organization($customer_data);

            if ($org_result['success']) {
                // NEW USER - organization created successfully
                $user_id = $org_result['data']['_id'];
                $org_id = $org_result['data']['orgId'];
                $bank_linking_url = $org_result['data']['partner_embedded_url'] ?? '';

                $logger->debug('New organization created', array(
                    'org_id' => $org_id,
                    'user_id' => $user_id,
                    'has_bank_linking_url' => !empty($bank_linking_url)
                ));

                // Save org data
                if ($is_guest) {
                    WC()->session->set('monarch_temp_org_id', $org_id);
                    WC()->session->set('monarch_temp_user_id', $user_id);
                } else {
                    $customer_id = get_current_user_id();
                    update_user_meta($customer_id, '_monarch_temp_org_id', $org_id);
                    update_user_meta($customer_id, '_monarch_temp_user_id', $user_id);
                }

                wp_send_json_success(array(
                    'org_id' => $org_id,
                    'user_id' => $user_id,
                    'bank_linking_url' => $bank_linking_url,
                    'existing_user' => false
                ));
                return;
            }

            // Organization creation failed - check if email already exists
            $error_msg = strtolower($org_result['error'] ?? '');
            $is_email_exists_error = strpos($error_msg, 'email') !== false &&
                (strpos($error_msg, 'already') !== false || strpos($error_msg, 'exists') !== false || strpos($error_msg, 'in use') !== false);

            if (!$is_email_exists_error) {
                // Some other error - return it
                wp_send_json_error($org_result['error']);
                return;
            }

            // EXISTING USER - call /merchants/verify to get partner_embedded_url
            $logger->debug('Email exists - calling /merchants/verify', array('email' => $user_email));
            $verify_result = $monarch_api->get_user_by_email($user_email);

            if (!$verify_result['success'] || empty($verify_result['data'])) {
                $logger->error('/merchants/verify failed for existing user', array(
                    'email' => $user_email,
                    'error' => $verify_result['error'] ?? 'unknown'
                ));
                wp_send_json_error('Unable to retrieve your account. Please contact support.');
                return;
            }

            // Got existing user data
            $org_id = $verify_result['data']['orgId'] ?? null;
            $user_id = $verify_result['data']['userId'] ?? null;
            $bank_linking_url = $verify_result['data']['partner_embedded_url'] ?? '';

            $logger->debug('Existing user found via /merchants/verify', array(
                'org_id' => $org_id,
                'user_id' => $user_id,
                'has_bank_linking_url' => !empty($bank_linking_url)
            ));

            if (!$org_id) {
                wp_send_json_error('Unable to retrieve your organization. Please contact support.');
                return;
            }

            // Save org data
            if ($is_guest) {
                WC()->session->set('monarch_temp_org_id', $org_id);
                WC()->session->set('monarch_temp_user_id', $user_id);
            } else {
                $customer_id = get_current_user_id();
                update_user_meta($customer_id, '_monarch_temp_org_id', $org_id);
                update_user_meta($customer_id, '_monarch_temp_user_id', $user_id);
            }

            // Return existing user with bank linking URL
            wp_send_json_success(array(
                'org_id' => $org_id,
                'user_id' => $user_id,
                'bank_linking_url' => $bank_linking_url,
                'existing_user' => true
            ));

        } catch (Exception $e) {
            wp_send_json_error('Organization creation failed: ' . $e->getMessage());
        }
    }
    
    /**
     * AJAX handler for bank connection completion
     *
     * For EMBEDDED bank linking flow:
     * - PayToken is automatically created and assigned by Monarch when user completes bank linking
     * - We only need to save the paytoken_id retrieved from getLatestPayToken
     * - NO need to call assign_paytoken() - Monarch handles this automatically
     */
    public function ajax_bank_connection_complete() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        // Support both logged in users and guest checkout
        $is_guest = !is_user_logged_in();
        
        if ($is_guest) {
            // For guest checkout, get data from session
            $org_id = WC()->session->get('monarch_temp_org_id');
        } else {
            // For logged-in users, get from user meta
            $customer_id = get_current_user_id();
            $org_id = get_user_meta($customer_id, '_monarch_temp_org_id', true);
        }
        
        // Get user_id based on user type
        if ($is_guest) {
            $user_id = WC()->session->get('monarch_temp_user_id');
        } else {
            $user_id = get_user_meta($customer_id, '_monarch_temp_user_id', true);
        }
        
        $paytoken_id = sanitize_text_field($_POST['paytoken_id']);

        if (!$org_id || !$user_id || !$paytoken_id) {
            wp_send_json_error('Missing organization or paytoken data');
        }

        try {
            $logger = WC_Monarch_Logger::instance();

            // For embedded bank linking, PayToken is already created and assigned by Monarch
            // We just need to save the data to user meta
            $logger->debug('Bank connection complete - saving data', array(
                'org_id' => $org_id,
                'user_id' => $user_id,
                'paytoken_id' => $paytoken_id,
                'flow' => 'embedded_bank_linking'
            ));

            // Save permanent data based on user type
            if ($is_guest) {
                // For guest users, store in session (will be moved to order meta during checkout)
                WC()->session->set('monarch_org_id', $org_id);
                WC()->session->set('monarch_user_id', $user_id);
                WC()->session->set('monarch_paytoken_id', $paytoken_id);
                WC()->session->set('monarch_connected_date', current_time('mysql'));
                
                // Clean up temporary session data
                WC()->session->__unset('monarch_temp_org_id');
                WC()->session->__unset('monarch_temp_user_id');
            } else {
                // Save permanent user data for logged-in users
                update_user_meta($customer_id, '_monarch_org_id', $org_id);
                update_user_meta($customer_id, '_monarch_user_id', $user_id);
                update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);
                update_user_meta($customer_id, '_monarch_connected_date', current_time('mysql'));

                // IMPORTANT: Store the merchant_org_id and testmode used when creating this connection
                // This allows us to detect if settings changed and the old org won't work
                update_user_meta($customer_id, '_monarch_merchant_org_id', $this->merchant_org_id);
                update_user_meta($customer_id, '_monarch_testmode', $this->testmode ? 'yes' : 'no');

                // Copy temp API credentials to permanent
                $temp_api_key = get_user_meta($customer_id, '_monarch_temp_org_api_key', true);
                $temp_app_id = get_user_meta($customer_id, '_monarch_temp_org_app_id', true);
                if ($temp_api_key && $temp_app_id) {
                    update_user_meta($customer_id, '_monarch_org_api_key', $temp_api_key);
                    update_user_meta($customer_id, '_monarch_org_app_id', $temp_app_id);
                }

                // Clean up temporary data
                delete_user_meta($customer_id, '_monarch_temp_org_id');
                delete_user_meta($customer_id, '_monarch_temp_user_id');
                delete_user_meta($customer_id, '_monarch_temp_org_api_key');
                delete_user_meta($customer_id, '_monarch_temp_org_app_id');
            }

            // Log bank connection
            $logger->log_customer_event('bank_connected', $customer_id, array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id
            ));

            wp_send_json_success(array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id
            ));

        } catch (Exception $e) {
            wp_send_json_error('Bank connection completion failed: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for checking bank connection status
     *
     * IMPORTANT: Must use the PURCHASER's API credentials
     * The orgId must be associated with the security headers being used.
     */
    public function ajax_check_bank_status() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        // Support both logged in users and guest checkout
        $is_guest = !is_user_logged_in();
        $org_id = sanitize_text_field($_POST['org_id']);

        if (!$org_id) {
            wp_send_json_error('Organization ID is required');
        }

        try {
            if ($is_guest) {
                // For guest checkout, verify session exists
                if (!WC()->session || !WC()->session->get_session_cookie()) {
                    wp_send_json_error('Session required for guest checkout');
                    return;
                }
            } else {
                $customer_id = get_current_user_id();
            }

            // IMPORTANT: Use the PURCHASER's API credentials
            // The orgId must be associated with the security headers being used
            if ($is_guest) {
                // For guest checkout, credentials should be in session from organization creation
                $temp_api_data = WC()->session->get('monarch_temp_api_data');
                $purchaser_api_key = $temp_api_data['api']['sandbox']['api_key'] ?? null;
                $purchaser_app_id = $temp_api_data['api']['sandbox']['app_id'] ?? null;
            } else {
                // Check BOTH temp (new registration) AND permanent (returning user) credentials
                $purchaser_api_key = get_user_meta($customer_id, '_monarch_temp_org_api_key', true);
                if (empty($purchaser_api_key)) {
                    // Returning user - try permanent credentials
                    $purchaser_api_key = get_user_meta($customer_id, '_monarch_org_api_key', true);
                }
                $purchaser_app_id = get_user_meta($customer_id, '_monarch_temp_org_app_id', true);
                if (empty($purchaser_app_id)) {
                    // Returning user - try permanent credentials
                    $purchaser_app_id = get_user_meta($customer_id, '_monarch_org_app_id', true);
                }
            }

            // Use purchaser credentials if available, otherwise fall back to merchant credentials
            $api_key_to_use = $purchaser_api_key ?: $this->api_key;
            $app_id_to_use = $purchaser_app_id ?: $this->app_id;

            // Query Monarch API to get latest paytoken for the organization
            $api_url = $this->testmode
                ? 'https://devapi.monarch.is/v1'
                : 'https://api.monarch.is/v1';

            // Use /getlatestpaytoken/ endpoint which works with merchant credentials for child organizations
            $response = wp_remote_get($api_url . '/getlatestpaytoken/' . $org_id, array(
                'headers' => array(
                    'accept' => 'application/json',
                    'X-API-KEY' => $api_key_to_use,
                    'X-APP-ID' => $app_id_to_use
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                wp_send_json_error('Failed to check bank status: ' . $response->get_error_message());
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code >= 200 && $status_code < 300) {
                // The /getlatestpaytoken/ endpoint returns the latest paytoken directly
                $paytoken_id = $body['_id'] ?? $body['payToken'] ?? $body['id'] ?? null;

                if (!empty($paytoken_id)) {
                    wp_send_json_success(array(
                        'connected' => true,
                        'paytoken_id' => $paytoken_id,
                        'org_id' => $org_id
                    ));
                } else {
                    wp_send_json_success(array(
                        'connected' => false,
                        'message' => 'No bank account connected yet'
                    ));
                }
            } else {
                wp_send_json_error('Failed to retrieve organization status');
            }

        } catch (Exception $e) {
            wp_send_json_error('Error checking bank status: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for getting latest paytoken after embedded bank linking
     * This is the correct flow per Monarch documentation:
     * After user links bank in iframe, call /v1/getlatestpaytoken/[organizationID]
     *
     * IMPORTANT: Must use the PURCHASER's API credentials (returned when organization was created)
     * NOT the merchant's credentials. The orgId must be associated with the security headers.
     */
    public function ajax_get_latest_paytoken() {
        $logger = WC_Monarch_Logger::instance();

        // Verify nonce
        if (!check_ajax_referer('monarch_ach_nonce', 'nonce', false)) {
            $logger->error('Nonce verification failed');
            wp_send_json_error('Security check failed. Please refresh the page and try again.');
            return;
        }

        // Check if user is logged in (allow guest checkout)
        if (!is_user_logged_in() && !isset($_POST['org_id'])) {
            wp_send_json_error('User must be logged in or provide organization ID');
            return;
        }

        $org_id = isset($_POST['org_id']) ? sanitize_text_field($_POST['org_id']) : '';

        if (empty($org_id)) {
            wp_send_json_error('Organization ID is required');
            return;
        }

        try {
            $customer_id = get_current_user_id();

            // IMPORTANT: Use the PURCHASER's API credentials for getLatestPayToken
            // The orgId must be associated with the security headers being used
            // Check BOTH temp (new registration) AND permanent (returning user) credentials
            $purchaser_api_key = get_user_meta($customer_id, '_monarch_temp_org_api_key', true);
            if (empty($purchaser_api_key)) {
                // Returning user - try permanent credentials
                $purchaser_api_key = get_user_meta($customer_id, '_monarch_org_api_key', true);
            }
            $purchaser_app_id = get_user_meta($customer_id, '_monarch_temp_org_app_id', true);
            if (empty($purchaser_app_id)) {
                // Returning user - try permanent credentials
                $purchaser_app_id = get_user_meta($customer_id, '_monarch_org_app_id', true);
            }

            // Log credentials being used
            $logger->debug('ajax_get_latest_paytoken called', array(
                'org_id' => $org_id,
                'using_purchaser_credentials' => !empty($purchaser_api_key),
                'purchaser_api_key_last_4' => $purchaser_api_key ? substr($purchaser_api_key, -4) : 'N/A',
                'purchaser_app_id' => $purchaser_app_id ?: 'N/A',
                'merchant_api_key_last_4' => substr($this->api_key, -4),
                'merchant_app_id' => $this->app_id,
                'testmode' => $this->testmode ? 'yes' : 'no'
            ));

            // Use purchaser credentials if available, otherwise fall back to merchant credentials
            $api_key_to_use = $purchaser_api_key ?: $this->api_key;
            $app_id_to_use = $purchaser_app_id ?: $this->app_id;

            // Initialize Monarch API with the correct credentials
            $monarch_api = new Monarch_API(
                $api_key_to_use,
                $app_id_to_use,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            // Call getLatestPayToken API endpoint
            $result = $monarch_api->get_latest_paytoken($org_id);

            if ($result['success']) {
                $data = $result['data'];

                // Extract paytoken ID from response
                // The API may return it in different formats, so check multiple possible fields
                $paytoken_id = $data['_id'] ?? $data['payTokenId'] ?? $data['paytoken_id'] ?? $data['payToken'] ?? null;

                if ($paytoken_id) {
                    // Store the paytoken for the current user
                    $customer_id = get_current_user_id();
                    update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);

                    // Also store org_id permanently now that bank is linked
                    $temp_org_id = get_user_meta($customer_id, '_monarch_temp_org_id', true);
                    if ($temp_org_id) {
                        update_user_meta($customer_id, '_monarch_org_id', $temp_org_id);
                    }

                    // Log success
                    $logger->log_customer_event('paytoken_retrieved', $customer_id, array(
                        'org_id' => $org_id,
                        'paytoken_id' => $paytoken_id
                    ));

                    wp_send_json_success(array(
                        'connected' => true,
                        'paytoken_id' => $paytoken_id,
                        'org_id' => $org_id,
                        'message' => 'Bank account connected successfully'
                    ));
                } else {
                    // No paytoken found - bank linking may not be complete yet
                    $logger->debug('PayToken not found in response', array(
                        'org_id' => $org_id,
                        'response_data' => $data
                    ));
                    wp_send_json_error('PayToken not found. Please complete bank linking first.');
                }
            } else {
                // API call failed - could be 404 (no paytoken) or other error
                $error_message = $result['error'] ?? 'Failed to retrieve paytoken';
                $status_code = $result['status_code'] ?? 0;

                $logger->debug('getLatestPayToken API failed', array(
                    'org_id' => $org_id,
                    'error' => $error_message,
                    'status_code' => $status_code,
                    'using_purchaser_credentials' => !empty($purchaser_api_key),
                    'full_response' => $result
                ));

                // 404 typically means no paytoken exists yet
                if ($status_code == 404 || strpos(strtolower($error_message), 'not found') !== false) {
                    $creds_info = !empty($purchaser_api_key) ? '(using purchaser credentials)' : '(using merchant credentials - purchaser credentials not found)';
                    wp_send_json_error('PayToken not found ' . $creds_info . '. Bank linking may not be complete yet.');
                } else {
                    $creds_info = !empty($purchaser_api_key) ? '(purchaser creds)' : '(merchant creds)';
                    wp_send_json_error($error_message . ' ' . $creds_info);
                }
            }

        } catch (Exception $e) {
            wp_send_json_error('Error retrieving paytoken: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for disconnecting bank account
     */
    public function ajax_disconnect_bank() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error('User must be logged in');
        }

        $customer_id = get_current_user_id();

        // Delete all Monarch-related user meta using helper
        $this->clear_user_monarch_data($customer_id);

        // Log the disconnection
        $logger = WC_Monarch_Logger::instance();
        $logger->log_customer_event('bank_disconnected', $customer_id, array());

        wp_send_json_success(array('message' => 'Bank account disconnected successfully'));
    }

    /**
     * AJAX handler for getting bank linking URL for returning users
     * Used when a user has an org_id but their paytoken has expired after a transaction
     * Per Monarch: "They can simply select the bank and proceed by clicking Continue"
     */
    public function ajax_get_bank_linking_url() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        $org_id = sanitize_text_field($_POST['org_id']);

        if (empty($org_id)) {
            wp_send_json_error('Organization ID is required');
            return;
        }

        $logger = WC_Monarch_Logger::instance();

        try {
            // Get the user's stored API credentials
            $customer_id = get_current_user_id();
            $purchaser_api_key = get_user_meta($customer_id, '_monarch_org_api_key', true);
            $purchaser_app_id = get_user_meta($customer_id, '_monarch_org_app_id', true);

            // Use purchaser credentials if available, otherwise merchant credentials
            $api_key_to_use = $purchaser_api_key ?: $this->api_key;
            $app_id_to_use = $purchaser_app_id ?: $this->app_id;

            $logger->debug('Getting bank linking URL for returning user', array(
                'org_id' => $org_id,
                'customer_id' => $customer_id,
                'using_purchaser_credentials' => !empty($purchaser_api_key)
            ));

            // FIRST: Check if we have a stored bank linking URL
            $stored_url = get_user_meta($customer_id, '_monarch_bank_linking_url', true);
            if (!empty($stored_url)) {
                $logger->debug('Found stored bank linking URL for returning user', array(
                    'url_length' => strlen($stored_url)
                ));
                wp_send_json_success(array(
                    'bank_linking_url' => $stored_url,
                    'org_id' => $org_id,
                    'source' => 'stored'
                ));
                return;
            }

            // SECOND: Check if user already has a valid paytoken
            $api_url = $this->testmode
                ? 'https://devapi.monarch.is/v1'
                : 'https://api.monarch.is/v1';

            $response = wp_remote_get($api_url . '/getlatestpaytoken/' . $org_id, array(
                'headers' => array(
                    'accept' => 'application/json',
                    'X-API-KEY' => $api_key_to_use,
                    'X-APP-ID' => $app_id_to_use
                ),
                'timeout' => 30
            ));

            if (!is_wp_error($response)) {
                $status_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if ($status_code >= 200 && $status_code < 300) {
                    $paytoken_id = $body['_id'] ?? $body['payToken'] ?? $body['id'] ?? null;

                    if ($paytoken_id) {
                        // User already has a valid paytoken - save it and return
                        update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);

                        $logger->debug('Returning user already has valid paytoken', array(
                            'paytoken_id' => $paytoken_id
                        ));

                        wp_send_json_success(array(
                            'paytoken_id' => $paytoken_id,
                            'org_id' => $org_id,
                            'message' => 'Bank account already connected'
                        ));
                        return;
                    }
                }
            }

            // No valid paytoken - need to get bank linking URL
            // Use the comprehensive helper that tries multiple endpoints
            // Pass the appropriate credentials (purchaser if available, otherwise merchant)
            $logger->debug('ajax_get_bank_linking_url: No valid paytoken, getting bank linking URL', array(
                'org_id' => $org_id,
                'using_purchaser_credentials' => !empty($purchaser_api_key)
            ));

            $bank_linking_url = $this->get_bank_linking_url_for_org($org_id, $logger, $api_key_to_use, $app_id_to_use);

            if (empty($bank_linking_url)) {
                $logger->error('ajax_get_bank_linking_url: Could not retrieve bank linking URL from any endpoint', array(
                    'org_id' => $org_id
                ));

                // Clear the user's Monarch data so they can re-register
                $this->clear_user_monarch_data($customer_id);

                $logger->debug('Cleared user Monarch data - they need to re-register');

                // Return special response to tell frontend to show registration form
                wp_send_json_error(array(
                    'message' => 'Your bank connection needs to be refreshed. Please complete the registration form below to reconnect.',
                    'action' => 'show_registration_form',
                    'cleared_data' => true
                ));
                return;
            }

            // Clean up URL (same logic as create_organization)
            $bank_linking_url = urldecode($bank_linking_url);
            if (strpos($bank_linking_url, 'http%3A') !== false) {
                $bank_linking_url = urldecode($bank_linking_url);
            }

            $logger->debug('Bank linking URL retrieved for returning user', array(
                'org_id' => $org_id,
                'url_length' => strlen($bank_linking_url)
            ));

            wp_send_json_success(array(
                'bank_linking_url' => $bank_linking_url,
                'org_id' => $org_id
            ));

        } catch (Exception $e) {
            wp_send_json_error('Error getting bank linking URL: ' . $e->getMessage());
        }
    }

    /**
     * AJAX handler for manual bank entry
     * Creates organization + paytoken + assigns in one flow
     */
    public function ajax_manual_bank_entry() {
        check_ajax_referer('monarch_ach_nonce', 'nonce');

        // Support both logged in users and guest checkout
        $is_guest = !is_user_logged_in();
        
        if ($is_guest) {
            // For guest checkout, require session for data storage
            if (!WC()->session || !WC()->session->get_session_cookie()) {
                wp_send_json_error('Session required for guest checkout');
                return;
            }
        }

        try {
            $monarch_api = new Monarch_API(
                $this->api_key,
                $this->app_id,
                $this->merchant_org_id,
                $this->partner_name,
                $this->testmode
            );

            // Get user data - support both logged in users and guest checkout
            // IMPORTANT: Always use billing_email from form (real-time input), NOT cached WordPress user email
            $form_email = isset($_POST['billing_email']) ? sanitize_email($_POST['billing_email']) : '';

            $logger = WC_Monarch_Logger::instance();

            if ($is_guest) {
                // For guest checkout, use billing email from form
                $user_email = $form_email;
                $user_id = 'guest_' . substr(md5($user_email . time()), 0, 8);
                $customer_id = null; // No WordPress user ID for guests
            } else {
                $current_user = wp_get_current_user();
                $customer_id = get_current_user_id();
                // Use form email if provided, otherwise fall back to WordPress user email
                $user_email = !empty($form_email) ? $form_email : $current_user->user_email;
                $user_id = $customer_id;
            }

            $logger->debug('Manual entry: Email being used for organization', array(
                'form_email' => $form_email,
                'wp_user_email' => $is_guest ? 'N/A (guest)' : $current_user->user_email,
                'final_email' => $user_email,
                'is_guest' => $is_guest
            ));

            // Validate required fields
            $bank_name = sanitize_text_field($_POST['bank_name']);
            $routing_number = sanitize_text_field($_POST['routing_number']);
            $account_number = sanitize_text_field($_POST['account_number']);
            $account_type = sanitize_text_field($_POST['account_type']);

            if (empty($bank_name) || empty($routing_number) || empty($account_number)) {
                wp_send_json_error('Please fill in all bank details');
            }

            // Validate routing number (9 digits)
            if (!preg_match('/^\d{9}$/', $routing_number)) {
                wp_send_json_error('Routing number must be exactly 9 digits');
            }

            // IMPORTANT: Always prioritize form data (real-time input) over cached WordPress user data
            // Get form values first
            $form_first_name = isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : '';
            $form_last_name = isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : '';

            // Use form data if provided, otherwise fall back to WordPress user data
            if ($is_guest) {
                $first_name = $form_first_name;
                $last_name = $form_last_name;
            } else {
                $first_name = !empty($form_first_name) ? $form_first_name : $current_user->user_firstname;
                $last_name = !empty($form_last_name) ? $form_last_name : $current_user->user_lastname;
            }

            $logger->debug('Manual entry: Customer data being used for organization', array(
                'form_email' => $form_email,
                'form_first_name' => $form_first_name,
                'form_last_name' => $form_last_name,
                'wp_user_email' => $is_guest ? 'N/A (guest)' : $current_user->user_email,
                'wp_first_name' => $is_guest ? 'N/A (guest)' : $current_user->user_firstname,
                'wp_last_name' => $is_guest ? 'N/A (guest)' : $current_user->user_lastname,
                'final_email' => $user_email,
                'final_first_name' => $first_name,
                'final_last_name' => $last_name,
                'is_guest' => $is_guest
            ));

            // Prepare customer data - ALL fields use form data (real-time input)
            $phone = preg_replace('/[^0-9]/', '', sanitize_text_field($_POST['monarch_phone']));
            $phone = substr($phone, -10);

            $dob_raw = sanitize_text_field($_POST['monarch_dob']);
            $dob = date('m/d/Y', strtotime($dob_raw));

            $customer_data = array(
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $user_email,
                'password' => wp_generate_password(16, true, true),
                'phone' => $phone,
                'company_name' => sanitize_text_field($_POST['monarch_company'] ?? ''),
                'dob' => $dob,
                'address_1' => sanitize_text_field($_POST['billing_address_1']),
                'address_2' => sanitize_text_field($_POST['billing_address_2'] ?? ''),
                'city' => sanitize_text_field($_POST['billing_city']),
                'state' => sanitize_text_field($_POST['billing_state']),
                'zip' => sanitize_text_field($_POST['billing_postcode']),
                'country' => sanitize_text_field($_POST['billing_country'])
            );

            // STEP 0: Check if user already exists by email (prevents "email already exists" error)
            $logger->debug('========== MANUAL ENTRY: EMAIL LOOKUP START ==========');
            $logger->debug('Manual entry: Checking if user already exists by email', array('email' => $user_email));
            $existing_user = $monarch_api->get_user_by_email($user_email);

            // Log the COMPLETE response from getUserByEmail for debugging
            $logger->debug('Manual entry: getUserByEmail FULL RESPONSE', array(
                'email_checked' => $user_email,
                'user_exists' => $existing_user['user_exists'] ?? 'NOT SET',
                'success' => $existing_user['success'] ?? 'NOT SET',
                'org_id_direct' => $existing_user['org_id'] ?? 'NOT SET',
                'data' => $existing_user['data'] ?? 'NOT SET',
                'error' => $existing_user['error'] ?? 'NOT SET',
                'status_code' => $existing_user['status_code'] ?? 'NOT SET',
                'full_response' => $existing_user
            ));

            // Check if user exists using the new user_exists flag
            $user_exists = $existing_user['user_exists'] ?? false;
            $found_org_id = null;
            $found_user_id = null;

            if ($user_exists) {
                // User exists - extract org_id
                // First check if API returned org_id directly
                $found_org_id = $existing_user['org_id'] ?? null;

                // If not, search in data
                if (!$found_org_id && !empty($existing_user['data'])) {
                    $data = $existing_user['data'];
                    $found_org_id = $data['orgId'] ?? $data['org_id'] ?? $data['organizationId'] ?? $data['organization_id'] ?? null;
                    $found_user_id = $data['_id'] ?? $data['userId'] ?? $data['user_id'] ?? $data['id'] ?? null;
                }

                // Deep search as last resort
                if (!$found_org_id) {
                    $found_org_id = $this->find_org_id_recursive($existing_user);
                }

                $logger->debug('Manual entry: User EXISTS - extracted org_id', array(
                    'found_org_id' => $found_org_id,
                    'found_user_id' => $found_user_id
                ));
            }

            $logger->debug('========== MANUAL ENTRY: EMAIL LOOKUP END ==========', array(
                'user_exists' => $user_exists,
                'final_org_id' => $found_org_id,
                'final_user_id' => $found_user_id,
                'will_use_existing' => $user_exists && !empty($found_org_id)
            ));

            $org_id = $found_org_id;
            $user_id = $found_user_id;
            $org_result = null; // Initialize to prevent undefined variable errors
            $is_existing_user = $user_exists && !empty($found_org_id);

            if ($is_existing_user) {
                $logger->debug('Manual entry: SUCCESS - Existing user found, will NOT create new organization', array(
                    'email' => $user_email,
                    'org_id' => $org_id,
                    'user_id' => $user_id
                ));
            }

            // Step 1: Create organization (only if user doesn't exist)
            if (!$org_id) {
                $logger->debug('========== MANUAL ENTRY: USER NOT FOUND - CREATING NEW ORG ==========');
                $logger->debug('Manual entry: User was NOT found by email lookup. Proceeding to create new organization.', array(
                    'email' => $user_email,
                    'user_exists_flag' => $user_exists,
                    'api_error' => $existing_user['error'] ?? 'none',
                    'status_code' => $existing_user['status_code'] ?? 'unknown'
                ));
                $org_result = $monarch_api->create_organization($customer_data);

                if (!$org_result['success']) {
                    // Check if the error is "Email already in use"
                    $error_msg = strtolower($org_result['error'] ?? '');
                    if (strpos($error_msg, 'email') !== false && (strpos($error_msg, 'already') !== false || strpos($error_msg, 'exists') !== false || strpos($error_msg, 'in use') !== false)) {
                        $logger->debug('Manual entry: Email exists error - retrying lookup', array(
                            'email' => $user_email,
                            'error' => $org_result['error']
                        ));

                        // Retry the email lookup
                        $retry_lookup = $monarch_api->get_user_by_email($user_email);
                        if ($retry_lookup['success'] && !empty($retry_lookup['data'])) {
                            $org_id = $retry_lookup['org_id'] ?? $retry_lookup['data']['orgId'] ?? null;
                            $user_id = $retry_lookup['data']['_id'] ?? $retry_lookup['data']['userId'] ?? null;
                            $is_existing_user = true;

                            $logger->debug('Manual entry: Found org on retry', array(
                                'org_id' => $org_id,
                                'user_id' => $user_id
                            ));
                        } else {
                            wp_send_json_error('This email is already registered but could not be found. Please contact support.');
                            return;
                        }
                    } else {
                        wp_send_json_error('Organization creation failed: ' . $org_result['error']);
                        return;
                    }
                } else {
                    $user_id = $org_result['data']['_id'];
                    $org_id = $org_result['data']['orgId'];

                    // Log organization creation
                    $logger->log_customer_event('organization_created_manual', $customer_id, array(
                        'org_id' => $org_id,
                        'user_id' => $user_id
                    ));
                }
            } else {
                $logger->debug('Manual entry: Using existing organization', array(
                    'org_id' => $org_id,
                    'user_id' => $user_id
                ));
            }

            // Step 2: Create PayToken with bank details
            $bank_data = array(
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'routing_number' => $routing_number,
                'account_type' => $account_type
            );

            $paytoken_result = $monarch_api->create_paytoken($user_id, $bank_data);

            if (!$paytoken_result['success']) {
                wp_send_json_error('Bank account setup failed: ' . $paytoken_result['error']);
            }

            $paytoken_id = $paytoken_result['data']['payToken'] ?? $paytoken_result['data']['_id'];

            // Step 3: Assign PayToken to organization
            $assign_result = $monarch_api->assign_paytoken($paytoken_id, $org_id);

            if (!$assign_result['success']) {
                wp_send_json_error('Failed to link bank account: ' . $assign_result['error']);
            }

            // Save permanent data based on user type
            if ($is_guest) {
                // For guest users, store in session (will be moved to order meta during checkout)
                WC()->session->set('monarch_org_id', $org_id);
                WC()->session->set('monarch_user_id', $user_id);
                WC()->session->set('monarch_paytoken_id', $paytoken_id);
                WC()->session->set('monarch_connected_date', current_time('mysql'));

                // Store the purchaser org's API credentials for transactions (only for new organizations)
                if ($org_result && isset($org_result['data']['api'])) {
                    $org_api = $org_result['data']['api'];
                    $credentials_key = $this->testmode ? 'sandbox' : 'prod';
                    $org_credentials = $org_api[$credentials_key] ?? null;
                    if ($org_credentials) {
                        WC()->session->set('monarch_org_api_key', $org_credentials['api_key']);
                        WC()->session->set('monarch_org_app_id', $org_credentials['app_id']);
                    }
                }
            } else {
                // Save permanent user data for logged-in users
                update_user_meta($customer_id, '_monarch_org_id', $org_id);
                update_user_meta($customer_id, '_monarch_user_id', $user_id);
                update_user_meta($customer_id, '_monarch_paytoken_id', $paytoken_id);
                update_user_meta($customer_id, '_monarch_connected_date', current_time('mysql'));

                // IMPORTANT: Store the merchant_org_id and testmode used when creating this connection
                update_user_meta($customer_id, '_monarch_merchant_org_id', $this->merchant_org_id);
                update_user_meta($customer_id, '_monarch_testmode', $this->testmode ? 'yes' : 'no');

                // Store the purchaser org's API credentials for transactions (only for new organizations)
                if ($org_result && isset($org_result['data']['api'])) {
                    $org_api = $org_result['data']['api'];
                    $credentials_key = $this->testmode ? 'sandbox' : 'prod';
                    $org_credentials = $org_api[$credentials_key] ?? null;
                    if ($org_credentials) {
                        update_user_meta($customer_id, '_monarch_org_api_key', $org_credentials['api_key']);
                        update_user_meta($customer_id, '_monarch_org_app_id', $org_credentials['app_id']);
                    }
                }
            }

            // Log successful bank connection
            $logger->log_customer_event('bank_connected_manual', $customer_id, array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'bank_name' => $bank_name
            ));

            wp_send_json_success(array(
                'org_id' => $org_id,
                'paytoken_id' => $paytoken_id,
                'message' => 'Bank account connected successfully'
            ));

        } catch (Exception $e) {
            wp_send_json_error('Manual bank entry failed: ' . $e->getMessage());
        }
    }

    /**
     * Clear all stored Monarch data for a user
     * Used when credentials become invalid or environment changes
     */
    private function clear_user_monarch_data($customer_id) {
        delete_user_meta($customer_id, '_monarch_org_id');
        delete_user_meta($customer_id, '_monarch_user_id');
        delete_user_meta($customer_id, '_monarch_paytoken_id');
        delete_user_meta($customer_id, '_monarch_org_api_key');
        delete_user_meta($customer_id, '_monarch_org_app_id');
        delete_user_meta($customer_id, '_monarch_connected_date');
        delete_user_meta($customer_id, '_monarch_merchant_org_id');
        delete_user_meta($customer_id, '_monarch_testmode');
        delete_user_meta($customer_id, '_monarch_temp_org_id');
        delete_user_meta($customer_id, '_monarch_temp_user_id');
        delete_user_meta($customer_id, '_monarch_temp_org_api_key');
        delete_user_meta($customer_id, '_monarch_temp_org_app_id');
        delete_user_meta($customer_id, '_monarch_bank_linking_url');
    }

    /**
     * Verify that an organization exists and is accessible with the given credentials
     * This is a pre-flight check before attempting transactions to provide better error messages
     * Uses /getlatestpaytoken/{org_id} endpoint which validates both org existence and paytoken
     */
    private function verify_organization_exists($org_id, $api_key, $app_id) {
        try {
            $api_url = $this->testmode ? 'https://devapi.monarch.is/v1' : 'https://api.monarch.is/v1';

            $response = wp_remote_get($api_url . '/getlatestpaytoken/' . $org_id, array(
                'headers' => array(
                    'accept' => 'application/json',
                    'X-API-KEY' => $api_key,
                    'X-APP-ID' => $app_id
                ),
                'timeout' => 15 // Shorter timeout for pre-flight check
            ));

            if (is_wp_error($response)) {
                // Network error - don't fail, let the transaction attempt proceed
                return array(
                    'valid' => true, // Assume valid on network error
                    'error' => 'Network check skipped: ' . $response->get_error_message()
                );
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            // 2xx status codes indicate the org exists
            if ($status_code >= 200 && $status_code < 300) {
                return array(
                    'valid' => true,
                    'error' => null,
                    'paytoken_id' => $body['_id'] ?? $body['payToken'] ?? null
                );
            }

            // 404 means org not found - this is a definitive "org doesn't exist"
            if ($status_code == 404) {
                return array(
                    'valid' => false,
                    'error' => '404 not found - organization does not exist'
                );
            }

            // 401/403 might mean credential mismatch - still attempt transaction
            if ($status_code == 401 || $status_code == 403) {
                return array(
                    'valid' => true, // Let transaction attempt proceed
                    'error' => 'Auth issue on pre-check (status ' . $status_code . '), will attempt transaction'
                );
            }

            // Other errors - extract message
            $error_msg = $body['error']['message'] ?? $body['message'] ?? 'Unknown error (status ' . $status_code . ')';

            // For most errors, let the transaction attempt proceed
            return array(
                'valid' => true,
                'error' => 'Pre-check warning: ' . $error_msg
            );

        } catch (Exception $e) {
            // On exception, don't block - let transaction attempt proceed
            return array(
                'valid' => true,
                'error' => 'Pre-check exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Validate that a paytoken exists and can be used with the given credentials
     * This prevents "Paytoken is Invalid" errors by verifying credential consistency
     * Uses /getlatestpaytoken/{org_id} endpoint which works with merchant credentials for child organizations
     */
    private function validate_paytoken_with_credentials($org_id, $paytoken_id, $api_key, $app_id) {
        try {
            $api_url = $this->testmode ? 'https://devapi.monarch.is/v1' : 'https://api.monarch.is/v1';

            // Use /getlatestpaytoken/ endpoint to get paytokens for the organization
            $response = wp_remote_get($api_url . '/getlatestpaytoken/' . $org_id, array(
                'headers' => array(
                    'accept' => 'application/json',
                    'X-API-KEY' => $api_key,
                    'X-APP-ID' => $app_id
                ),
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                return array(
                    'valid' => false,
                    'error' => 'Network error: ' . $response->get_error_message()
                );
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code < 200 || $status_code >= 300) {
                $error_msg = $body['error']['message'] ?? $body['message'] ?? 'API error';
                return array(
                    'valid' => false,
                    'error' => "API returned status $status_code: $error_msg"
                );
            }

            // The /getlatestpaytoken/ endpoint returns the latest paytoken directly
            // Check if response contains paytoken data
            $response_paytoken_id = $body['_id'] ?? $body['payToken'] ?? $body['id'] ?? null;

            if (empty($response_paytoken_id)) {
                return array(
                    'valid' => false,
                    'error' => 'No paytoken found for organization'
                );
            }

            // Verify the paytoken matches what we expect
            if ($response_paytoken_id !== $paytoken_id) {
                // The latest paytoken doesn't match - this could mean the user has a newer bank account linked
                // We'll still consider it valid if we got a paytoken back, as the stored one might be outdated
                $this->log('Paytoken mismatch warning', array(
                    'stored_paytoken' => $paytoken_id,
                    'latest_paytoken' => $response_paytoken_id,
                    'org_id' => $org_id
                ));
            }

            return array(
                'valid' => true,
                'error' => null,
                'latest_paytoken_id' => $response_paytoken_id
            );

        } catch (Exception $e) {
            return array(
                'valid' => false,
                'error' => 'Validation exception: ' . $e->getMessage()
            );
        }
    }

    /**
     * Get stored Monarch credentials for a user (supports both logged-in and guest users)
     * Returns array with org_id, paytoken_id, api_key, app_id
     */
    private function get_user_monarch_credentials($customer_id, $is_guest = false, $temporary = false) {
        $prefix = $temporary ? 'monarch_temp_' : 'monarch_';
        
        if ($is_guest) {
            // For guest users, get from session
            return array(
                'org_id' => WC()->session->get($prefix . 'org_id'),
                'paytoken_id' => WC()->session->get($prefix . 'paytoken_id'),
                'api_key' => WC()->session->get($prefix . 'org_api_key'),
                'app_id' => WC()->session->get($prefix . 'org_app_id'),
                'user_id' => WC()->session->get($prefix . 'user_id')
            );
        } else {
            // For logged-in users, get from user meta
            return array(
                'org_id' => get_user_meta($customer_id, '_' . $prefix . 'org_id', true),
                'paytoken_id' => get_user_meta($customer_id, '_' . $prefix . 'paytoken_id', true),
                'api_key' => get_user_meta($customer_id, '_' . $prefix . 'org_api_key', true),
                'app_id' => get_user_meta($customer_id, '_' . $prefix . 'org_app_id', true),
                'user_id' => get_user_meta($customer_id, '_' . $prefix . 'user_id', true)
            );
        }
    }

    /**
     * Recursively search for orgId in a nested array/response
     */
    private function find_org_id_recursive($data, $depth = 0) {
        if ($depth > 5 || !is_array($data)) {
            return null;
        }

        $org_id_keys = array('orgId', 'org_id', 'organizationId', 'organization_id');

        foreach ($org_id_keys as $key) {
            if (isset($data[$key]) && !empty($data[$key]) && is_string($data[$key])) {
                return $data[$key];
            }
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $found = $this->find_org_id_recursive($value, $depth + 1);
                if ($found) {
                    return $found;
                }
            }
        }

        return null;
    }
}