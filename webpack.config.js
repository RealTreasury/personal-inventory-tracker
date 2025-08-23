const path = require('path');

module.exports = {
  entry: {
    admin: './src/admin.js',
    app: './src/app.js'
  },
  output: {
    path: path.resolve(__dirname, 'assets'),
    filename: '[name].js'
  }
};
