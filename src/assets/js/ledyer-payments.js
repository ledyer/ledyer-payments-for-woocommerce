jQuery( function ( $ ) {
    if ( typeof KLPParams === "undefined" ) {
        return false
    }

    const handleProceedWithLedyer = async () => {
        try {
            const authArgs = { ...KLPParams.customer, sessionId: KLPParams.sessionId }
            const authResponse = await window.ledyer.payments.api.authorize( authArgs )

            // ... some time will pass while the user is interacting with the dialog

            if ( authResponse ) {
                // if status is authorized, the order is ready to be created
                if ( authResponse.status === "authorized" ) {
                    // Get the authorization token to create an order from your backend
                    const authToken = authResponse.authToken
                }

                if ( authResponse.status === "awaitingSignatory" ) {
                    // A signatory is required to complete the purchase
                }

                // redirect the user to a success page
            }
        } catch ( error ) {
            // Handle error
        }
    }
} )
