import { test as base, expect } from '@playwright/test';
import { execSync } from 'child_process';

function run(command: string, options: { encoding?: BufferEncoding } = {}) {
  try {
    return execSync(command, { stdio: 'inherit', ...options });
  } catch (error) {
    console.warn(`Failed to run "${command}":`, error);
    return null;
  }
}

export const test = base.extend({
  wp: [
    async ({}, use) => {
      run('npx wp-env start');
      await use({});
      run('npx wp-env stop');
    },
    { scope: 'worker', auto: true },
  ],
  page: async ({ page }, use) => {
    await page.goto('http://localhost:8888/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await Promise.all([
      page.waitForNavigation(),
      page.click('#wp-submit'),
    ]);
    await use(page);
  },
  cleanup: [
    async ({}, use) => {
      await use();
      try {
        const ids = execSync(
          'npx wp-env run cli "wp post list --post_type=pit_item --format=ids"',
          { encoding: 'utf8' }
        )
          .trim();
        if (ids) {
          run(`npx wp-env run cli "wp post delete ${ids} --force"`);
        }
      } catch (error) {
        console.warn('Cleanup failed:', error);
      }
    },
    { scope: 'test', auto: true },
  ],
});

export { expect };
