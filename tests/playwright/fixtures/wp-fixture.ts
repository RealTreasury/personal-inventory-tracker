import { test as base, expect } from '@playwright/test';
import { execSync } from 'child_process';

function run(command: string) {
  try {
    execSync(command, { stdio: 'inherit' });
  } catch (error) {
    console.warn(`Failed to run "${command}":`, error);
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
  adminPage: async ({ page }, use) => {
    await page.goto('http://localhost:8888/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await use(page);
  },
  cleanup: [
    async ({}, use) => {
      await use();
      run(
        'npx wp-env run cli "wp post delete $(wp post list --post_type=pit_item --format=ids) --force"'
      );
    },
    { auto: true },
  ],
});

export { expect };
