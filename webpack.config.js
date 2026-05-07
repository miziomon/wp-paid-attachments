const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		index:  './assets/admin/index.jsx',
		widget: './assets/public/index.js',
	},
};
