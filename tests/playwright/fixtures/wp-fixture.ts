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
  wp: [async ({}, use) => {
    run('npx wp-env start');
    await use({});
    run('npx wp-env stop');
  }, { scope: 'worker', auto: true }],

  page: async ({ page }, use) => {
    await page.goto('/wp-login.php');
    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');
    await expect(page).toHaveURL(/wp-admin/);
    await use(page);
  },

  cleanup: [async ({}, use) => {
    run('npx wp-env run tests-cli "wp post delete $(wp post list --post_type=pit_item --format=ids) --force" || true');
    await use({});
    run('npx wp-env run tests-cli "wp post delete $(wp post list --post_type=pit_item --format=ids) --force" || true');
  }, { auto: true }],
});

export { expect };
