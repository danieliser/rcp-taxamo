<?php

/**
* Sets up admin functionality for the RCP Taxamo extension, including settings.
*/
class RCP_Taxamo_Admin {
	public function __construct() {
		add_action( 'rcp_payments_settings', array($this, 'settings_fields') );
		add_action( 'rcp_view_member_after', array( $this, 'member_details' ) ); 
	}

	/**
	* Render the settings fields for taxamo configuration.
	*/
	public function settings_fields( $rcp_options ) { ?>
		<table class="form-table">
			<tr valign="top">
				<th colspan=2><h3><?php _e( 'Taxamo Settings', 'rcp-taxamo' ); ?></h3></th>
			</tr>
			<tr valign="top">
				<th>
					<label for="rcp_settings[taxamo_public_token]"><?php _e( 'Public Token', 'rcp-taxamo' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="rcp_settings[taxamo_public_token]" style="width: 300px;" name="rcp_settings[taxamo_public_token]" value="<?php echo !empty( $rcp_options['taxamo_public_token'] ) ? $rcp_options['taxamo_public_token'] : ''; ?>"/>
					<p class="description"><?php _e( 'Enter your Taxamo public token.', 'rcp-taxamo' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th>
					<label for="rcp_settings[taxamo_private_token]"><?php _e( 'Private Token', 'rcp-taxamo' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="rcp_settings[taxamo_private_token]" style="width: 300px;" name="rcp_settings[taxamo_private_token]" value="<?php echo !empty( $rcp_options['taxamo_private_token'] ) ? $rcp_options['taxamo_private_token'] : ''; ?>"/>
					<p class="description"><?php _e( 'Enter your Taxamo private token.', 'rcp-taxamo' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th>
					<label for="rcp_settings[taxamo_tax_included]"><?php _e( 'Tax Included for New Purchases?', 'rcp-taxamo' ); ?></label>
				</th>
				<td>
					<label>
						<input type="checkbox" id="rcp_settings[taxamo_tax_included]" name="rcp_settings[taxamo_tax_included]" <?php echo !empty( $rcp_options['taxamo_tax_included'] ) ? 'checked="checked"' : ''; ?>/>
						<?php _e( 'If checked taxes will be subtracted from the total rather than added to it.', 'rcp-taxamo' ); ?>
					</label>
					<p class="description"><?php _e( 'EX. $10 Subscription: With Tax Included ($10), Without ($10 + $2 Tax).', 'rcp-taxamo' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th>
					<label for="rcp_settings[taxamo_price_template]"><?php _e( 'Price Template', 'rcp-taxamo' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="rcp_settings[taxamo_price_template]" style="width: 300px;" name="rcp_settings[taxamo_price_template]" value="<?php echo !empty( $rcp_options['taxamo_price_template'] ) ? $rcp_options['taxamo_price_template'] : ''; ?>"/>
					<p class="description"><?php _e( 'Description Coming Soon.', 'rcp-taxamo' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th>
					<label for="rcp_settings[taxamo_no_tax_title]"><?php _e( 'No Tax Applied Title', 'rcp-taxamo' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="rcp_settings[taxamo_no_tax_title]" style="width: 300px;" name="rcp_settings[taxamo_no_tax_title]" value="<?php echo !empty( $rcp_options['taxamo_no_tax_title'] ) ? $rcp_options['taxamo_no_tax_title'] : ''; ?>"/>
					<p class="description"><?php _e( 'Description Coming Soon.', 'rcp-taxamo' ); ?></p>
				</td>
			</tr>
			<tr valign="top">
				<th>
					<label for="rcp_settings[taxamo_tax_title]"><?php _e( 'Tax Applied Title', 'rcp-taxamo' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="rcp_settings[taxamo_tax_title]" style="width: 300px;" name="rcp_settings[taxamo_tax_title]" value="<?php echo !empty( $rcp_options['taxamo_tax_title'] ) ? $rcp_options['taxamo_tax_title'] : ''; ?>"/>
					<p class="description"><?php _e( 'Description Coming Soon.', 'rcp-taxamo' ); ?></p>
				</td>
			</tr>
		</table><?php
	}

	/**
	* Render the country field for member details.
	*/
	public function member_details( $user_id ) {
		$country = get_user_meta( $user_id, 'rcp_country', true );
		$countries = $this->get_contries();
		if( empty( $country ) ) {
			return;
		}?>
		<tr class="form-field">
			<th scope="row" valign="top">
				<?php _e( 'Country', 'rcp-taxamo' ); ?>
			</th>
			<td>
				<?php echo $countries[$country]; ?>
			</td>
		</tr><?php	
	}
}