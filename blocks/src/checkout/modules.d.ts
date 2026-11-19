// WooCommerce packages are provided as globals (wc.*) at runtime and have no bundled type declarations.
declare module "@woocommerce/blocks-registry" {
    export function registerPaymentMethod( options: Record<string, unknown> ): void
}

declare module "@woocommerce/settings" {
    export function getSetting<T = unknown>( name: string, fallback?: T ): T
}

declare module "@wordpress/html-entities" {
    export function decodeEntities( text: string ): string
}
