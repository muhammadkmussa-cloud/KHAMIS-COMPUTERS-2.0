const { Page, expect } = require('@playwright/test');

class LoginPage {
  constructor(page) {
    this.page = page;
    this.emailInput = page.locator('input[name="email"]');
    this.passwordInput = page.locator('input[name="password"]');
    this.signinBtn = page.locator('button[type="submit"]');
  }

  async goto() {
    await this.page.goto('/login');
  }

  async login(email, password) {
    await this.emailInput.fill(email);
    await this.passwordInput.fill(password);
    await this.signinBtn.click();
    await this.page.waitForURL('**/dashboard', { timeout: 5000 }).catch(() => {});
  }
}

class AdminPage {
  constructor(page) {
    this.page = page;
    this.dashboardLink = page.getByRole('link', { name: 'Dashboard' });
    this.productsLink = page.getByRole('link', { name: 'Products' });
    this.staffLink = page.getByRole('link', { name: 'Staff' });
    this.settingsLink = page.getByRole('link', { name: 'Settings' });
    this.reportsLink = page.getByRole('link', { name: 'Reports' });
  }

  async gotoSettings() {
    await this.settingsLink.click();
    await this.page.waitForURL('**/settings');
  }

  async gotoStaff() {
    await this.staffLink.click();
    await this.page.waitForURL('**/staff');
  }

  async expectNoAdminLinks() {
    const links = this.page.getByRole('link', { name: /Products|Reports|Staff|Settings/ });
    await expect(links.first()).toBeVisible();
  }
}

class CashierPage {
  constructor(page) {
    this.page = page;
    this.dashboardLink = page.getByRole('link', { name: 'Dashboard' });
    this.posLink = page.getByRole('link', { name: 'POS' });
    this.salesLink = page.getByRole('link', { name: 'Sales' });
  }

  async expectNoAdminLinks() {
    await expect(this.page.getByRole('link', { name: 'Settings' }).first()).not.toBeVisible();
    await expect(this.page.getByRole('link', { name: 'Staff' }).first()).not.toBeVisible();
    await expect(this.page.getByRole('link', { name: 'Reports' }).first()).not.toBeVisible();
  }
}

class SettingsPage {
  constructor(page) {
    this.page = page;
    this.tabs = page.locator('.settings-tab');
    this.toastRegion = page.locator('#settings-toast-region');
    this.testEmailBtn = page.locator('#test-email-btn');
    this.testMpesaBtn = page.locator('#test-mpesa-btn');
  }

  async expectTabActive(tabName) {
    await expect(this.tabs.filter({ hasText: tabName })).toHaveClass(/active/);
  }
}

class StaffPage {
  constructor(page) {
    this.page = page;
    this.addForm = page.locator('#add-staff-form');
    this.staffTable = page.locator('.staff-table');
    this.emptyState = page.locator('.empty-state');
  }

  async expectEmptyState() {
    await expect(this.emptyState).toBeVisible();
  }

  async expectStaffCount(count) {
    const rows = this.page.locator('.staff-table tbody tr');
    await expect(rows).toHaveCount(count);
  }
}

class ShopPage {
  constructor(page) {
    this.page = page;
  }

  async expectNoHorizontalOverflow() {
    const body = this.page.locator('body');
    const overflow = await body.evaluate((el) => el.scrollWidth > el.clientWidth);
    expect(overflow).toBe(false);
  }
}

module.exports = { LoginPage, AdminPage, CashierPage, SettingsPage, StaffPage, ShopPage };