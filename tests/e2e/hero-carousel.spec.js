const { test, expect } = require('@playwright/test');
const { LoginPage, ShopPage } = require('./pages');
const path = require('path');

const ADMIN = { email: 'admin@khamis.local', password: 'admin1234' };
const CASHIER = { email: 'cashier@khamis.local', password: 'cashier1234' };
const FIXTURE_IMAGE = path.join(__dirname, 'fixtures', 'hero.jpg');
const FIXTURE_BAD = path.join(__dirname, 'fixtures', 'not-image.txt');
const PREFIX = 'E2E-HERO-';
const PROJECTS = ['admin-desktop', 'admin-tablet', 'customer-mobile'];

async function loginAs(page, creds) {
  const login = new LoginPage(page);
  await login.goto();
  await login.login(creds.email, creds.password);
}

async function gotoHeroAdmin(page) {
  await page.goto('/settings?tab=online-shop');
  await expect(page.locator('.hero-manage-page')).toBeVisible();
}

function slideCard(page, headline) {
  return page.locator('.slide-card').filter({ has: page.locator('h3', { hasText: headline }) });
}

async function createSlide(page, opts) {
  const {
    headline,
    active = true,
    ctaType = 'shop',
    ctaTarget = '',
    ctaText = 'Shop now',
    displayOrder = 0,
  } = opts;
  await gotoHeroAdmin(page);
  const form = page.locator('#add-slide form');
  await form.locator('input[name="desktop_image"]').setInputFiles(FIXTURE_IMAGE);
  await form.locator('input[name="headline"]').fill(headline);
  await form.locator('input[name="eyebrow"]').fill('E2E');
  await form.locator('select[name="cta_type"]').selectOption(ctaType);
  if (ctaTarget !== '') {
    await form.locator('input[name="cta_target"]').fill(String(ctaTarget));
  }
  await form.locator('input[name="cta_text"]').fill(ctaText);
  await form.locator('input[name="display_order"]').fill(String(displayOrder));
  if (!active) {
    await form.locator('input[name="is_active"]').uncheck();
  }
  await form.locator('button[type="submit"]').click();
  await page.waitForURL(/settings\?tab=online-shop/);
}

async function deleteSlide(page, headline) {
  await gotoHeroAdmin(page);
  const card = slideCard(page, headline);
  if ((await card.count()) === 0) return;
  page.once('dialog', (d) => d.accept());
  await card.first().locator('button:has-text("Delete")').click();
  await page.waitForURL(/settings\?tab=online-shop/);
}

async function deleteAllTestSlides(page) {
  await gotoHeroAdmin(page);
  for (let i = 0; i < 40; i++) {
    const card = page
      .locator('.slide-card')
      .filter({ has: page.locator('h3', { hasText: PREFIX }) })
      .first();
    if ((await card.count()) === 0) break;
    page.once('dialog', (d) => d.accept());
    await card.locator('button:has-text("Delete")').click();
    await page.waitForURL(/settings\?tab=online-shop/);
  }
}

