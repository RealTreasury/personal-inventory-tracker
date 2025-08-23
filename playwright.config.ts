import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: 'tests/playwright',
  use: {
    baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
    headless: true,
  },
  projects: [
    {
      name: 'chromium',
      use: { browserName: 'chromium' },
    },
  ],
});
