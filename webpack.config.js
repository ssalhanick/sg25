const path = require('path');
const MiniCssExtractPlugin = require('mini-css-extract-plugin');

module.exports = (env, argv) => {
    const isProduction = argv.mode === 'production';

    return {
        entry: {
            // Add your entry points here
            'theme': './wp-content/themes/sg-theme/src/js/theme.js',
            'admin': './wp-content/themes/sg-theme/src/js/admin.js',
        },
        output: {
            filename: isProduction ? 'js/[name].min.js' : 'js/[name].js',
            path: path.resolve(__dirname, 'wp-content/themes/sg-theme/dist'),
        },
        module: {
            rules: [
                {
                    test: /\.js$/,
                    exclude: /node_modules/,
                    use: {
                        loader: 'babel-loader',
                        options: {
                            presets: ['@babel/preset-env']
                        }
                    }
                },
                {
                    test: /\.(scss|css)$/,
                    use: [
                        MiniCssExtractPlugin.loader,
                        'css-loader',
                        'sass-loader'
                    ]
                }
            ]
        },
        plugins: [
            new MiniCssExtractPlugin({
                filename: isProduction ? 'css/[name].min.css' : 'css/[name].css'
            })
        ],
        devtool: isProduction ? false : 'source-map'
    };
}; 