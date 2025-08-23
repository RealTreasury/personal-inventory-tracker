const esbuild = require('esbuild');

const shared = {
  bundle: true,
  minify: false,
  sourcemap: false,
};

esbuild.build({
  ...shared,
  entryPoints: ['src/app.js'],
  outfile: 'assets/app.js',
}).catch(() => process.exit(1));

esbuild.build({
  ...shared,
  entryPoints: ['src/admin.js'],
  outfile: 'assets/admin.js',
}).catch(() => process.exit(1));

esbuild.build({
  ...shared,
  entryPoints: ['src/app.css'],
  outfile: 'assets/app.css',
}).catch(() => process.exit(1));

esbuild.build({
  ...shared,
  entryPoints: ['src/admin.css'],
  outfile: 'assets/admin.css',
}).catch(() => process.exit(1));
