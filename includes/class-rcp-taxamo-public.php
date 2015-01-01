<?php

class RCP_Taxamo_Public {
	public function __construct() {
		add_action( 'wp_enqueue_scripts', array($this, 'scripts') );
		add_action( 'wp_footer', array($this, 'initialize_taxamo_js'), 30 );

		add_action( 'rcp_before_subscription_form_fields', array( $this, 'vat_fields' ) );
		add_action( 'rcp_profile_editor_after', array( $this, 'vat_fields' ) );



		// Process User Forms, Check For Errors, Update User Meta for Country & VAT #.
		add_action( 'rcp_form_errors', array( $this, 'error_checks' ) );
		add_filter( 'rcp_subscription_data', array( $this, 'subscription_data' ) );
		add_action( 'rcp_user_profile_updated', array( $this, 'user_profile_update' ), 10, 2 );
	}

	public function user_profile_update( $user_id, $userdata ) {

		$country = ! empty( $_POST['rcp_country'] ) ? sanitize_text_field( $_POST['rcp_country'] ) : '';
		$vat_number   = ! empty( $_POST['rcp_vat_number'] )   ? sanitize_text_field( $_POST['rcp_vat_number'] ) : '';

		if ( empty( $country ) ) {
			rcp_errors()->add( 'empty_country', __( 'Please select a valid billing country', 'rcp-taxamo' ) );
		}

		update_user_meta( $user_id, 'rcp_country', $country );
		update_user_meta( $user_id, 'rcp_vat_number', $vat_number );

	}


	public function scripts() {
		global $rcp_options;

		wp_register_script( 'taxamo', 'https://api.taxamo.com/js/v1/taxamo.all.js', array(), '1' );
		if(is_page($rcp_options['registration_page'])) {
			wp_enqueue_script( 'taxamo' );
		}
	}

