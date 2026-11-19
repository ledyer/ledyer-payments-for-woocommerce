import type { ComponentType } from "react"

/**
 * The data from CheckoutBlock::get_payment_method_data().
 */
export interface LedyerSettings {
    title: string
    description: string
    iconUrl: string
    features: string[]
    logToFile: { url: string; nonce: string }
    createOrder: { url: string }
    pending: { url: string }
    i18n: Record<
        | "companyNumber"
        | "reference1"
        | "reference1Help"
        | "reference2"
        | "reference2Help"
        | "companyNumberMissing"
        | "placeOrder"
        | "notAuthorized"
        | "genericError",
        string
    >
}

export interface LedyerAuthorizeResponse {
    state: "authorized" | "awaitingSignatory" | string
    authorizationToken?: string
}

type Unsubscribe = () => void
type Observer<T> = ( callback: ( data: T ) => unknown ) => Unsubscribe

/**
 * The subset of the props that WooCommerce passes to payment method components that this integration uses.
 */
export interface PaymentMethodProps {
    eventRegistration: {
        onPaymentSetup: Observer<void>
        /** Deprecated alias of onPaymentSetup, used by older WooCommerce versions. */
        onPaymentProcessing?: Observer<void>
        onCheckoutSuccess: Observer<{ processingResponse?: { paymentDetails?: Record<string, string> } }>
    }
    emitResponse: {
        responseTypes: { SUCCESS: string; ERROR: string; FAIL: string }
        noticeContexts: { PAYMENTS: string; CHECKOUT: string }
    }
    components: {
        PaymentMethodLabel: ComponentType<{ text: string }>
    }
}

declare global {
    interface Window {
        ledyer?: {
            payments?: {
                api?: {
                    authorize: ( args: { customer: Record<string, unknown>; sessionId: string } ) => Promise<LedyerAuthorizeResponse>
                }
            }
        }
    }
}
