const path = require('path');

module.exports = {
  entry: {
    app: './assets/app.js',
    admin: './assets/admin.js'
  },
  output: {
    path: path.resolve(__dirname, 'assets'),
    filename: '[name].js'
  },
  module: {
    rules: [
      {
        test: /\.css$/i,
        use: ['style-loader', 'css-loader']
      }
    ]
  }
};
