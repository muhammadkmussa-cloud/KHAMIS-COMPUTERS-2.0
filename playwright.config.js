const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 1,
  workers: 1,
  reporter: 'html',
  use: {
    baseURL: 'http://localhost:8081',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },
  projects: [
    {
      name: 'admin-desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
      },
    },
    {
      name: 'cashier-desktop',
      use: {
        ...devices['Desktop Chrome'],
        viewport: { width: 1440, height: 900 },
      },
    },
    {
      name: 'admin-tablet',
      use: {
        ...devices['iPad Pro'],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'cashier-tablet',
      use: {
        ...devices['iPad Pro'],
        viewport: { width: 768, height: 1024 },
      },
    },
    {
      name: 'admin-mobile',
      use: {
        ...devices['iPhone 12 Pro'],
        viewport: { width: 375, height: 667 },
      },
    },
    {
      name: 'cashier-mobile',
      use: {
        ...devices['iPhone 12 Pro'],
        viewport: { width: 375, height: 667 },
      },
    },
    {
      name: 'customer-mobile',
      use: {
        ...devices['iPhone 12 Pro'],
        viewport: { width: 375, height: 667 },
      },
    },
  ],
});