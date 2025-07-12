/**
 * Webpack configuration.
 */

const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');
const CssMinimizerPlugin = require('css-minimizer-webpack-plugin');
const cssnano = require('cssnano'); // https://cssnano.co/
const { CleanWebpackPlugin } = require('clean-webpack-plugin');
const TerserPlugin = require('terser-webpack-plugin');
const CopyPlugin = require('copy-webpack-plugin'); // https://webpack.js.org/plugins/copy-webpack-plugin/
const DependencyExtractionWebpackPlugin = require('@wordpress/dependency-extraction-webpack-plugin');
const ESLintPlugin = require('eslint-webpack-plugin');
const BundleAnalyzerPlugin = require('webpack-bundle-analyzer').BundleAnalyzerPlugin;


// JS Directory path.
const JS_DIR = path.resolve(__dirname, 'assets/src/js');
const IMG_DIR = path.resolve(__dirname, 'assets/src/img');
// const LIB_DIR = path.resolve( __dirname, 'assets/src/library' );
const BUILD_DIR = path.resolve(__dirname, 'assets/build');

const entry = {
	editor: JS_DIR + '/editor.js',
	frontend: JS_DIR + '/frontend.js',
	admin: JS_DIR + '/admin.js',
};

const output = {
	path: BUILD_DIR,
	filename: 'js/[name].js',
	chunkFilename: 'js/[name].chunk.js',
};

/**
 * Note: argv.mode will return 'development' or 'production'.
 */
const plugins = (argv) => {
	const pluginsList = [
		new CleanWebpackPlugin({
			cleanStaleWebpackAssets: ('production' === argv.mode) // Automatically remove all unused webpack assets on rebuild, when set to true in production. ( https://www.npmjs.com/package/clean-webpack-plugin#options-and-defaults-optional )
		}),

		new MiniCssExtractPlugin({
			filename: 'css/[name].css',
			chunkFilename: 'css/[name].chunk.css'
		}),

		new ESLintPlugin({
			// This tells the plugin to use your .eslintrc.json file
			useEslintrc: true,
			files: 'assets/src/js/**/*.js', // Specify which files to lint
			fix: true,// Fix ESLint errors if possible
			// Other options you might want
			emitWarning: true,
			emitError: false,
			failOnError: ('production' === argv.mode),
			failOnWarning: false,
			cache: true,  // This helps reduce repeated warnings
			cacheLocation: 'node_modules/.cache/eslint/',
			lintDirtyModulesOnly: true,   // Only lint changed files during watch
		}),
		new DependencyExtractionWebpackPlugin({
			injectPolyfill: true,
			combineAssets: true,
		})
	];

	// Only add analyzer when ANALYZE env variable is true
	if (process.env.ANALYZE) {
		pluginsList.push(
			new BundleAnalyzerPlugin({
				analyzerMode: 'server',
				analyzerPort: 8888,
				openAnalyzer: true
			})
		);
	}

	return pluginsList

};

const rules = [
	{
		test: /\.js$/,
		include: [JS_DIR],
		exclude: /node_modules/,
		use: [
			{
				loader: 'babel-loader',
				options: {
					cacheDirectory: true,
					presets: [
						['@babel/preset-env', {
							useBuiltIns: 'usage',
							corejs: 3,
							modules: false,
							targets: {
								browsers: [
									'last 2 versions',
									'not dead',
									'not ie <= 11'
								]
							}
						}]
					]
				}
			}
		]
	},
	{
		test: /\.(scss|sass)$/,
		exclude: /node_modules/,
		use: [
			{
				loader: MiniCssExtractPlugin.loader,
				options: {
					esModule: false
				}
			},
			{
				loader: 'css-loader',
				options: {
					sourceMap: true,
					importLoaders: 2
				}
			},
			{
				loader: 'postcss-loader',
				options: {
					postcssOptions: {
						plugins: [
							['autoprefixer'],
							['cssnano', {
								preset: ['default', {
									discardComments: {
										removeAll: true
									}
								}]
							}]
						]
					},
					sourceMap: true
				}
			},
			{
				loader: 'sass-loader',
				options: {
					implementation: require('sass'),
					sassOptions: (loaderContext) => ({
						indentedSyntax: /\.sass$/i.test(loaderContext.resourcePath), // Enable indented syntax for .sass files
						outputStyle: 'compressed'
					}),
					sourceMap: true
				}
			}
		]
	},
	{
		test: /\.(png|jpg|svg|jpeg|gif|ico)$/,
		type: 'asset',
		parser: {
			dataUrlCondition: {
				maxSize: 8 * 1024 // 8kb
			}
		},
		generator: {
			filename: 'img/[name].[hash][ext]'
		}
	},
	{
		test: /\.(ttf|otf|eot|svg|woff(2)?)(\?[a-z0-9]+)?$/,
		exclude: [IMG_DIR, /node_modules/],
		type: 'asset',
		generator: {
			filename: 'fonts/[name].[hash][ext]'
		}
	}
];

/**
 * Since you may have to disambiguate in your webpack.config.js between development and production builds,
 * you can export a function from your webpack configuration instead of exporting an object
 *
 * @param {string} env environment ( See the environment options CLI documentation for syntax examples. https://webpack.js.org/api/cli/#environment-options )
 * @param argv options map ( This describes the options passed to webpack, with keys such as output-filename and optimize-minimize )
 * @return {{output: *, devtool: string, entry: *, optimization: {minimizer: [*, *]}, plugins: *, module: {rules: *}, externals: {jquery: string}}}
 *
 * @see https://webpack.js.org/configuration/configuration-types/#exporting-a-function
 */
module.exports = (env, argv) => ({

	entry: entry,

	output: output,

	/**
	 * A full SourceMap is emitted as a separate file ( e.g.  main.js.map )
	 * It adds a reference comment to the bundle so development tools know where to find it.
	 * set this to false if you don't need it
	 */
	devtool: 'production' === argv.mode ? 'source-map' : 'eval-source-map',

	module: {
		rules: rules,
	},

	optimization: {
		minimize: true,
		minimizer: [
			new CssMinimizerPlugin({
				minimizerOptions: {
					preset: [
						'default',
						{
							discardComments: { removeAll: true }
						}
					]
				}
			}),
			new TerserPlugin({
				parallel: true,
				terserOptions: {
					compress: {
						drop_console: 'production' === argv.mode,
						drop_debugger: true
					},
					output: {
						comments: false
					}
				},
				extractComments: false
			})
		],
		splitChunks: {
			cacheGroups: {
				commons: {
					test: /[\\/]node_modules[\\/]/,
					name: 'vendors',
					chunks: 'all'
				},
				styles: {
					name: 'styles',
					test: /\.css$/,
					chunks: 'all',
					enforce: true
				}
			}
		},
		runtimeChunk: 'single'
	},

	plugins: plugins(argv),

	externals: {
		jquery: 'jQuery',
		'@wordpress/element': 'wp.element',
		'@wordpress/blocks': 'wp.blocks',
		'@wordpress/components': 'wp.components',
		'@wordpress/block-editor': 'wp.blockEditor'
	},

	stats: {
		// Reduce console output during watch
		all: false,
		errors: true,
		warnings: true,
		assets: false,
		modules: false,
	},
	// ignoreWarnings is a top-level webpack config property
	ignoreWarnings: [
		// Ignore Sass deprecation warnings
		/Deprecation.*legacy JS API/,
		// Optionally ignore ESLint warnings during watch
		/\[eslint\]/,
	],

	cache: {
		type: 'filesystem',
		buildDependencies: {
			config: [__filename]
		}
	}
}); 