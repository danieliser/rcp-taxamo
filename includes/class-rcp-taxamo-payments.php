<?php

class RCP_Taxamo_Payments {
	public function __construct() {
		add_filter( 'rcp_paypal_args', array( $this, 'paypal_args' ), 10, 2 );
		add_action( 'rcp_stripe_signup_invoice', array( $this, 'stripe_signup_tax_invoice_item' ), 1, 2 );

		// Sends all payments to Taxamo. Reguardless of tax.
		add_action( 'rcp_insert_payment', array( $this, 'track_payment' ), 10, 3 );
		add_action( 'rcp_stripe_invoice.created', array( $this, 'stripe_recurring_tax_invoice_item' ) );
	}

	/**
	* Processed when invoices are created, open and contain a subscription ID.
	* This only occurs during follow up subscription payments.
	*/
	public function stripe_recurring_tax_invoice_item( $invoice ) {

		global $rcp_options;
		$user = rcp_stripe_get_user_id( $invoice->customer );
		$transaction_key = get_user_meta( $user_id, 'rcp_taxamo_transaction_key', true );
		$subscription_id = rcp_get_subscription_id( 1 );

		// Return if invoice is not open, does not contain a subscription id, or $subscription_id is empty
		if( $invoice->closed || empty($invoice->subscription) || !$subscription_id ) {
			return;
		}

		$user_data = get_userdata( $user );
		$subscription_details 	= rcp_get_subscription_details( $subscription_id );

		/**
		* Initiate Taxamo API.
		*/
		$taxamo = $this->taxamo_api();

		/**
		* Get existing transaction information.
		*/
		$existing_transaction = $taxamo->getTransaction( $transaction_key );
		$existing_transaction = $existing_transaction->transaction;

		/**
		* Build a new empty transaction for tax calculation.
		*/
		$lines = array();
		foreach($existing_transaction->transaction_lines as $line) {
			$transaction_line = new input_transaction_line();
			$transaction_line->amount = $line->amount;
			$transaction_line->custom_id = $line->custom_id;
			$lines[] = $transaction_line;
		}

		$transaction = new input_transaction();
		$transaction->currency_code = $rcp_options['currency'];
		$transaction->buyer_ip = $existing_transaction->buyer_ip;
		$transaction->billing_country_code = $existing_transaction->tax_country_code;
		$transaction->force_country_code = $existing_transaction->tax_country_code;
		$transaction->transaction_lines = $lines;

		/**
		* Calculate the tax for the new transaction.
		*/
		$tax_calc = $taxamo->calculateTax(array('transaction' => $transaction));
		$tax_amount = floatval($tax_calc->transaction->tax_amount);

		/**
		* If no tax or tax was included in original total return.
		*/
		if($tax_amount <= 0 || (!empty($rcp_options['taxamo_tax_included']) && $rcp_options['taxamo_tax_included'])) {
			return;
		}

		/**
		* Add Invoice Line Item for VAT Taxes.
		*/
		Stripe_InvoiceItem::create( array(
				'customer'    => $invoice->customer,
				'invoice'	  => $invoice->id,
				'amount'      => $tax_amount * 100,
				'currency'    => strtolower( $rcp_options['currency'] ),
				'description' => __('VAT Taxes', 'rcp-taxamo'),
			)
		);

	}

	/**
	* Processed when the subscription is first created..
	* This only occurs during the first subscription payment.
	*/
	public function stripe_signup_tax_invoice_item( $customer, $subscription_data ) {

		global $rcp_options;
		$tax_amount = floatval($subscription_data['taxamo_tax_amount']);

		/**
		* Confirm the transaction in Taxamo.
		*/
		$this->confirm_transaction( $subscription_data );

		/**
		* If no tax or tax was included in original total return.
		*/
		if($tax_amount <= 0 || (!empty($rcp_options['taxamo_tax_included']) && $rcp_options['taxamo_tax_included'])) {
			return;
		}

		/**
		* Add Invoice Line Item for VAT Taxes.
		*/
		Stripe_InvoiceItem::create( array(
				'customer'    => $customer->id,
				'amount'      => $tax_amount * 100,
				'currency'    => strtolower( $subscription_data['currency'] ),
				'description' => __('VAT Taxes', 'rcp-taxamo'),
			)
		);
		
		/**
		* If no subscription fee then pay invoice immediately.
		*/
		if ( empty( $subscription_data['fee'] ) || $subscription_data['fee'] <= 0 ) {

			// Create the invoice containing taxes
			$invoice = Stripe_Invoice::create( array(
					'customer' => $customer->id,
				) );
			$invoice->pay();

		}

	}

