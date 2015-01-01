<?php

class RCP_Taxamo_Payments {
	public function __construct() {
		add_filter( 'rcp_paypal_args', array( $this, 'paypal_args' ), 10, 2 );
		add_action( 'rcp_insert_payment', array( $this, 'track_payment' ), 10, 3 );
		add_action( 'rcp_stripe_signup_invoice', array( $this, 'stripe_signup_tax_invoice' ), 10, 3 );

		//if(is_user_logged_in() ) $this->track_payment( 8, array("subscription" => "Silver","date" => "2014-12-24 2:18:22","amount" => "12.20","user_id" => "1","payment_type" => "subscr_payment","subscription_key" => "198a1fa1aba0904cc632b321f3cdf14c","transaction_id" => "2J882465JA4891610"), "12.20");
	}

	public function stripe_signup_tax_invoice( $customer, $subscription_data ) {


		echo '<pre>'; var_dump($subscription_data); exit();

		$tax_amount = floatval($subscription_data['taxamo_total_amount']) - floatval($subscription_data['amount']);

		Stripe_InvoiceItem::create( array(
				'customer'    => $customer->id,
				'amount'      => $tax_amount * 100,
				'currency'    => strtolower( $subscription_data['currency'] ),
				'description' => __('VAT Taxes', 'rcp-taxamo'),
			)
		);

		// Create the invoice containing taxes / discounts / fees
		$invoice = Stripe_Invoice::create( array(
				'customer' => $customer->id, // the customer to apply the fee to
			) );
		$invoice->pay();
	}


	/**
	* Filters the Paypal Standard Payment arguments before the redirect to paypal checkout.
	*/
	public function paypal_args( $paypal_args, $subscription_data ) {
		global $rcp_options;
		$paypal_args['country'] = $subscription_data['country'];
		if(!class_exists('Taxamo'))
			require RCP_TAXAMO_PLUGIN_DIR . 'includes/libraries/taxamo-php/lib/Taxamo.php';

		$user_id         = $subscription_data['user_id'];
		$user_payments   = rcp_get_user_payments( $user_id );
		$transaction_key = get_user_meta( $user_id, 'rcp_taxamo_transaction_key', true );

		/**
		* Initiate Taxamo API.
		*/
		$taxamo = new Taxamo(new APIClient($rcp_options['taxamo_private_token'], 'https://api.taxamo.com'));

		/**
		* Prepare confirmation data & confirm transaciton.
		*/
		if( count($user_payments) <= 1 ) {

			$user_info = get_userdata($user_id);

			$transaction = new input_transaction;
			$transaction->buyer_email = $user_info->user_email;
			$transaction->buyer_name = $user_info->display_name;
			$transaction->custom_id = $subscription_data['key'];

			$confirm = $taxamo->confirmTransaction($transaction_key, array('transaction' => $transaction)); 
		}

		if(!empty($subscription_data['taxamo_total_amount'])) {

			// Set recurring payment amounts.
			if( $subscription_data['auto_renew'] && ! empty( $subscription_data['length'] ) ) {

				// Regular Subscription Price with Vat Tax
				$paypal_args['a3'] = $subscription_data['taxamo_total_amount'];

				// Adjust first payment to include tax.
				if( ! empty( $subscription_data['fee'] ) ) {
					$paypal_args['a1'] = number_format( $subscription_data['fee'] + $subscription_data['taxamo_total_amount'], 2 );
				}

			}
			// Set single payment amount.
			else {
				$paypal_args['amount'] = $subscription_data['taxamo_total_amount'];
			}
		}
		return $paypal_args;
	} 





	public function track_payment( $payment_id, $args, $amount ) {
		global $rcp_options;

		if(!class_exists('Taxamo'))
			require RCP_TAXAMO_PLUGIN_DIR . '/includes/libraries/taxamo-php/lib/Taxamo.php';

		$user_id         = $args['user_id'];
		$transaction_key = get_user_meta( $user_id, 'rcp_taxamo_transaction_key', true );

		/**
		* Initiate Taxamo API.
		*/
		$taxamo = new Taxamo(new APIClient($rcp_options['taxamo_private_token'], 'https://api.taxamo.com'));

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
		* Add payment to Taxamo.
		*/
		$payment = $taxamo->createPayment($transaction_key, $payment_data);
	}
}
