jQuery( function ( $ ) {
    if ( typeof LedyerPaymentsParams === "undefined" ) {
        return false
    }

    const gatewayParams = LedyerPaymentsParams
    const { gatewayId, sessionId } = gatewayParams

    /**
     * Handles the process of proceeding with Ledyer payment for an order.
     *
     * @param {string} orderId - The key of the order.
     * @param {Object} customerData - The customer data.
     * @returns {void}
     */
    const handleProceedWithLedyer = async ( orderId, customerData ) => {
        try {
            const authArgs = { customer: { ...customerData }, sessionId }
            const authResponse = await window.ledyer.payments.api.authorize( authArgs )

            // ... some time will pass while the user is interacting with the dialog

            if (authResponse) {
                
                // if status is authorized, the order is ready to be created
                if ( authResponse.state === "authorized" ) {
                    // Get the authorization token to create an order from your backend
                    const authToken = authResponse.authorizationToken
                    const { state } = authResponse
                    const { createOrderUrl, createOrderNonce } = gatewayParams

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
                }

                if ( authResponse.state === "awaitingSignatory" ) {
                    const { pendingPaymentUrl, pendingPaymentNonce } = gatewayParams
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

                            submitOrderFail(
                                "createOrder",
                                "The payment is pending payment. Failed to redirect to order received page.",
                            )
                        },
                    } )
                }

                // redirect the user to a success page
            }
        } catch ( error ) {
            // Handle error
            console.debug( "error: %s", error )
        }

        unblockUI()
    }

    const printNotice = ( message ) => {
        const elementId = `${ gatewayId }-error-notice`

        // Remove any existing notice that we have created. This won't remove the default WooCommerce notices.
        $( `#${ elementId }` ).remove()

        const html = `<div id='${ elementId }' class='woocommerce-NoticeGroup'><ul class='woocommerce-error' role='alert'><li>${ message }</li></ul></div>`
        $( "form.checkout" ).prepend( html )

        document.getElementById( elementId ).scrollIntoView( { behavior: "smooth" } )
    }

    const logToFile = ( message, level = "notice" ) => {
        const { logToFileUrl, logToFileNonce, reference } = gatewayParams
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
    }

    const unblockUI = () => {
        $( ".woocommerce-checkout-review-order-table" ).unblock()
        $( "form.checkout" ).removeClass( "processing" ).unblock()
    }

    const blockUI = () => {
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
    }

    const isActiveGateway = () => {
        if ( $( 'input[name="payment_method"]:checked' ).length ) {
            const currentGateway = $( 'input[name="payment_method"]:checked' ).val()
            return currentGateway.indexOf( gatewayId ) >= 0
        }

        return false
    }

    const submitOrderFail = ( error, message ) => {
        console.debug( "[%s] Woo failed to create the order. Reason: %s", error, message )

        printNotice( message )
        unblockUI()
        $( document.body ).trigger( "checkout_error" )
        $( document.body ).trigger( "update_checkout" )
    }

    const submitOrder = ( e ) => {
        if ( ! isActiveGateway() ) {
            return false
        }

        if ( $( "form.checkout" ).is( ".processing" ) ) {
            return false
        }

        e.preventDefault()
        blockUI()

        const { submitOrderUrl } = gatewayParams
        $.ajax( {
            type: "POST",
            url: submitOrderUrl,
            data: $( "form.checkout" ).serialize(),
            dataType: "json",
            success: async ( data ) => {
                try {
                    if ( "success" === data.result ) {
                        const { order_key: orderId, customer } = data

                        logToFile( `Successfully placed order ${ orderId }. Sending "shouldProceed: true".` )

                        await handleProceedWithLedyer( orderId, customer )
                    } else {
                        console.warn( "AJAX request succeeded, but the Woo order was not created.", data )
                        throw "SubmitOrder failed"
                    }
                } catch ( err ) {
                    console.error( err )
                    if ( data.messages ) {
                        // Strip HTML code from messages.
                        const messages = data.messages.replace( /<\/?[^>]+(>|$)/g, "" )

                        logToFile( "Checkout error | " + messages, "error" )
                        submitOrderFail( "submitOrder", messages )
                    } else {
                        logToFile( "Checkout error | No message", "error" )
                        submitOrderFail( "submitOrder", "Checkout error" )
                    }
                }
            },
            error: ( data ) => {
                try {
                    logToFile( "AJAX error | " + JSON.stringify( data ), "error" )
                } catch ( e ) {
                    logToFile( "AJAX error | Failed to parse error message.", "error" )
                }
                submitOrderFail( "AJAX", "Something went wrong, please try again." )
            },
        } )
    }

    $( "body" ).on( "click", "input#place_order, button#place_order", ( e ) => {
        submitOrder( e )
    } )
} )