test.describe('Hero carousel', () => {
  test.beforeEach(async ({}, testInfo) => {
    test.skip(!PROJECTS.includes(testInfo.project.name), 'hero spec runs on desktop/tablet/mobile projects');
  });

  test.afterAll(async ({ browser }) => {
    const context = await browser.newContext();
    const page = await context.newPage();
    try {
      await loginAs(page, ADMIN);
      await deleteAllTestSlides(page);
    } catch (e) {
      // best-effort cleanup
    }
    await context.close();
  });

  test('storefront shows an active slide with live price and a working product CTA', async ({ page }) => {
    const headline = PREFIX + 'Product ' + Date.now();
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);
    await createSlide(page, { headline, ctaType: 'product', ctaTarget: '1', ctaText: 'Shop now' });

    await page.goto('/shop');
    await expect(page.locator('.hero-carousel')).toBeVisible();
    await expect(page.locator('.hero-headline').first()).toHaveText(headline);
    await expect(page.locator('.hero-slide.is-active .hero-price')).toBeVisible();
    const cta = page.locator('.hero-slide.is-active .hero-btn-primary');
    await expect(cta).toHaveAttribute('href', /\/shop\/product\/1$/);
    await cta.click();
    await expect(page).toHaveURL(/\/shop\/product\/1$/);

    await deleteSlide(page, headline);
  });

  test('inactive slides are not rendered and category CTAs resolve', async ({ page }) => {
    const activeHeadline = PREFIX + 'Active ' + Date.now();
    const hiddenHeadline = PREFIX + 'Hidden ' + Date.now();
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);
    await createSlide(page, { headline: activeHeadline, displayOrder: 1 });
    await createSlide(page, { headline: hiddenHeadline, displayOrder: 2, active: false });
    await createSlide(page, { headline: PREFIX + 'Category ' + Date.now(), ctaType: 'category', ctaTarget: '1', ctaText: 'Explore' });

    await page.goto('/shop');
    await expect(page.locator('.hero-headline', { hasText: activeHeadline })).toHaveCount(1);
    await expect(page.locator('.hero-headline', { hasText: hiddenHeadline })).toHaveCount(0);
    await expect(page.locator('.hero-slide.is-active .hero-btn-primary')).toHaveAttribute('href', /category=/);

    await deleteAllTestSlides(page);
  });

  test('slides render in display order', async ({ page }) => {
    const first = PREFIX + 'First ' + Date.now();
    const second = PREFIX + 'Second ' + Date.now();
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);
    await createSlide(page, { headline: first, displayOrder: 2 });
    await createSlide(page, { headline: second, displayOrder: 1 });

    await page.goto('/shop');
    const headlines = await page.locator('.hero-headline').allTextContents();
    const order = headlines.filter((t) => t === first || t === second);
    expect(order).toEqual([second, first]);

    await deleteAllTestSlides(page);
  });

  test('carousel controls navigate, pause and respond to the keyboard', async ({ page }) => {
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);
    await createSlide(page, { headline: PREFIX + 'Ctrl A ' + Date.now(), displayOrder: 1 });
    await createSlide(page, { headline: PREFIX + 'Ctrl B ' + Date.now(), displayOrder: 2 });

    await page.goto('/shop');
    const carousel = page.locator('.hero-carousel');
    await expect(carousel).toBeVisible();

    const activeIndex = () =>
      page.locator('.hero-slide').evaluateAll((els) => els.findIndex((el) => el.classList.contains('is-active')));
    const start = await activeIndex();

    if (await page.locator('.hero-next').isVisible()) {
      await page.locator('.hero-next').click();
      await expect.poll(activeIndex).not.toBe(start);
      const advanced = await activeIndex();
      await page.locator('.hero-prev').click();
      await expect.poll(activeIndex).toBe(start);
      await page.locator('.hero-dot').nth(advanced).click();
      await expect.poll(activeIndex).toBe(advanced);
    } else {
      await page.locator('.hero-dot').nth((start + 1) % 2).click();
      await expect.poll(activeIndex).not.toBe(start);
    }

    const pause = page.locator('[data-hero-pause]');
    await expect(pause).toHaveText('Pause');
    await pause.click();
    await expect(pause).toHaveText('Play');
    await expect(pause).toHaveAttribute('aria-pressed', 'true');

    await page.locator('.hero-dot').first().focus();
    const beforeKey = await activeIndex();
    await page.keyboard.press('ArrowRight');
    await expect.poll(activeIndex).not.toBe(beforeKey);

    await deleteAllTestSlides(page);
  });

  test('zero active slides fall back to the branded hero', async ({ page }) => {
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);
    await page.goto('/shop');
    await expect(page.locator('.hero-carousel')).toHaveCount(0);
    await expect(page.locator('.shop-hero')).toBeVisible();
    await expect(page.locator('h1')).toContainText('Technology you can buy with confidence.');
  });

  test('storefront hero has no horizontal overflow', async ({ page }) => {
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);
    await createSlide(page, { headline: PREFIX + 'Overflow ' + Date.now(), displayOrder: 1 });
    await page.goto('/shop');
    const shop = new ShopPage(page);
    await shop.expectNoHorizontalOverflow();
    await expect(page.locator('.hero-carousel')).toBeVisible();
    await deleteAllTestSlides(page);
  });

  test('admin can create, edit, disable and delete a slide', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'admin-desktop', 'admin CRUD runs once');
    const headline = PREFIX + 'Crud ' + Date.now();
    const edited = PREFIX + 'Crud edited ' + Date.now();
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);

    await createSlide(page, { headline, displayOrder: 3 });
    const card = slideCard(page, headline);
    await expect(card).toHaveCount(1);
    await expect(card.locator('.slide-status')).toHaveText('Active');

    await card.locator('[data-edit-toggle]').click();
    const editForm = card.locator('.slide-edit form');
    await expect(editForm).toBeVisible();
    await editForm.locator('input[name="headline"]').fill(edited);
    await editForm.locator('button[type="submit"]').click();
    await page.waitForURL(/settings\?tab=online-shop/);
    await expect(slideCard(page, edited)).toHaveCount(1);

    const editedCard = slideCard(page, edited);
    await editedCard.locator('button:has-text("Disable")').click();
    await page.waitForURL(/settings\?tab=online-shop/);
    await expect(slideCard(page, edited).locator('.slide-status')).toHaveText('Disabled');

    await deleteSlide(page, edited);
    await expect(slideCard(page, edited)).toHaveCount(0);
  });

  test('invalid image uploads are rejected', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'admin-desktop', 'upload validation runs once');
    const headline = PREFIX + 'Bad ' + Date.now();
    await loginAs(page, ADMIN);
    await deleteAllTestSlides(page);
    await gotoHeroAdmin(page);
    const form = page.locator('#add-slide form');
    await form.locator('input[name="desktop_image"]').setInputFiles(FIXTURE_BAD);
    await form.locator('input[name="headline"]').fill(headline);
    await form.locator('button[type="submit"]').click();
    await page.waitForURL(/settings\?tab=online-shop/);
    await expect(slideCard(page, headline)).toHaveCount(0);
  });

  test('cashiers cannot access the hero manager', async ({ page }, testInfo) => {
    test.skip(testInfo.project.name !== 'admin-desktop', 'RBAC runs once');
    await loginAs(page, CASHIER);
    const response = await page.goto('/settings?tab=online-shop');
    expect(response.status()).toBe(403);
  });
});
