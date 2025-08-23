import { test, expect } from './fixtures/wp-fixture';
import { execSync } from 'child_process';

test('CSV export streams thousands of items', async () => {
  execSync(
    'npx wp-env run cli "wp post generate --post_type=pit_item --count=1500"',
    { stdio: 'inherit' }
  );

  const csv = execSync(
    String.raw`npx wp-env run cli "wp eval 'RealTreasury\Inventory\Import_Export::generate_csv( array(), fopen( "php://output", "w" ) );'"`,
    { encoding: 'utf8' }
  );

  const lines = csv.trim().split(/\n/);
  expect(lines.length).toBe(1501);
});

test('PDF export generates a PDF file', async () => {
  const pdf = execSync(
    String.raw`npx wp-env run cli "wp eval 'echo base64_encode( RealTreasury\\Inventory\\Import_Export::generate_pdf() );'"`,
    { encoding: 'utf8' }
  );
  const buffer = Buffer.from(pdf.trim(), 'base64');
  expect(buffer.slice(0, 4).toString()).toBe('%PDF');
});

test('Excel export generates an XLSX file', async () => {
  const xlsx = execSync(
    String.raw`npx wp-env run cli "wp eval 'echo base64_encode( RealTreasury\\Inventory\\Import_Export::generate_excel() );'"`,
    { encoding: 'utf8' }
  );
  const buffer = Buffer.from(xlsx.trim(), 'base64');
  expect(buffer.slice(0, 2).toString('hex')).toBe('504b');
});
