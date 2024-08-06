const path = require( 'path' );
module.exports = {
	mode: 'production', // production
	entry: {
		'ledyer-payments-checkout': './assets/js/ledyer-payments-checkout',
		'ledyer-payments-admin': './assets/js/ledyer-payments-admin',
	},

	output: {
		filename: '[name].min.js',
		path: path.resolve( __dirname, './assets/js' ),
	},
	devtool: 'source-map',
	module: {
		rules: [
			{
				test: /\.m?js$/,
				exclude: /(node_modules|bower_components)/,
				use: {
					loader: 'babel-loader',
					options: {
						presets: [ '@babel/preset-env' ],
						plugins: [ '@babel/plugin-proposal-object-rest-spread' ],
					},
				},
			},
		],
	},
};