	/**
	* Filters the Paypal Standard Payment arguments before the redirect to paypal checkout.
	*/
	public function paypal_args( $paypal_args, $subscription_data ) {

		global $rcp_options;
		$paypal_args['country'] = $subscription_data['country'];
		$tax_amount = floatval($subscription_data['taxamo_tax_amount']);
		$total_amount = floatval($subscription_data['taxamo_total_amount']);

		/**
		* Confirm the transaction in Taxamo.
		*/
		$this->confirm_transaction( $subscription_data );

		/**
		* If no tax or tax was included in original total return.
		*/
		if($tax_amount <= 0 || (!empty($rcp_options['taxamo_tax_included']) && $rcp_options['taxamo_tax_included'])) {
			return $paypal_args;
		}

		/**
		* Set recurring payment amounts.
		*/
		if( $subscription_data['auto_renew'] && ! empty( $subscription_data['length'] ) ) {

			/**
			* Modified Subscription Price with Vat Tax Added
			*/
			$paypal_args['a3'] = $total_amount;

			/**
			* Adjust first payment to include tax.
			*/
			if( ! empty( $subscription_data['fee'] ) ) {
				$paypal_args['a1'] = number_format( $subscription_data['fee'] + $total_amount, 2 );
			}

		}
		/**
		* Set single payment amount.
		*/
		else {
			$paypal_args['amount'] = $total_amount;
		}

		return $paypal_args;

	} 

	/**
	* Returns the taxamo API class. Instantiates the Taxamo API if not already.
	*/
	public function taxamo_api() {
		global $rcp_options;
		if(!isset($this->taxamo_api)) {
			if(!class_exists('Taxamo')) {
				require RCP_TAXAMO_PLUGIN_DIR . 'includes/libraries/taxamo-php/lib/Taxamo.php';				
			}
			$this->taxamo_api = new Taxamo(new APIClient($rcp_options['taxamo_private_token'], 'https://api.taxamo.com'));
		}
		return $this->taxamo_api;
	}

	/**
	* Confirms a transaction prior to payment.
	*/
	public function confirm_transaction( $subscription_data ) {
		global $rcp_options;

		$user_id         = $subscription_data['user_id'];
		$user_payments   = rcp_get_user_payments( $user_id );
		$transaction_key = get_user_meta( $user_id, 'rcp_taxamo_transaction_key', true );
		$transaction_unconfirmed = get_user_meta( $user_id, 'rcp_taxamo_transaction_key_unconfirmed', true );

		/**
		* Initiate Taxamo API.
		*/
		$taxamo = $this->taxamo_api();

		/**
		* Prepare confirmation data & confirm transaciton.
		*/
		if( $transaction_unconfirmed ) {

			$user_info = get_userdata($user_id);

			$transaction = new input_transaction;
			$transaction->buyer_email = $user_info->user_email;
			$transaction->buyer_name = $user_info->display_name;
			$transaction->custom_id = $subscription_data['key'];

			$confirm = $taxamo->confirmTransaction($transaction_key, array('transaction' => $transaction)); 
			delete_user_meta( $user_id, 'rcp_taxamo_transaction_key_unconfirmed' );
		}
	}

	/**
	* Track Individual Payments in Taxamo.
	*/
	public function track_payment( $payment_id, $args, $amount ) {

		global $rcp_options;
		$user_id         = $args['user_id'];
		$transaction_key = get_user_meta( $user_id, 'rcp_taxamo_transaction_key', true );

		/**
		* Return if no transaction key exists.
		*/
		if(!$transaction_key || $transaction_key == '') {
			return;
		}

		/**
		* Set Payment Gateway based on payment_type.
		*/
		if( $args['payment_type'] == 'web_accept' || $args['payment_type'] == 'subscr_payment' ) {
			$gateway = 'Paypal';
		}
		elseif( $args['payment_type'] == 'Credit Card') {
			$gateway = 'Stripe';
		}

		/**
		* Prepare payment data for Taxamo.
		*/
		$payment_data = array(
			'amount'              => floatval($amount),
			'payment_information' => 'Gateway: ' . $gateway . '; SubscriptionID: ' . $args['subscription_key'] . '; PaymentID: ' . $payment_id . ';',
		);

		/**
		* Initiate Taxamo API.
		*/
		$taxamo = $this->taxamo_api();

		/**
		* Add payment to Taxamo.
		*/
		$payment = $taxamo->createPayment($transaction_key, $payment_data);

	}

}
