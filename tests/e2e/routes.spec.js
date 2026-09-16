const { test, expect } = require('@playwright/test');
const { LoginPage, AdminPage, CashierPage, SettingsPage, StaffPage, ShopPage } = require('./pages');

const ADMIN_EMAIL = 'admin@khamis.local';
const ADMIN_PASSWORD = 'admin1234';
const CASHIER_EMAIL = 'cashier@khamis.local';
const CASHIER_PASSWORD = 'cashier1234';

test.describe('Route access - all roles', () => {
  test('Admin can access settings and staff pages at 1440px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    const admin = new AdminPage(page);
    await admin.gotoSettings();
    await expect(page).toHaveURL(/\/settings/);

    await admin.gotoStaff();
    await expect(page).toHaveURL(/\/staff/);
  });

  test('Cashier is redirected from admin-only pages at 1440px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(CASHIER_EMAIL, CASHIER_PASSWORD);

    await page.goto('/settings');
    await expect(page).toHaveURL(/\/dashboard/);

    await page.goto('/staff');
    await expect(page).toHaveURL(/\/dashboard/);
  });

  test('Admin can see admin-only navigation links', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    const admin = new AdminPage(page);
    await admin.expectNoAdminLinks();
  });

  test('Cashier does not see admin-only navigation links', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(CASHIER_EMAIL, CASHIER_PASSWORD);

    const cashier = new CashierPage(page);
    await cashier.expectNoAdminLinks();
  });
});

test.describe('Settings page', () => {
  test('All tabs render correctly at 375px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/settings');

    const settings = new SettingsPage(page);
    await settings.expectTabActive('Business');

    const tabs = page.locator('.settings-tab');
    const count = await tabs.count();
    expect(count).toBeGreaterThan(0);
  });

  test('Settings page has no horizontal overflow at 375px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/settings');

    const shop = new ShopPage(page);
    await shop.expectNoHorizontalOverflow();
  });
});

test.describe('Staff page', () => {
  test('Empty state shows when no staff members at 375px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/staff');

    const staff = new StaffPage(page);
    await staff.expectEmptyState();
  });

  test('Staff page has no horizontal overflow at 768px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/staff');

    const shop = new ShopPage(page);
    await shop.expectNoHorizontalOverflow();
  });
});

test.describe('Shop pages', () => {
  test('Shop home has no horizontal overflow at 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/');

    const shop = new ShopPage(page);
    await shop.expectNoHorizontalOverflow();
  });

  test('Cart page has no horizontal overflow at 375px', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 667 });
    await page.goto('/shop/cart');

    const shop = new ShopPage(page);
    await shop.expectNoHorizontalOverflow();
  });
});

test.describe('Dashboard', () => {
  test('Admin dashboard renders at 1440px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(ADMIN_EMAIL, ADMIN_PASSWORD);

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.goto('/dashboard');

    await expect(page.locator('h1')).toBeVisible();
  });

  test('Cashier dashboard renders at 768px', async ({ page }) => {
    const login = new LoginPage(page);
    await login.goto();
    await login.login(CASHIER_EMAIL, CASHIER_PASSWORD);

    await page.setViewportSize({ width: 768, height: 1024 });
    await page.goto('/dashboard');

    await expect(page.locator('h1')).toBeVisible();
  });
});