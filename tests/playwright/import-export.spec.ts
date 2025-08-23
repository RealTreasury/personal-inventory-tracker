import { test as base, expect } from './fixtures/wp-fixture';
import { execSync } from 'child_process';

const test = base.extend({
  seededItems: [
    async ({}, use) => {
      execSync(
        'npx wp-env run cli "wp post generate --post_type=pit_item --count=1500"',
        { stdio: 'inherit' }
      );
      await use();
      execSync(
        'npx wp-env run cli "wp post delete $(wp post list --post_type=pit_item --format=ids) --force"',
        { stdio: 'inherit' }
      );
    },
    { auto: true },
  ],
});

test('CSV export streams thousands of items', async ({ seededItems }) => {
  const csv = execSync(
    String.raw`npx wp-env run cli "wp eval 'RealTreasury\Inventory\Import_Export::generate_csv( array(), fopen( \"php://output\", \"w\" ) );'"`,
    { encoding: 'utf8' }
  );

  const lines = csv.trim().split(/\n/);
  expect(lines.length).toBe(1501);
});

