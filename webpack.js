const path = require('path')
const MiniCssExtractPlugin = require('mini-css-extract-plugin')
const webpackConfig = require('@nextcloud/webpack-vue-config')

webpackConfig.entry = {
    'fit_tracker-main': path.join(__dirname, 'src', 'main.js'),
}

// Use short hash-based chunk names; fix publicPath for custom_apps installation
webpackConfig.output = {
    ...webpackConfig.output,
    publicPath: '/custom_apps/fit_tracker/js/',
    chunkFilename: 'fit_tracker-chunk-[contenthash:8].js',
}

// Extract CSS into a separate file instead of injecting via style-loader
webpackConfig.module.rules = webpackConfig.module.rules.map(rule => {
    if (rule.test && rule.test.toString() === '/\\.css$/') {
        return { test: /\.css$/, use: [MiniCssExtractPlugin.loader, 'css-loader'] }
    }
    if (rule.test && rule.test.toString() === '/\\.scss$/') {
        return { test: /\.scss$/, use: [MiniCssExtractPlugin.loader, 'css-loader', 'sass-loader'] }
    }
    return rule
})

webpackConfig.plugins = [
    ...webpackConfig.plugins,
    new MiniCssExtractPlugin({
        filename: '../css/[name].css',
    }),
]

module.exports = webpackConfig
