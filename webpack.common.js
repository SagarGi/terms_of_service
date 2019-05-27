const path = require('path');
const { VueLoaderPlugin } = require('vue-loader');

module.exports = {
	entry: {
		admin: path.join(__dirname, 'src', 'admin.js'),
		user: path.join(__dirname, 'src', 'user.js'),
		public: path.join(__dirname, 'src', 'public.js'),
	},
	output: {
		path: path.resolve(__dirname, './js'),
		publicPath: '/js/',
		filename: 'terms_of_service_[name].js'
	},
	module: {
		rules: [
			{
				test: /\.css$/,
				use: ['vue-style-loader', 'css-loader']
			},
			{
				test: /\.vue$/,
				loader: 'vue-loader'
			},
			{
				test: /\.js$/,
				loader: 'babel-loader',
				exclude: /node_modules/
			},
			{
				test: /\.(png|jpg|gif|svg)$/,
				loader: 'file-loader',
				options: {
					name: '[name].[ext]?[hash]'
				}
			},
			{
				/**
				 * Fixes lodash registering globally and therefore replacing server's underscore
				 *
				 * https://github.com/lodash/lodash/issues/1798#issuecomment-233804586
				 * https://github.com/webpack/webpack/issues/3017#issuecomment-285954512
				 */
				parser: {
					amd: false
				}
			}
		],
	},
	plugins: [new VueLoaderPlugin()],
	resolve: {
		alias: {
			vue$: 'vue/dist/vue.esm.js'
		},
		extensions: ['*', '.js', '.vue', '.json']
	}
};
