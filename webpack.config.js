var path = require('path');
var webpack = require('webpack');
var ExtractTextPlugin = require('extract-text-webpack-plugin');

var BUILD_DIR = path.resolve(__dirname, 'public');
var APP_DIR = path.resolve(__dirname, 'resources');

module.exports = {
  entry: APP_DIR + '/js/index.jsx',
  output: {
    path: BUILD_DIR,
    filename: 'js/bundle.js'
  },
  module: {
    loaders: [
      {
        test: /\.jsx?$/,
        include: APP_DIR + '/js',
        loader: 'babel-loader',
        exclude: /node_modules/,
        include: path.resolve(__dirname, 'resources/js'),
        query: {
          presets: ['env', 'react', 'flow'],
          plugins: ['transform-object-rest-spread', 'transform-class-properties']
        }
      },
      {
        test: /\.(scss|css)$/,
        loader: ExtractTextPlugin.extract({
          fallback: 'style-loader',
          use: ['css-loader', 'sass-loader']
        })
      },
      {
        test: /\.(eot|svg|ttf|woff|woff2)$/,
        loader: 'file-loader?name=[name].[ext]&outputPath=/fonts/'
      }
    ]
  },
  plugins: [
      new ExtractTextPlugin({
        filename: 'css/styles.css',
        allChunks: true
      })
  ]
};
