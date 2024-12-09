jQuery( function ( $ ) {
    if ( typeof LedyerPaymentsParams === "undefined" ) {
        return false
    }

    const LedyerPayments = {
        params: LedyerPaymentsParams,
        gatewayId: LedyerPaymentsParams.gatewayId,
        sessionId: LedyerPaymentsParams.sessionId,
        i18n: {},

        init: () => {
            $( "body" ).on( "click", "input#place_order, button#place_order", ( e ) => {
                // Do not allow a purchase to go through if ANY error occurs.
                try {
                    if ( ! LedyerPayments.isActiveGateway() ) {
                        return
                    }

                    const organizationNumber = $( "#billing_company_number" ).val().trim()
                    if ( organizationNumber.length === 0 ) {
                        LedyerPayments.printNotice( LedyerPayments.params.i18n.companyNumberMissing )
                        return false
                    }

                    LedyerPayments.submitOrder( e )
                } catch ( error ) {
                    LedyerPayments.printNotice( LedyerPayments.params.i18n.genericError )
                    console.error( error )
                    return false
                }
            } )

            $( document ).ready( () => {
                // If "billing_form", remove the field from the payment_form and insert it after the company name field. Otherwise, if it is "payment_form", leave as-is.
                if ( LedyerPayments.params.companyNumberPlacement === "billing_form" ) {
                    if ( LedyerPayments.isActiveGateway() ) {
                        $( "#billing_company_number_field" ).remove()
                    }

                    // Required whenever the customer changes payment method.
                    $( "body" ).on( "change", 'input[name="payment_method"]', LedyerPayments.moveCompanyNumberField )
                    // Required when the checkout is initially loaded, and Ledyer is the chosen gateway.
                    $( "body" ).on( "updated_checkout", LedyerPayments.moveCompanyNumberField )
                }

                // Make the company name field required if Ledyer is the chosen gateway.
                LedyerPayments.toggleCheckoutField()
                $( "body" ).on( "change", 'input[name="payment_method"]', LedyerPayments.toggleCheckoutField )
                $( "body" ).on( "updated_checkout", LedyerPayments.toggleCheckoutField )
            } )
        },

        /**
         * Moves the company number field to the billing form or leaves in the payment method.
         * @returns {void}
         */
        moveCompanyNumberField: () => {
            if ( LedyerPayments.params.companyNumberPlacement === "billing_form" ) {
                if ( LedyerPayments.isActiveGateway() ) {
                    $( "#billing_company_number_field" ).detach().insertAfter( "#billing_company_field" ).show()
                } else {
                    $( "#billing_company_number_field" ).hide()
                }
            }
        },

        /**
         * Toggles the company name field between required and optional.
         * @returns {void}
         */
        toggleCheckoutField: () => {
            if ( LedyerPayments.isActiveGateway() ) {
                LedyerPayments.makeCheckoutFieldRequired( "billing_company_field" )
            } else {
                LedyerPayments.makeCheckoutFieldOptional( "billing_company_field", false )
            }
        },

        /**
         * Makes a checkout field required.
         * @param {string} id - The ID of the field.
         * @returns {void}
         */
        makeCheckoutFieldRequired: ( id ) => {
            const i18n = LedyerPayments.i18n.required ?? $( ".required" ).first().text()
            if ( i18n.length === 0 ) {
                // None of the fields are optional, there is nothing to do.
                return false
            } else {
                // Save the i18n for later use.
                LedyerPayments.i18n.required = i18n
            }

            const field = $( `#${ id }` )

            const input = field.find( "input" ).first()
            if ( input.attr( "aria-required" ) === "true" || input.attr( "required" ) === "true" ) {
                // The field is already required.
                return false
            }

            // Set a flag to determine whether the field was optional before.
            field.attr( "data-optional", "true" )

            // Make the input field required.
            input.attr( "aria-required", "true" )
            input.attr( "required", "true" )

            // Remove the optional label.
            const label = field.find( "label" ).first()
            label.find( ".optional" ).remove()

            // Add the required label.
            let clone = $( ".required" ).first()
            if ( clone.length === 0 ) {
                // No required field exists. Let us make some assumption and create one.
                clone = $.parseHTML( `<abbr class="required" title="required">${ i18n }</abbr>` )
            } else {
                clone = clone.clone()
            }
            label.append( clone )
        },

        /**
         * Makes a checkout field optional.
         * @param {string} id - The ID of the field.
         * @param {boolean} restore - Whether to restore the field to optional.
         * @returns {void}
         */
        makeCheckoutFieldOptional: ( id, restore = true ) => {
            const i18n = LedyerPayments.i18n.optional ?? $( ".optional" ).first().text()
            if ( i18n.length === 0 ) {
                // None of the fields are required, there is nothing to do.
                return false
            } else {
                // Save the i18n for later use.
                LedyerPayments.i18n.optional = i18n
            }

            const field = $( `#${ id }` )
            if ( ! field.attr( "data-optional" ) && ! restore ) {
                // If restore is false, we won't restore the field to optional.
                return false
            }

            if ( field.find( ".required" ).length === 0 ) {
                // The field is already optional.
                return false
            }

            // Make the input field optional.
            const input = field.find( "input" ).first()
            input.attr( "aria-required", "false" )
            input.attr( "required", "false" )

            // Remove the required label.
            const label = field.find( "label" ).first()
            label.find( ".required" ).remove()

            // Add the optional label.
            let el = $( ".optional" ).first()
            if ( el.length === 0 ) {
                // No optional field exists. Let us make some assumption and create one.
                el = $.parseHTML( `<span class="optional">${ i18n }</span>` )
            } else {
                el = el.clone()
            }
            label.append( el )
        },

        /**
         * Handles the process of proceeding with Ledyer payment for an order.
         *
         * @param {string} orderId - The key of the order.
         * @param {Object} customerData - The customer data.
         * @returns {void}
         */
        handleProceedWithLedyer: async ( orderId, customerData ) => {
            try {
                LedyerPayments.blockUI()

                const authArgs = { customer: { ...customerData }, sessionId: LedyerPayments.sessionId }
                const authResponse = await window.ledyer.payments.api.authorize( authArgs )

                if ( authResponse.state === "authorized" ) {
                    LedyerPayments.createOrder( authResponse, orderId )
                } else if ( authResponse.state === "awaitingSignatory" ) {
                    LedyerPayments.createPendingOrder( authResponse, orderId )
                }
            } catch ( error ) {
                console.error( error )
            } finally {
                LedyerPayments.unblockUI()
            }
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
         * @returns {void}
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

            LedyerPayments.unblockUI()
            $( document.body ).trigger( "checkout_error" )
            $( document.body ).trigger( "update_checkout" )

            // update_checkout clears notice.
            LedyerPayments.printNotice( message )
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

        /**
         * Informs Ledyer to proceed with creating the order in their system.
         *
         * This is done after the payment has been authorized, and we've verified that the order was created in WooCommerce.
         *
         * @throws {Error} If the authResponse state is not "authorized".
         *
         * @param {object} authResponse The response from the authorization request.
         * @param {string} orderId The WC order ID.
         * @returns {void}
         */
        createOrder: ( authResponse, orderId ) => {
            if ( authResponse.state !== "authorized" ) {
                throw new Error(
                    `createOrder was called with an invalid state. Received ${ authResponse.state }, expected 'authorized'.`,
                )
            }

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
                async: false,
                success: ( data ) => {
                    if ( ! data.success ) {
                        LedyerPayments.submitOrderFail(
                            "createOrder",
                            "The payment was successful, but the order could not be created.",
                        )

                        return
                    }

                    const {
                        data: { location },
                    } = data
                    window.location = location
                },
                error: ( jqXHR, textStatus, errorThrown ) => {
                    console.debug( "Error:", textStatus, errorThrown )
                    console.debug( "Response:", jqXHR.responseText )

                    console.error( errorThrown )
                    LedyerPayments.submitOrderFail(
                        "createOrder",
                        "The payment was successful, but the order could not be created.",
                    )
                },
            } )
        },

        /**
         * Informs Ledyer to proceed with creating the pending payment order in their system.
         *
         * This is done after the payment has been authorized, and we've verified that the order was created in WooCommerce.
         *
         * @throws {Error} If the authResponse state is not "awaitingSignatory".
         *
         * @param {string} orderId The WC order ID.
         * @returns {void}
         */
        createPendingOrder: ( authResponse, orderId ) => {
            if ( authResponse.state !== "awaitingSignatory" ) {
                throw new Error(
                    `createPendingOrder was called with an invalid state. Received ${ authResponse.state }, expected 'awaitingSignatory'.`,
                )
            }

            const { pendingPaymentUrl, pendingPaymentNonce } = LedyerPayments.params

            $.ajax( {
                type: "POST",
                url: pendingPaymentUrl,
                dataType: "json",
                data: {
                    order_key: orderId,
                    nonce: pendingPaymentNonce,
                },
                async: false,
                success: ( data ) => {
                    if ( ! data.success ) {
                        LedyerPayments.submitOrderFail(
                            "pendingPayment",
                            "The payment is pending payment. Failed to redirect to order received page.",
                        )

                        return
                    }

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
        },
    }

    LedyerPayments.init()
} )
