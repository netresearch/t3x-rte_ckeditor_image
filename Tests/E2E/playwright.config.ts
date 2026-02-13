import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  /* Allow parallel workers in CI - improves speed for larger test suites */
  workers: process.env.CI ? 2 : undefined,
  timeout: 30_000,
  reporter: 'html',
  use: {
    baseURL: process.env.BASE_URL || 'https://v13.rte-ckeditor-image.ddev.site',
    trace: 'on-first-retry',
    ignoreHTTPSErrors: true,
  },
  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