	public function initialize_taxamo_js() {
		global $rcp_options;
		if(is_page($rcp_options['registration_page'])) {
			$priceTemplate = !empty($rcp_options['taxamo_price_template']) ? $rcp_options['taxamo_price_template'] : __('${totalAmount} (${taxRate}% tax)', 'rcp-taxamo');
			$noTaxTitle = !empty($rcp_options['taxamo_no_tax_title']) ? $rcp_options['taxamo_no_tax_title'] : __('No tax applied in this location', 'rcp-taxamo');
			$taxTitle = !empty($rcp_options['taxamo_tax_title']) ? $rcp_options['taxamo_tax_title'] : __('Original amount: ${amount}, tax rate: ${taxRate}%', 'rcp-taxamo');
			$priceClass = !empty($rcp_options['taxamo_price_class']) ? $rcp_options['taxamo_price_class'] : '.rcp_price';
		 ?>
			<script type="text/javascript">
				(function () {
					var taxamo_public_token = '<?php echo $rcp_options['taxamo_public_token'];?>',
						default_currency_code = '<?php echo $rcp_options['currency'];?>',
						country = jQuery('#rcp_country'),
						vat_number = jQuery('#rcp_vat_number'),
						card_number = jQuery('input.card-number'),
						priceClass = '<?php echo $priceClass;?>',
						transaction,
						taxamo_check = false,
						current_member_option;

					/**
					* Pricing Template Defaults
					*/
					Taxamo.options.scanPrices.priceTemplate = "<?php echo $priceTemplate;?>";
					Taxamo.options.scanPrices.noTaxTitle    = "<?php echo $noTaxTitle;?>"
					Taxamo.options.scanPrices.taxTitle      = "<?php echo $taxTitle;?>";
					Taxamo.subscribe('taxamo.country.selected', function (data) {
						jQuery('.rcp_subscription_fieldset').css({opacity: 0.25});
						setTimeout(function () {
							jQuery('.rcp_subscription_fieldset').animate({opacity: 1}, 500);
						}, 2000);
					});

					/**
					* Initialize Taxamo with Public Token, Set default store currency, scan prices & detect country.
					*/
					Taxamo.initialize(taxamo_public_token);
					Taxamo.setCurrencyCode(default_currency_code);
					Taxamo.scanPrices(priceClass);
					Taxamo.detectCountry();

					jQuery(document)
						.ready(function () {
							Taxamo.setBillingCountry(jQuery('#rcp_country').val());
							Taxamo.setTaxNumber(jQuery('#rcp_vat_number').val());
						});
					/**
					* Register Event Listeners
					*/
					jQuery(document)
						/**
						* Update the billing country when user chooses a country.
						*/
						.on('change', '#rcp_country', function () {
							Taxamo.setBillingCountry(jQuery(this).val());
						})
						/**
						* Update the billing card number or vat number when user leaves the input.
						*/
						.on('focusout', '.card-number, #rcp_vat_number', function () {
							var $this = jQuery(this);
							if($this.hasClass('card-number')) {
								Taxamo.setCreditCardPrefix(jQuery(this).val().substring(0, 9))
							}
							else {
								Taxamo.setTaxNumber(jQuery(this).val());		
							}
						})
						/**
						* Before submitting the form create & store a transaction with taxamo,
						* then saving the taxamo transaction key in a hidden form field with the total amount.
						*/
						.on('submit', '#rcp_registration_form', function (event) {
							var $this = jQuery(this),
								option = jQuery('input[type="radio"][name="rcp_level"]').filter(':checked').parents('.rcp_subscription_level'),
								transaction = Taxamo.transaction()
									.currencyCode(default_currency_code);
									/*
									.customId('order1414556')
									.description('order #1414556')
									*/

							if(taxamo_check) {
								return;
							}
							event.preventDefault();

							if (country.length && country.val() !== '') {
								transaction
									.buyerCountryCode(country.val())
									.forceCountryCode(country.val());
							}
							if (vat_number.length && vat_number.val() !== '') {
								transaction.buyerTaxNumber(vat_number.val());
							}
							if (card_number.length && card_number.val() !== '') {
								transaction.buyerCardNumberPrefix(card_number.val().substring(0, 9))
							}

							transaction
								.transactionLine('line1') //first line
									.amount(parseInt(jQuery(priceClass, option).attr('taxamo-amount')))
									//.totalAmount( parseInt( jQuery('.rcp_price', option).attr('taxamo-amount') ) )
									.description(jQuery('.rcp_subscription_level_name', option).text())
									.productType('default')
									.done(); //go back to transaction context

							Taxamo.storeTransaction(
								transaction,
								function (data) { //success handler, you should place more complex logic here
									jQuery('#rcp_taxamo_transaction_key').val(data.transaction.key);
									jQuery('#rcp_taxamo_tax_supported').val(data.transaction.tax_supported);
									jQuery('#rcp_taxamo_total_amount').val(data.transaction.total_amount);
									//taxamo_check = true;
									//$this.trigger('submit');
								},
								function (data) { //error handler, you should place more complex logic here
									console.log(data);
								}
							);
						});
				}());
			</script><?php
		}
	}

	public function vat_fields( $user_id = NULL ) {
		if( !$user_id ) {
			$user_id = get_current_user_id();
		}

		$user_country = get_user_meta( $user_id, 'rcp_country', true );
		$user_vat_number = get_user_meta( $user_id, 'rcp_vat_number', true );?>

		<fieldset class="rcp_vat_fieldset">
			<input type="hidden" id="rcp_taxamo_tax_supported" name="rcp_taxamo_tax_supported" value=""/>
			<input type="hidden" id="rcp_taxamo_transaction_key" name="rcp_taxamo_transaction_key" value=""/>
			<input type="hidden" id="rcp_taxamo_total_amount" name="rcp_taxamo_total_amount" value=""/>
			<p id="rcp_country_wrap">
				<label for="rcp_country"><?php _e( 'Country', 'rcp-taxamo' ); ?></label>
				<select name="rcp_country" id="rcp_country" required>
					<?php foreach( $this->get_countries() as $key => $country ) : ?>
					<option value="<?php echo $key; ?>" <?php echo $key == $user_country ? 'selected="selected"' : '';?>><?php echo $country; ?></option>
					<?php endforeach; ?>
				</select>
			</p>
			<p id="rcp_vat_number_wrap">
				<label for="rcp_vat_number"><?php _e( 'Vat Number', 'rcp-taxamo' ); ?></label>
				<input name="rcp_vat_number" id="rcp_vat_number" type="text" value="<?php echo esc_attr( $user_vat_number );?>" />
			</p>
		</fieldset><?php
	}

