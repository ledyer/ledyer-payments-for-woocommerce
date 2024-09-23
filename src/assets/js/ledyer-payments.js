jQuery( function ( $ ) {
    if ( typeof LedyerPaymentsParams === "undefined" ) {
        return false
    }

    const gatewayParams = LedyerPaymentsParams
    const { gatewayId, sessionId } = gatewayParams

    const handleProceedWithLedyer = async ( orderId, customer ) => {
        try {
            const authArgs = { sessionId, ...customer }
            console.log( authArgs )
            const authResponse = await window.ledyer.payments.api.authorize( authArgs )

            // ... some time will pass while the user is interacting with the dialog

            if ( authResponse ) {
                // if status is authorized, the order is ready to be created
                if ( authResponse.status === "authorized" ) {
                    // Get the authorization token to create an order from your backend
                    const authToken = authResponse.authToken
                    const { createOrderUrl, createOrderNonce } = gatewayParams

                    $.ajax( {
                        type: "POST",
                        url: createOrderUrl,
                        data: {
                            order_id: orderId,
                            auth_token: authToken,
                            nonce: createOrderNonce,
                        },
                        dataType: "json",
                        success: async ( data ) => {
                            console.log( data )
                        },
                        error: ( data ) => {
                            console.error( data )
                        },
                    } )
                }

                if ( authResponse.status === "awaitingSignatory" ) {
                    // A signatory is required to complete the purchase
                }

                // redirect the user to a success page
            }
        } catch ( error ) {
            // Handle error
            console.log( error )
        }

        unblockUI()
    }

    const logToFile = ( message, level = "notice" ) => {
        const { logToFileUrl, logToFileNonce, reference } = gatewayParams

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

    const unblockUI = () => {
        $( ".woocommerce-checkout-review-order-table" ).unblock()
        $( "form.checkout" ).removeClass( "processing" ).unblock()
    }

    const isActiveGateway = () => {
        if ( $( 'input[name="payment_method"]:checked' ).length ) {
            const currentGateway = $( 'input[name="payment_method"]:checked' ).val()
            return currentGateway.indexOf( gatewayId ) >= 0
        }

        return false
    }

    const submitOrderFail = ( error, message ) => {
        console.error( "[%s] Woo failed to create the order. Reason: %s", error, message )

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
                        console.log( "Woo order created successfully", data )
                        logToFile( 'Successfully placed order. Sending "shouldProceed: true".' )

                        console.log( data )
                        const { order_id: orderId, customer } = data
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

                        console.warn( "submitOrder: error %s", messages )
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
                submitOrderFail( "AJAX", "Internal Server Error" )
            },
        } )
    }

    $( "body" ).on( "click", "input#place_order, button#place_order", ( e ) => {
        submitOrder( e )
    } )
} )
