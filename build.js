const fs = require('fs');
const path = require('path');
const esbuild = require('esbuild');

const args = process.argv.slice(2);
const isDev = args.includes('--dev');
const isWatch = args.includes('--watch');

const baseConfig = {
  bundle: true,
  minify: !isDev,
  sourcemap: isDev,
  target: ['es2018'],
  external: [],
  banner: { js: '/*! Personal Inventory Tracker */' },
  logLevel: 'info',
};

const artifactsDir = path.join(__dirname, 'artifacts');

function generateEntry() {
  if (!fs.existsSync(artifactsDir)) return;
  const scripts = fs
    .readdirSync(artifactsDir)
    .filter(f => f.endsWith('.js'))
    .sort()
    .map(f => fs.readFileSync(path.join(artifactsDir, f), 'utf8'));
  fs.writeFileSync(path.join(__dirname, 'src', 'enhanced-app.js'), scripts.join('\n'));
}

function processCss() {
  const cssFile = path.join(artifactsDir, 'enhanced_css.css');
  if (!fs.existsSync(cssFile)) return;
  let css = fs.readFileSync(cssFile, 'utf8');
  css = css.replace(/\/\*[^]*?\*\//g, '').replace(/\s+/g, ' ').trim();
  fs.writeFileSync(path.join(__dirname, 'assets', 'app.css'), css);
}

const builds = [
  { entryPoints: ['src/enhanced-app.js'], outfile: 'assets/app.js', globalName: 'PITApp' },
  { entryPoints: ['src/admin.js'], outfile: 'assets/admin.js', globalName: 'PITAdmin' },
  { entryPoints: ['src/ocr.js'], outfile: 'assets/ocr.js', globalName: 'PITOcr', external: ['tesseract.js'] },
  { entryPoints: ['src/import-export.jsx'], outfile: 'assets/import-export.js', globalName: 'PITImportExport', loader: { '.jsx': 'jsx' }, external: ['react','react-dom'] },
  { entryPoints: ['src/shopping-list.jsx'], outfile: 'assets/shopping-list.js', globalName: 'PITShoppingList', loader: { '.jsx': 'jsx' }, external: ['react','react-dom'] },
  { entryPoints: ['src/ocr-scanner.jsx'], outfile: 'assets/ocr-scanner.js', globalName: 'PITOcrScanner', loader: { '.jsx': 'jsx' }, external: ['react','react-dom'] },
];

async function build(config) {
  if (isWatch) {
    const ctx = await esbuild.context(config);
    await ctx.watch();
  } else {
    await esbuild.build(config);
  }
}

async function run() {
  generateEntry();
  processCss();
  const enhancedReadme = `
# Personal Inventory Tracker Enhanced

Personal Inventory Tracker Enhanced adds OCR receipt scanning, analytics, and a streamlined admin experience.
Use this plugin to keep a detailed record of household items with minimal effort.

## Features
- OCR-powered item suggestion
- CSV import and export
- Responsive admin dashboard
- Lightweight build with esbuild

## Development
Run \`node build.js --dev --watch\` during development to rebuild on changes.

## License
GPL-2.0+
`;
  fs.writeFileSync(path.join(__dirname, 'README-ENHANCED.md'), enhancedReadme.trim() + '\n');

  await Promise.all(builds.map(cfg => build({ ...baseConfig, ...cfg })));
}

run().catch(err => {
  console.error(err);
  process.exit(1);
});

module.exports = { build, builds, baseConfig };
