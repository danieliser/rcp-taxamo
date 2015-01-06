<?php

/**
* Sets up public functionality for the RCP Taxamo extension, including form fields and script loading.
*/
class RCP_Taxamo_Public {

	public function __construct() {

		add_action( 'wp_enqueue_scripts', array($this, 'scripts') );

		// Add VAT fields to User Forms.
		add_action( 'rcp_before_subscription_form_fields', array( $this, 'vat_fields' ) );
		add_action( 'rcp_profile_editor_after', array( $this, 'vat_fields' ) );

		// Process User Forms, Update User Meta for Country & VAT #.
		add_filter( 'rcp_subscription_data', array( $this, 'subscription_data' ) );
		add_action( 'rcp_user_profile_updated', array( $this, 'user_profile_update' ), 10, 2 );
		
	}

	public function scripts() {

		global $rcp_options;

		wp_register_script( 'taxamo', 'https://api.taxamo.com/js/v1/taxamo.all.js', array(), '1' );
		wp_register_script( 'rcp-taxamo', RCP_TAXAMO_PLUGIN_URL . 'assets/scripts/rcp-taxamo.min.js', array('jquery', 'taxamo'), '1', true );

		if( is_page($rcp_options['registration_page']) && !empty($rcp_options['taxamo_public_token']) ) {
			wp_enqueue_script( 'rcp-taxamo' );
			wp_localize_script('rcp-taxamo', 'rcp_taxamo_vars', array(
					'taxamo_public_token' => $rcp_options['taxamo_public_token'],
					'tax_included' => !empty($rcp_options['taxamo_tax_included']) ? true : false,
					'currency' => $rcp_options['currency'],
					'priceTemplate' => !empty($rcp_options['taxamo_price_template']) ? $rcp_options['taxamo_price_template'] : __('${totalAmount} (${taxRate}% tax)', 'rcp-taxamo'),
					'noTaxTitle' => !empty($rcp_options['taxamo_no_tax_title']) ? $rcp_options['taxamo_no_tax_title'] : __('No tax applied in this location', 'rcp-taxamo'),
					'taxTitle' => !empty($rcp_options['taxamo_tax_title']) ? $rcp_options['taxamo_tax_title'] : __('Original amount: ${amount}, tax rate: ${taxRate}%', 'rcp-taxamo'),
					'priceClass' => !empty($rcp_options['taxamo_price_class']) ? '.' . $rcp_options['taxamo_price_class'] : '.rcp_price',
				)
			);
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
			<input type="hidden" id="rcp_taxamo_amount" name="rcp_taxamo_amount" value=""/>
			<input type="hidden" id="rcp_taxamo_tax_amount" name="rcp_taxamo_tax_amount" value=""/>
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

	public function user_profile_update( $user_id, $userdata ) {

		$country = ! empty( $_POST['rcp_country'] ) ? sanitize_text_field( $_POST['rcp_country'] ) : '';
		$vat_number   = ! empty( $_POST['rcp_vat_number'] )   ? sanitize_text_field( $_POST['rcp_vat_number'] ) : '';

		update_user_meta( $user_id, 'rcp_country', $country );
		update_user_meta( $user_id, 'rcp_vat_number', $vat_number );

	}

	public function subscription_data( $subscription_data ) {

		$subscription_data['country'] = sanitize_text_field( $_POST['rcp_country'] );
		$subscription_data['vat_number'] = sanitize_text_field( $_POST['rcp_vat_number'] );
		$subscription_data['taxamo_transaction_key'] = sanitize_text_field( $_POST['rcp_taxamo_transaction_key'] );
		$subscription_data['taxamo_amount'] = sanitize_text_field( $_POST['rcp_taxamo_amount'] );
		$subscription_data['taxamo_tax_amount'] = sanitize_text_field( $_POST['rcp_taxamo_tax_amount'] );
		$subscription_data['taxamo_total_amount'] = sanitize_text_field( $_POST['rcp_taxamo_total_amount'] );

		update_user_meta( $subscription_data['user_id'], 'rcp_vat_number', $subscription_data['vat_number'] );
		update_user_meta( $subscription_data['user_id'], 'rcp_country', $subscription_data['country'] );

		$current_transaction_key = get_user_meta( $subscription_data['user_id'], 'rcp_taxamo_transaction_key', true );

		if(!$current_transaction_key || $subscription_data['taxamo_transaction_key'] != $current_transaction_key) {
			update_user_meta( $subscription_data['user_id'], 'rcp_taxamo_transaction_key', $subscription_data['taxamo_transaction_key'] );
			add_user_meta( $subscription_data['user_id'], 'rcp_taxamo_transaction_key_new', true );

		}

		return $subscription_data;

	}

	public function get_countries() {

		$countries = array(
			''	 => __( 'Select Your Billing Country', 'rcp-taxamo' ),
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
		return apply_filters( 'rcp_taxamo_country_options', $countries );

	} 
}
