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
  banner: { js: '/*! Personal Inventory Tracker Enhanced */' },
  logLevel: 'info',
};

const artifactsDir = path.join(__dirname, 'artifacts');

function processCss() {
  const cssFile = path.join(artifactsDir, 'enhanced_css.css');
  if (!fs.existsSync(cssFile)) return;
  let css = fs.readFileSync(cssFile, 'utf8');
  css = css.replace(/\/\*[^]*?\*\//g, '').replace(/\s+/g, ' ').trim();
  fs.writeFileSync(path.join(__dirname, 'assets', 'app.css'), css);
}

const builds = [
  {
    entryPoints: { app: 'src/frontend-app.jsx' },
    outdir: 'assets',
    loader: { '.jsx': 'jsx' },
    format: 'esm',
    splitting: true,
    chunkNames: 'chunks/[name]-[hash]',
    entryNames: '[name]',
  },
  { entryPoints: ['src/admin.js'], outfile: 'assets/admin.js', globalName: 'PITAdmin' },
  { entryPoints: ['src/ocr.js'], outfile: 'assets/ocr.js', globalName: 'PITOcr' },
  { entryPoints: ['src/import-export.jsx'], outfile: 'assets/import-export.js', globalName: 'PITImportExport', loader: { '.jsx': 'jsx' }, external: ['react','react-dom'] },
  { entryPoints: ['src/analytics.jsx'], outfile: 'assets/analytics.js', globalName: 'PITAnalytics', loader: { '.jsx': 'jsx' }, external: ['react','react-dom'] },
  { entryPoints: ['src/shopping-list.jsx'], outfile: 'assets/shopping-list.js', globalName: 'PITShoppingList', loader: { '.jsx': 'jsx' }, external: ['react','react-dom'] },
  {
    entryPoints: ['src/ocr-scanner.jsx'],
    outfile: 'assets/ocr-scanner.js',
    loader: { '.jsx': 'jsx' },
    format: 'esm',
  },
];

function addPhpGuards() {
  const blocksDir = path.join(__dirname, 'blocks');

  function handleDir(dir) {
    if (!fs.existsSync(dir)) return;
    const entries = fs.readdirSync(dir, { withFileTypes: true });
    for (const entry of entries) {
      const fullPath = path.join(dir, entry.name);
      if (entry.isDirectory()) {
        handleDir(fullPath);
      } else if (entry.isFile() && entry.name.endsWith('.asset.php')) {
        let content = fs.readFileSync(fullPath, 'utf8');
        if (!content.includes("if ( ! defined( 'ABSPATH' ) ) { exit; }")) {
          content = content.replace('<?php\n', "<?php\nif ( ! defined( 'ABSPATH' ) ) { exit; }\n");
          fs.writeFileSync(fullPath, content);
        }
      }
    }
  }

  handleDir(blocksDir);
}

async function build(config) {
  if (isWatch) {
    const ctx = await esbuild.context(config);
    await ctx.watch();
  } else {
    await esbuild.build(config);
  }
}

async function run() {
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
  addPhpGuards();
}

run().catch(err => {
  console.error(err);
  process.exit(1);
});

module.exports = { build, builds, baseConfig };
