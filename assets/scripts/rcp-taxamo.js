/**
* RCP Taxamo - v1.0.0
*/
(function () {
    "use strict";
    var taxamo_public_token = rcp_taxamo_vars.taxamo_public_token,
        tax_included = rcp_taxamo_vars.tax_included,
        default_currency_code = rcp_taxamo_vars.currency,
        country = jQuery('#rcp_country'),
        vat_number = jQuery('#rcp_vat_number'),
        card_number = jQuery('input.card-number'),
        priceClass = rcp_taxamo_vars.priceClass,
        transaction,
        taxamo_transaction_token = null,
        update_rcp_taxamo_transaction = function () {
            var option = jQuery('input[type="radio"][name="rcp_level"]').filter(':checked').parents('.rcp_subscription_level');

            transaction = Taxamo.transaction()
                .currencyCode(default_currency_code);

            if (country.length && country.val() !== '') {
                transaction
                    .buyerCountryCode(country.val())
                    .forceCountryCode(country.val());
            }
            if (vat_number.length && vat_number.val() !== '') {
                transaction.buyerTaxNumber(vat_number.val());
            }
            if (card_number.length && card_number.val() !== '') {
                transaction.buyerCardNumberPrefix(card_number.val().substring(0, 9));
            }

            if (tax_included) {
                transaction
                    .transactionLine('line1') //first line
                        .totalAmount(parseFloat(jQuery(priceClass, option).attr('rel')))
                        .description(jQuery('.rcp_subscription_level_name', option).text())
                        .productType('default')
                        .done(); //go back to transaction context
            } else {
                transaction
                    .transactionLine('line1') //first line
                        .amount(parseFloat(jQuery(priceClass, option).attr('taxamo-amount')))
                        .description(jQuery('.rcp_subscription_level_name', option).text())
                        .productType('default')
                        .done(); //go back to transaction context
            }



        };

    /**
    * Pricing Template Defaults
    */
    Taxamo.options.scanPrices.priceTemplate = rcp_taxamo_vars.priceTemplate;
    Taxamo.options.scanPrices.noTaxTitle    = rcp_taxamo_vars.noTaxTitle;
    Taxamo.options.scanPrices.taxTitle      = rcp_taxamo_vars.taxTitle;

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
    if (!tax_included) {
        Taxamo.scanPrices(priceClass);
    }
    Taxamo.detectCountry();

    jQuery(document)
        .ready(function () {
            Taxamo.setBillingCountry(jQuery('#rcp_country').val());
            Taxamo.setTaxNumber(jQuery('#rcp_vat_number').val());
        })

    /**
    * Register Event Listeners
    */
        /**
        * Update the billing country when user chooses a country, this will update prices if needed.
        */
        .on('change', '#rcp_country', function () {
            Taxamo.setBillingCountry(jQuery(this).val());
        })
        /**
        * Update the billing card number or vat number when user leaves the input.
        */
        .on('focusout', '.card-number, #rcp_vat_number', function () {
            var $this = jQuery(this);
            if ($this.hasClass('card-number')) {
                Taxamo.setCreditCardPrefix(jQuery(this).val().substring(0, 9));
            } else {
                Taxamo.setTaxNumber(jQuery(this).val());
            }
        })
        .on('click', 'input[type="radio"][name="rcp_level"]', function () {
            update_rcp_taxamo_transaction();
        })
        /**
        * Before submitting the form create & store a transaction with taxamo,
        * then saving the taxamo transaction key in a hidden form field with the total amount.
        */
        .on('submit', '#rcp_registration_form', function (event) {
            var $this = jQuery(this);

            if (taxamo_transaction_token) {
                return true;
            }

            event.preventDefault();
            event.stopPropagation();

            update_rcp_taxamo_transaction();

            Taxamo.storeTransaction(
                transaction,
                function (data) { //success handler, you should place more complex logic here
                    jQuery('#rcp_taxamo_transaction_key').val(data.transaction.key);
                    jQuery('#rcp_taxamo_tax_supported').val(data.transaction.tax_supported);
                    jQuery('#rcp_taxamo_amount').val(data.transaction.amount);
                    jQuery('#rcp_taxamo_tax_rate').val(data.transaction.transaction_lines[0].tax_rate);
                    jQuery('#rcp_taxamo_tax_amount').val(data.transaction.tax_amount);
                    jQuery('#rcp_taxamo_total_amount').val(data.transaction.total_amount);
                    taxamo_transaction_token = data.transaction.key;
                    $this.trigger('submit');
                },
                function (data) { //error handler, you should place more complex logic here
                    console.log(data);
                }
            );
        });
}());
