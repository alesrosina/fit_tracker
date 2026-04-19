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

// Nextcloud apps run inside an already-loaded page; the default 244 KB limit
// is not meaningful here, and large chunks are split out via dynamic imports above.
webpackConfig.performance = { hints: false }

module.exports = webpackConfig