	public function error_checks( $data ) {
		if( empty( $data['rcp_country'] ) ) {
			rcp_errors()->add( 'empty_country', __( 'Please select your country', 'rcp-taxamo' ), 'register' );
		}
	} 

	public function subscription_data( $subscription_data ) {
		$subscription_data['country'] = sanitize_text_field( $_POST['rcp_country'] );
		$subscription_data['vat_number'] = sanitize_text_field( $_POST['rcp_vat_number'] );
		$subscription_data['taxamo_transaction_key'] = sanitize_text_field( $_POST['rcp_taxamo_transaction_key'] );
		$subscription_data['taxamo_total_amount'] = sanitize_text_field( $_POST['rcp_taxamo_total_amount'] );

		update_user_meta( $subscription_data['user_id'], 'rcp_vat_number', $subscription_data['vat_number'] );
		update_user_meta( $subscription_data['user_id'], 'rcp_country', $subscription_data['country'] );
		update_user_meta( $subscription_data['user_id'], 'rcp_taxamo_transaction_key', $subscription_data['taxamo_transaction_key'] );

		return $subscription_data;
	}

	public function get_countries() {
		$countries = array(
			''	 => __('Select Your Billing Country', 'rcp-taxamo'),
			'US' => 'United States',
			'CA' => 'Canada',
			'GB' => 'United Kingdom',
			'AF' => 'Afghanistan',
			'AL' => 'Albania',
			'DZ' => 'Algeria',
			'AS' => 'American Samoa',
			'AD' => 'Andorra',
			'AO' => 'Angola',
			'AI' => 'Anguilla',
			'AQ' => 'Antarctica',
			'AG' => 'Antigua and Barbuda',
			'AR' => 'Argentina',
			'AM' => 'Armenia',
			'AW' => 'Aruba',
			'AU' => 'Australia',
			'AT' => 'Austria',
			'AZ' => 'Azerbaijan',
			'BS' => 'Bahamas',
			'BH' => 'Bahrain',
			'BD' => 'Bangladesh',
			'BB' => 'Barbados',
			'BY' => 'Belarus',
			'BE' => 'Belgium',
			'BZ' => 'Belize',
			'BJ' => 'Benin',
			'BM' => 'Bermuda',
			'BT' => 'Bhutan',
			'BO' => 'Bolivia',
			'BA' => 'Bosnia and Herzegovina',
			'BW' => 'Botswana',
			'BV' => 'Bouvet Island',
			'BR' => 'Brazil',
			'IO' => 'British Indian Ocean Territory',
			'BN' => 'Brunei Darrussalam',
			'BG' => 'Bulgaria',
			'BF' => 'Burkina Faso',
			'BI' => 'Burundi',
			'KH' => 'Cambodia',
			'CM' => 'Cameroon',
			'CV' => 'Cape Verde',
			'KY' => 'Cayman Islands',
			'CF' => 'Central African Republic',
			'TD' => 'Chad',
			'CL' => 'Chile',
			'CN' => 'China',
			'CX' => 'Christmas Island',
			'CC' => 'Cocos Islands',
			'CO' => 'Colombia',
			'KM' => 'Comoros',
			'CD' => 'Congo, Democratic People\'s Republic',
			'CG' => 'Congo, Republic of',
			'CK' => 'Cook Islands',
			'CR' => 'Costa Rica',
			'CI' => 'Cote d\'Ivoire',
			'HR' => 'Croatia/Hrvatska',
			'CU' => 'Cuba',
			'CY' => 'Cyprus Island',
			'CZ' => 'Czech Republic',
			'DK' => 'Denmark',
			'DJ' => 'Djibouti',
			'DM' => 'Dominica',
			'DO' => 'Dominican Republic',
			'TP' => 'East Timor',
			'EC' => 'Ecuador',
			'EG' => 'Egypt',
			'GQ' => 'Equatorial Guinea',
			'SV' => 'El Salvador',
			'ER' => 'Eritrea',
			'EE' => 'Estonia',
			'ET' => 'Ethiopia',
			'FK' => 'Falkland Islands',
			'FO' => 'Faroe Islands',
			'FJ' => 'Fiji',
			'FI' => 'Finland',
			'FR' => 'France',
			'GF' => 'French Guiana',
			'PF' => 'French Polynesia',
			'TF' => 'French Southern Territories',
			'GA' => 'Gabon',
			'GM' => 'Gambia',
			'GE' => 'Georgia',
			'DE' => 'Germany',
			'GR' => 'Greece',
			'GH' => 'Ghana',
			'GI' => 'Gibraltar',
			'GL' => 'Greenland',
			'GD' => 'Grenada',
			'GP' => 'Guadeloupe',
			'GU' => 'Guam',
			'GT' => 'Guatemala',
			'GG' => 'Guernsey',
			'GN' => 'Guinea',
			'GW' => 'Guinea-Bissau',
			'GY' => 'Guyana',
			'HT' => 'Haiti',
			'HM' => 'Heard and McDonald Islands',
			'VA' => 'Holy See (City Vatican State)',
			'HN' => 'Honduras',
			'HK' => 'Hong Kong',
			'HU' => 'Hungary',
			'IS' => 'Iceland',
			'IN' => 'India',
			'ID' => 'Indonesia',
			'IR' => 'Iran',
			'IQ' => 'Iraq',
			'IE' => 'Ireland',
			'IM' => 'Isle of Man',
			'IL' => 'Israel',
			'IT' => 'Italy',
			'JM' => 'Jamaica',
			'JP' => 'Japan',
			'JE' => 'Jersey',
			'JO' => 'Jordan',
			'KZ' => 'Kazakhstan',
			'KE' => 'Kenya',
			'KI' => 'Kiribati',
			'KW' => 'Kuwait',
			'KG' => 'Kyrgyzstan',
			'LA' => 'Lao People\'s Democratic Republic',
			'LV' => 'Latvia',
			'LB' => 'Lebanon',
			'LS' => 'Lesotho',
			'LR' => 'Liberia',
			'LY' => 'Libyan Arab Jamahiriya',
			'LI' => 'Liechtenstein',
			'LT' => 'Lithuania',
			'LU' => 'Luxembourgh',
			'MO' => 'Macau',
			'MK' => 'Macedonia',
			'MG' => 'Madagascar',
			'MW' => 'Malawi',
			'MY' => 'Malaysia',
			'Mv' => 'Maldives',
			'ML' => 'Mali',
			'MT' => 'Malta',
			'MH' => 'Marshall Islands',
			'MQ' => 'Martinique',
			'MR' => 'Mauritania',
			'MU' => 'Mauritius',
			'YT' => 'Mayotte',
			'MX' => 'Mexico',
			'FM' => 'Micronesia',
			'MD' => 'Moldova, Republic of',
			'MC' => 'Monaco',
			'MN' => 'Mongolia',
			'ME' => 'Montenegro',
			'MS' => 'Montserrat',
			'MA' => 'Morocco',
			'MZ' => 'Mozambique',
			'MM' => 'Myanmar',
			'NA' => 'Namibia',
			'NR' => 'Nauru',
			'NP' => 'Nepal',
			'NL' => 'Netherlands',
			'AN' => 'Netherlands Antilles',
			'NC' => 'New Caledonia',
			'NZ' => 'New Zealand',
			'NI' => 'Nicaragua',
			'NE' => 'Niger',
			'NG' => 'Nigeria',
			'NU' => 'Niue',
			'NF' => 'Norfolk Island',
			'KR' => 'North Korea',
			'MP' => 'Northern Mariana Islands',
			'NO' => 'Norway',
			'OM' => 'Oman',
			'PK' => 'Pakistan',
			'PW' => 'Palau',
			'PS' => 'Palestinian Territories',
			'PA' => 'Panama',
			'PG' => 'Papua New Guinea',
			'PY' => 'Paraguay',
			'PE' => 'Peru',
			'PH' => 'Phillipines',
			'PN' => 'Pitcairn Island',
			'PL' => 'Poland',
			'PT' => 'Portugal',
			'PR' => 'Puerto Rico',
			'QA' => 'Qatar',
			'RE' => 'Reunion Island',
			'RO' => 'Romania',
			'RU' => 'Russian Federation',
			'RW' => 'Rwanda',
			'SH' => 'Saint Helena',
			'KN' => 'Saint Kitts and Nevis',
			'LC' => 'Saint Lucia',
			'PM' => 'Saint Pierre and Miquelon',
			'VC' => 'Saint Vincent and the Grenadines',
			'SM' => 'San Marino',
			'ST' => 'Sao Tome and Principe',
			'SA' => 'Saudi Arabia',
			'SN' => 'Senegal',
			'RS' => 'Serbia',
			'SC' => 'Seychelles',
			'SL' => 'Sierra Leone',
			'SG' => 'Singapore',
			'SK' => 'Slovak Republic',
			'SI' => 'Slovenia',
			'SB' => 'Solomon Islands',
			'SO' => 'Somalia',
			'ZA' => 'South Africa',
			'GS' => 'South Georgia',
			'KP' => 'South Korea',
			'ES' => 'Spain',
			'LK' => 'Sri Lanka',
			'SD' => 'Sudan',
			'SR' => 'Suriname',
			'SJ' => 'Svalbard and Jan Mayen Islands',
			'SZ' => 'Swaziland',
			'SE' => 'Sweden',
			'CH' => 'Switzerland',
			'SY' => 'Syrian Arab Republic',
			'TW' => 'Taiwan',
			'TJ' => 'Tajikistan',
			'TZ' => 'Tanzania',
			'TG' => 'Togo',
			'TK' => 'Tokelau',
			'TO' => 'Tonga',
			'TH' => 'Thailand',
			'TT' => 'Trinidad and Tobago',
			'TN' => 'Tunisia',
			'TR' => 'Turkey',
			'TM' => 'Turkmenistan',
			'TC' => 'Turks and Caicos Islands',
			'TV' => 'Tuvalu',
			'UG' => 'Uganda',
			'UA' => 'Ukraine',
			'AE' => 'United Arab Emirates',
			'UY' => 'Uruguay',
			'UM' => 'US Minor Outlying Islands',
			'UZ' => 'Uzbekistan',
			'VU' => 'Vanuatu',
			'VE' => 'Venezuela',
			'VN' => 'Vietnam',
			'VG' => 'Virgin Islands (British)',
			'VI' => 'Virgin Islands (USA)',
			'WF' => 'Wallis and Futuna Islands',
			'EH' => 'Western Sahara',
			'WS' => 'Western Samoa',
			'YE' => 'Yemen',
			'YU' => 'Yugoslavia',
			'ZM' => 'Zambia',
			'ZW' => 'Zimbabwe'
		);
		return $countries;
	} 
}
