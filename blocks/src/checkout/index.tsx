/**
 * Ledyer Payments for the WooCommerce Checkout block.
 *
 * Flow:
 * 1. The customer enters the company number and the optional references. They are sent with the payment data when the order is placed.
 * 2. The Store API creates the order and calls Gateway::process_payment(), which updates the Ledyer session and returns the customer data.
 * 3. On checkout success, the Ledyer SDK authorizes the payment, and the existing AJAX endpoints create the Ledyer order or mark it as pending.
 */
import React, { useEffect, useRef, useState } from "react"
import { registerPaymentMethod } from "@woocommerce/blocks-registry"
import { getSetting } from "@woocommerce/settings"
import { decodeEntities } from "@wordpress/html-entities"

import type { LedyerAuthorizeResponse, LedyerSettings, PaymentMethodProps } from "./types"

const settings = getSetting( "ledyer_payments_data", {} ) as LedyerSettings
const title = decodeEntities( settings.title || "Ledyer Payments" )
const description = decodeEntities( settings.description || "" )
const i18n = settings.i18n

const FIELD_COMPANY_NUMBER = "billing_company_number"
const FIELD_REFERENCE_1 = "ledyer_customer_reference_1"
const FIELD_REFERENCE_2 = "ledyer_customer_reference_2"

/**
 * Post form data to a WC AJAX endpoint and return the parsed JSON.
 */
const post = async ( url: string, data: Record<string, string> ) => {
    const response = await fetch( url, {
        method: "POST",
        credentials: "same-origin",
        body: new URLSearchParams( data ),
    } )

    return response.json()
}

/**
 * Log a message to the WooCommerce log. Errors are ignored since logging must never break the checkout.
 */
const log = ( message: string, nonce: string, level: "notice" | "error" = "notice" ) => {
    post( settings.logToFile.url, { message, level, reference: "", nonce } ).catch( () => undefined )
}

/**
 * Authorize the payment with Ledyer, then create the Ledyer order (or mark it as pending signatory).
 *
 * @returns The URL to redirect the customer to.
 * @throws Error with a customer-facing message if the payment was not completed.
 */
const authorizeAndCreateOrder = async ( details: Record<string, string> ): Promise<string> => {
    const orderKey = details.ledyer_order_key
    const logNonce = details.ledyer_log_nonce || settings.logToFile.nonce

    if ( ! window.ledyer?.payments?.api ) {
        log( `Ledyer SDK not loaded for order ${ orderKey }.`, logNonce, "error" )
        throw new Error( i18n.genericError )
    }

    let authResponse: LedyerAuthorizeResponse
    try {
        authResponse = await window.ledyer.payments.api.authorize( {
            customer: JSON.parse( details.ledyer_customer || "{}" ),
            sessionId: details.ledyer_session_id,
        } )
    } catch ( error ) {
        log( `Authorization failed for order ${ orderKey }: ${ String( error ) }`, logNonce, "error" )
        throw new Error( i18n.notAuthorized )
    }

    const state = authResponse?.state
    log( `Authorization response for order ${ orderKey }: ${ state }`, logNonce )

    let result
    if ( "authorized" === state ) {
        result = await post( settings.createOrder.url, {
            state,
            order_key: orderKey,
            auth_token: authResponse.authorizationToken ?? "",
            nonce: details.ledyer_create_order_nonce,
        } )
    } else if ( "awaitingSignatory" === state ) {
        result = await post( settings.pending.url, {
            order_key: orderKey,
            nonce: details.ledyer_pending_payment_nonce,
        } )
    } else {
        throw new Error( i18n.notAuthorized )
    }

    // The create order endpoint returns the order received URL even on failure, since the order is then confirmed (and retried) from the thank you page.
    const location = result?.data?.location
    if ( ! location ) {
        log( `Missing redirect location for order ${ orderKey }: ${ JSON.stringify( result ) }`, logNonce, "error" )
        throw new Error( i18n.genericError )
    }

    return location
}

const TextField = ( {
    id,
    label,
    value,
    onChange,
    help,
    error,
    required = false,
    disabled = false,
}: {
    id: string
    label: string
    value: string
    onChange: ( value: string ) => void
    help?: string
    error?: string
    required?: boolean
    disabled?: boolean
} ) => {
    const classes = [ "wc-block-components-text-input" ]
    if ( value ) {
        classes.push( "is-active" )
    }
    if ( error ) {
        classes.push( "has-error" )
    }

    return (
        <div className={ classes.join( " " ) } style={ { marginTop: "16px" } }>
            <input
                type="text"
                id={ id }
                name={ id }
                value={ value }
                required={ required }
                disabled={ disabled }
                aria-invalid={ error ? "true" : "false" }
                aria-describedby={ error ? `${ id }-error` : undefined }
                title={ help }
                onChange={ ( event ) => onChange( event.target.value ) }
            />
            <label htmlFor={ id }>{ label }</label>
            { error && (
                <div className="wc-block-components-validation-error" role="alert">
                    <p id={ `${ id }-error` }>{ error }</p>
                </div>
            ) }
        </div>
    )
}

