jQuery( function ( $ ) {
    if ( typeof LedyerPaymentsParams === "undefined" ) {
        return false
    }

    const LedyerPayments = {
        params: LedyerPaymentsParams,
        gatewayId: LedyerPaymentsParams.gatewayId,
        sessionId: LedyerPaymentsParams.sessionId,

        init: () => {
            let field = $( "#billing_company_number_field" ).detach()
            const moveCompanyNumberField = () => {
                if ( LedyerPayments.params.companyNumberPlacement === "billing_form" ) {
                    if ( LedyerPayments.isActiveGateway() ) {
                        $( "#billing_company_number_field" ).detach()
                        field.insertAfter( "#billing_company_field" )
                    } else {
                        field = $( "#billing_company_number_field" ).detach()
                    }
                }
            }

            $( "body" ).on( "click", "input#place_order, button#place_order", ( e ) => {
                if ( ! LedyerPayments.isActiveGateway() ) {
                    return
                }

                const organizationNumber = $( "#billing_company_number" ).val().trim()
                if ( organizationNumber.length === 0 ) {
                    LedyerPayments.printNotice( LedyerPayments.params.i18n.companyNumberMissing )
                    return false
                }

                LedyerPayments.submitOrder( e )
            } )

            $( document ).ready( () => {
                // If "billing_form", remove the field from the payment_form and insert it after the company name field. Otherwise, if it is "payment_form", leave as-is.
                if ( LedyerPayments.params.companyNumberPlacement === "billing_form" ) {
                    if ( LedyerPayments.isActiveGateway() ) {
                        $( "#billing_company_number_field" ).detach().insertAfter( "#billing_company_field" )
                    }

                    // Required whenever the customer changes payment method.
                    $( "body" ).on( "change", 'input[name="payment_method"]', moveCompanyNumberField )
                    // Required when the checkout is initially loaded, and Ledyer is the chosen gateway.
                    $( "body" ).on( "updated_checkout", moveCompanyNumberField )
                }
            } )
        },

        /**
         * Handles the process of proceeding with Ledyer payment for an order.
         *
         * @param {string} orderId - The key of the order.
         * @param {Object} customerData - The customer data.
         * @returns {void}
         */
        handleProceedWithLedyer: async ( orderId, customerData ) => {
            LedyerPayments.blockUI()
            try {
                const authArgs = { customer: { ...customerData }, sessionId: LedyerPayments.sessionId }
                const authResponse = await window.ledyer.payments.api.authorize( authArgs )

                    if ( authResponse.state === "authorized" ) {
                        const authToken = authResponse.authorizationToken
                        const { state } = authResponse
                        const { createOrderUrl, createOrderNonce } = LedyerPayments.params

                        $.ajax( {
                            type: "POST",
                            url: createOrderUrl,
                            dataType: "json",
                            data: {
                                state,
                                order_key: orderId,
                                auth_token: authToken,
                                nonce: createOrderNonce,
                            },
                            success: ( data ) => {
                                const {
                                    data: { location },
                                } = data
                                window.location = location
                            },
                            error: ( jqXHR, textStatus, errorThrown ) => {
                                console.debug( "Error:", textStatus, errorThrown )
                                console.debug( "Response:", jqXHR.responseText )

                                submitOrderFail(
                                    "createOrder",
                                    "The payment was successful, but the order could not be created.",
                                )
                            },
                        } )
                    } else if ( authResponse.state === "awaitingSignatory" ) {
                        const { pendingPaymentUrl, pendingPaymentNonce } = LedyerPayments.params
                        $.ajax( {
                            type: "POST",
                            url: pendingPaymentUrl,
                            dataType: "json",
                            data: {
                                order_key: orderId,
                                nonce: pendingPaymentNonce,
                            },
                            success: ( data ) => {
                                const {
                                    data: { location },
                                } = data
                                window.location = location
                            },
                            error: ( jqXHR, textStatus, errorThrown ) => {
                                console.debug( "Error:", textStatus, errorThrown )
                                console.debug( "Response:", jqXHR.responseText )

                                LedyerPayments.submitOrderFail(
                                    "pendingPayment",
                                    "The payment is pending payment. Failed to redirect to order received page.",
                                )
                            },
                        } )
                    }

            } catch ( error ) {
                LedyerPayments.unblockUI()
            }

            LedyerPayments.unblockUI()
        },

        /**
         * Prints a notice on the checkout page.
         * @param {string} message - The message to be displayed.
         * @returns {void}
         */
        printNotice: ( message ) => {
            const elementId = `${ LedyerPayments.gatewayId }-error-notice`

            // Remove any existing notice that we have created. This won't remove the default WooCommerce notices.
            $( `#${ elementId }` ).remove()

            const html = `<div id='${ elementId }' class='woocommerce-NoticeGroup'><ul class='woocommerce-error' role='alert'><li>${ message }</li></ul></div>`
            $( "form.checkout" ).prepend( html )

            document.getElementById( elementId ).scrollIntoView( { behavior: "smooth" } )
        },

        /**
         * Logs a message to the server.
         * @param {string} message - The message to be logged.
         * @param {string} level - The log level. Default is "notice".
         * @returns {void}
         */
        logToFile: ( message, level = "notice" ) => {
            const { logToFileUrl, logToFileNonce, reference } = LedyerPayments.params
            console.debug( message )

            $.ajax( {
                url: logToFileUrl,
                type: "POST",
                dataType: "json",
                data: {
                    level,
                    reference,
                    message: message,
                    nonce: logToFileNonce,
                },
            } )
        },

        /**
         * Unblocks the UI.
         * @returns {void}
         */
        unblockUI: () => {
            $( ".woocommerce-checkout-review-order-table" ).unblock()
            $( "form.checkout" ).removeClass( "processing" ).unblock()
        },

        /**
         * Blocks the UI.
         * @returns {void}
         */
        blockUI: () => {
            /* Order review. */
            $( ".woocommerce-checkout-review-order-table" ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )

            $( "form.checkout" ).addClass( "processing" )
            $( "form.checkout" ).block( {
                message: null,
                overlayCSS: {
                    background: "#fff",
                    opacity: 0.6,
                },
            } )
        },

        /**
         * Checks if the Ledyer Payments is current gateway.
         * @returns {boolean} - True if current gateway, false otherwise.
         */
        isActiveGateway: () => {
            if ( $( 'input[name="payment_method"]:checked' ).length ) {
                const currentGateway = $( 'input[name="payment_method"]:checked' ).val()
                return currentGateway.indexOf( LedyerPayments.gatewayId ) >= 0
            }

            return false
        },

        /**
         * Update the nonce values.
         *
         * This is required when a guest user is logged in and the nonce values are updated since the nonce is associated with the user ID (0 for guests).
         *
         * @param {object} nonce An object containing the new nonce values.
         */
        updateNonce: ( nonce ) => {
            for ( const key in nonce ) {
                if ( key in LedyerPayments.params ) {
                    LedyerPayments.params[ key ] = nonce[ key ]
                }
            }
        },

        /**
         * Handles failure to create WooCommerce order.
         *
         * @param {string} error - The error message.
         * @param {string} message - The message to be displayed.
         * @returns {void}
         */
        submitOrderFail: ( error, message ) => {
            console.debug( "[%s] Woo failed to create the order. Reason: %s", error, message )

            LedyerPayments.printNotice( message )
            LedyerPayments.unblockUI()
            $( document.body ).trigger( "checkout_error" )
            $( document.body ).trigger( "update_checkout" )
        },

        /**
         * Submits the checkout form to WooCommerce for order creation.
         *
         * @param {Event} e - The event object.
         * @returns {void}
         */
        submitOrder: ( e ) => {
            if ( $( "form.checkout" ).is( ".processing" ) ) {
                return false
            }

            e.preventDefault()
            LedyerPayments.blockUI()

            const { submitOrderUrl } = LedyerPayments.params
            $.ajax( {
                type: "POST",
                url: submitOrderUrl,
                data: $( "form.checkout" ).serialize(),
                dataType: "json",
                success: async ( data ) => {
                    try {
                        if ( data.nonce ) {
                            LedyerPayments.updateNonce( data.nonce )
                        }

                        if ( "success" === data.result ) {
                            const { order_key: orderId, customer } = data

                            LedyerPayments.logToFile(
                                `Successfully placed order ${ orderId }. Sending "shouldProceed: true".`,
                            )

                            LedyerPayments.handleProceedWithLedyer( orderId, customer )
                        } else {
                            console.warn( "AJAX request succeeded, but the Woo order was not created.", data )
                            throw "SubmitOrder failed"
                        }
                    } catch ( err ) {
                        console.error( err )
                        if ( data.messages ) {
                            // Strip HTML code from messages.
                            const messages = data.messages.replace( /<\/?[^>]+(>|$)/g, "" )

                            LedyerPayments.logToFile( "Checkout error | " + messages, "error" )
                            LedyerPayments.submitOrderFail( "submitOrder", messages )
                        } else {
                            LedyerPayments.logToFile( "Checkout error | No message", "error" )
                            LedyerPayments.submitOrderFail( "submitOrder", "Checkout error" )
                        }
                    }
                },
                error: ( data ) => {
                    try {
                        LedyerPayments.logToFile( "AJAX error | " + JSON.stringify( data ), "error" )
                    } catch ( e ) {
                        LedyerPayments.logToFile( "AJAX error | Failed to parse error message.", "error" )
                    }
                    LedyerPayments.submitOrderFail( "AJAX", "Something went wrong, please try again." )
                },
            } )
        },
    }

    LedyerPayments.init()
} )
