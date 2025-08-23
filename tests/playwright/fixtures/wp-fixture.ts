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
});

export { expect };