const Fields = ( {
    values,
    setValue,
    error,
    disabled = false,
}: {
    values: Record<string, string>
    setValue?: ( field: string, value: string ) => void
    error?: string
    disabled?: boolean
} ) => {
    const onChange = ( field: string ) => ( value: string ) => setValue?.( field, value )

    return (
        <>
            { description && <p>{ description }</p> }
            <TextField
                id={ FIELD_COMPANY_NUMBER }
                label={ i18n.companyNumber }
                value={ values[ FIELD_COMPANY_NUMBER ] }
                onChange={ onChange( FIELD_COMPANY_NUMBER ) }
                error={ error }
                required
                disabled={ disabled }
            />
            <TextField
                id={ FIELD_REFERENCE_1 }
                label={ i18n.reference1 }
                help={ i18n.reference1Help }
                value={ values[ FIELD_REFERENCE_1 ] }
                onChange={ onChange( FIELD_REFERENCE_1 ) }
                disabled={ disabled }
            />
            <TextField
                id={ FIELD_REFERENCE_2 }
                label={ i18n.reference2 }
                help={ i18n.reference2Help }
                value={ values[ FIELD_REFERENCE_2 ] }
                onChange={ onChange( FIELD_REFERENCE_2 ) }
                disabled={ disabled }
            />
        </>
    )
}

const emptyValues = {
    [ FIELD_COMPANY_NUMBER ]: "",
    [ FIELD_REFERENCE_1 ]: "",
    [ FIELD_REFERENCE_2 ]: "",
}

/**
 * The payment method content. It is only rendered while Ledyer is the selected payment method, so the event handlers below only run for Ledyer orders.
 */
const Content = ( props: PaymentMethodProps ) => {
    const { eventRegistration, emitResponse } = props
    const onPaymentSetup = eventRegistration.onPaymentSetup ?? eventRegistration.onPaymentProcessing
    const { onCheckoutSuccess } = eventRegistration

    const [ values, setValues ] = useState<Record<string, string>>( emptyValues )
    const [ error, setError ] = useState( "" )

    // The event handlers are registered once, so read the latest field values through a ref.
    const valuesRef = useRef( values )
    valuesRef.current = values

    const setValue = ( field: string, value: string ) => {
        setValues( ( current ) => ( { ...current, [ field ]: value } ) )
        if ( FIELD_COMPANY_NUMBER === field && value.trim() ) {
            setError( "" )
        }
    }

    useEffect( () => {
        return onPaymentSetup( () => {
            const data = Object.fromEntries(
                Object.entries( valuesRef.current ).map( ( [ key, value ] ) => [ key, value.trim() ] ),
            )

            if ( ! data[ FIELD_COMPANY_NUMBER ] ) {
                // Shown inline below the field, like WooCommerce's own field validation.
                setError( i18n.companyNumberMissing )
                document.getElementById( FIELD_COMPANY_NUMBER )?.focus()
                return { type: emitResponse.responseTypes.ERROR }
            }

            return {
                type: emitResponse.responseTypes.SUCCESS,
                meta: { paymentMethodData: data },
            }
        } )
    }, [ onPaymentSetup, emitResponse ] )

    useEffect( () => {
        return onCheckoutSuccess( async ( { processingResponse } ) => {
            try {
                const redirectUrl = await authorizeAndCreateOrder( processingResponse?.paymentDetails ?? {} )
                return { type: emitResponse.responseTypes.SUCCESS, redirectUrl }
            } catch ( e ) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: e instanceof Error && e.message ? e.message : i18n.genericError,
                    messageContext: emitResponse.noticeContexts.PAYMENTS,
                    retry: true,
                }
            }
        } )
    }, [ onCheckoutSuccess, emitResponse ] )

    return <Fields values={ values } setValue={ setValue } error={ error } />
}

const Edit = () => <Fields values={ emptyValues } disabled />

const Label = ( props: PaymentMethodProps ) => {
    const { PaymentMethodLabel } = props.components

    return (
        <span style={ { display: "flex", alignItems: "center", justifyContent: "space-between", width: "100%" } }>
            <PaymentMethodLabel text={ title } />
            { settings.iconUrl && (
                <img src={ settings.iconUrl } alt="" style={ { maxWidth: "120px", maxHeight: "25px", marginInline: "8px" } } />
            ) }
        </span>
    )
}

registerPaymentMethod( {
    name: "ledyer_payments",
    label: <Label { ...( {} as PaymentMethodProps ) } />,
    content: <Content { ...( {} as PaymentMethodProps ) } />,
    edit: <Edit />,
    canMakePayment: () => true,
    ariaLabel: title,
    placeOrderButtonLabel: i18n.placeOrder,
    supports: {
        features: settings.features ?? [ "products" ],
    },
} )
