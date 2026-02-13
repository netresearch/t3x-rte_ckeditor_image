import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  /* Allow parallel workers in CI - improves speed for larger test suites */
  workers: process.env.CI ? 2 : undefined,
  /* CI uses PHP built-in server which is slower under load from parallel
     workers — backend tests need login + navigation + CKEditor load before
     the actual test logic runs, so 30s default is too tight. */
  timeout: process.env.CI ? 60_000 : 30_000,
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
