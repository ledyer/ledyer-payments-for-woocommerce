const path = require( 'path' );
const DependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );

/**
 * Builds the WooCommerce Checkout block integration.
 *
 * WordPress and WooCommerce packages (react, @wordpress/*, @woocommerce/*) are not bundled. They are loaded as script
 * dependencies, which are written to blocks/build/[name].asset.php.
 */
module.exports = {
	mode: process.env.NODE_ENV === 'development' ? 'development' : 'production',
	entry: {
		checkout: './blocks/src/checkout/index.tsx',
	},
	output: {
		filename: '[name].js',
		path: path.resolve( __dirname, './blocks/build' ),
	},
	resolve: {
		extensions: [ '.ts', '.tsx', '.js' ],
	},
	module: {
		rules: [
			{
				test: /\.tsx?$/,
				use: 'ts-loader',
				exclude: /node_modules/,
			},
		],
	},
	plugins: [ new DependencyExtractionWebpackPlugin() ],
};
