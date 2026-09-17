<?php
declare(strict_types=1);

/**
 * Khamis Computers — front controller.
 * All requests are routed here via .htaccess (or router-dev.php locally).
 */

require dirname(__DIR__) . '/app/bootstrap.php';

// Onboarding: if the database has no tables, show the installer instead of the app.
if (PHP_SAPI !== 'cli' && Router::currentPath() !== 'install' && !Schema::isInstalled()) {
    redirect('install');
}

$router = new Router();

/* ---- Auth ---- */
$router->get('login', [AuthController::class, 'showLogin']);
$router->post('login', [AuthController::class, 'login']);
$router->post('logout', [AuthController::class, 'logout']); // POST + CSRF (no GET logout)

/* ---- Installer (remove in production!) ---- */
$router->get('install', [SetupController::class, 'form']);
$router->post('install', [SetupController::class, 'run']);

/* ---- Dashboard ---- */
$router->get('', [DashboardController::class, 'index'], ['auth' => true]);
$router->get('dashboard', [DashboardController::class, 'index'], ['auth' => true]);

/* ---- Inventory: products (create/edit/stock = admin; viewing = any staff) ---- */
$router->get('products', [ProductController::class, 'index'], ['auth' => true]);
$router->get('products/export', [ProductController::class, 'export'], ['auth' => true, 'admin' => true]);
$router->get('products/barcode/generate', [ProductController::class, 'generateBarcode'], ['auth' => true, 'admin' => true]);
$router->get('products/check-unique', [ProductController::class, 'checkUnique'], ['auth' => true, 'admin' => true]);
$router->get('products/new', [ProductController::class, 'create'], ['auth' => true, 'admin' => true]);
$router->post('products', [ProductController::class, 'store'], ['auth' => true, 'admin' => true]);
$router->get('products/{id}', [ProductController::class, 'show'], ['auth' => true]);
$router->get('products/{id}/edit', [ProductController::class, 'edit'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}', [ProductController::class, 'update'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/delete', [ProductController::class, 'delete'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/units', [ProductController::class, 'addUnits'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/units/bulk', [ProductController::class, 'bulkUnits'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/units/{unitId}/status', [ProductController::class, 'setUnitStatus'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/units/{unitId}/delete', [ProductController::class, 'deleteUnit'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/adjust', [ProductController::class, 'adjust'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/images', [ProductController::class, 'uploadImages'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/images/{imageId}/update', [ProductController::class, 'updateImage'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/images/{imageId}/primary', [ProductController::class, 'setPrimaryImage'], ['auth' => true, 'admin' => true]);
$router->post('products/{id}/images/{imageId}/delete', [ProductController::class, 'deleteImage'], ['auth' => true, 'admin' => true]);
$router->get('products/{id}/labels', [ProductController::class, 'labels'], ['auth' => true, 'admin' => true]);
$router->get('uploads/p/{filename}', [ProductController::class, 'image']); // public (shop)
$router->get('uploads/h/{filename}', [HeroSlideController::class, 'image']); // public (shop)

/* ---- Inventory: categories & brands (admin only) ---- */
$router->get('categories', [CategoryController::class, 'index'], ['auth' => true, 'admin' => true]);
$router->post('categories', [CategoryController::class, 'store'], ['auth' => true, 'admin' => true]);
$router->post('categories/{id}', [CategoryController::class, 'update'], ['auth' => true, 'admin' => true]);
$router->post('categories/{id}/delete', [CategoryController::class, 'delete'], ['auth' => true, 'admin' => true]);

$router->get('brands', [BrandController::class, 'index'], ['auth' => true, 'admin' => true]);
$router->post('brands', [BrandController::class, 'store'], ['auth' => true, 'admin' => true]);
$router->post('brands/{id}', [BrandController::class, 'update'], ['auth' => true, 'admin' => true]);
$router->post('brands/{id}/delete', [BrandController::class, 'delete'], ['auth' => true, 'admin' => true]);

$router->get('suppliers', [SupplierController::class, 'index'], ['auth' => true, 'admin' => true]);
$router->post('suppliers', [SupplierController::class, 'store'], ['auth' => true, 'admin' => true]);
$router->post('suppliers/{id}', [SupplierController::class, 'update'], ['auth' => true, 'admin' => true]);
$router->post('suppliers/{id}/status', [SupplierController::class, 'setStatus'], ['auth' => true, 'admin' => true]);
$router->post('suppliers/{id}/delete', [SupplierController::class, 'delete'], ['auth' => true, 'admin' => true]);
$router->post('delivery-zones', [DeliveryZoneController::class, 'store'], ['auth' => true, 'admin' => true]);
$router->post('delivery-zones/{id}', [DeliveryZoneController::class, 'update'], ['auth' => true, 'admin' => true]);
$router->post('delivery-zones/{id}/delete', [DeliveryZoneController::class, 'delete'], ['auth' => true, 'admin' => true]);

/* ---- Inventory: goods received (GRN) — admin only (stock-in) ---- */
$router->get('grn', [GrnController::class, 'index'], ['auth' => true, 'admin' => true]);
$router->get('grn/new', [GrnController::class, 'create'], ['auth' => true, 'admin' => true]);
$router->get('grn/export', [GrnController::class, 'export'], ['auth' => true, 'admin' => true]);
$router->post('grn/review', [GrnController::class, 'review'], ['auth' => true, 'admin' => true]);
$router->post('grn', [GrnController::class, 'store'], ['auth' => true, 'admin' => true]);
$router->get('grn/{id}', [GrnController::class, 'show'], ['auth' => true, 'admin' => true]);

/* ---- POS terminal ---- */
$router->get('pos', [PosController::class, 'index'], ['auth' => true]);
$router->get('pos/search', [PosController::class, 'search'], ['auth' => true]);
$router->get('pos/catalog', [PosController::class, 'catalog'], ['auth' => true]);
$router->post('pos/checkout', [PosController::class, 'checkout'], ['auth' => true]);
$router->post('pos/sync', [PosController::class, 'sync']); // auth handled in-controller (JSON 401)
$router->get('pos/receipt/{id}', [PosController::class, 'receipt'], ['auth' => true]);

/* ---- Online shop (public) ---- */
$router->get('shop', [ShopController::class, 'home']);
$router->get('shop/products', [ShopController::class, 'browse']);
$router->get('shop/product/{id}', [ShopController::class, 'product']);
$router->get('shop/cart', [ShopController::class, 'cart']);
$router->get('shop/api/cart', [ShopController::class, 'apiCart']);
$router->post('shop/checkout', [ShopController::class, 'checkout']);
$router->post('shop/whatsapp-enquiry', [ShopController::class, 'whatsappEnquiry']); // lightweight lead tracking
$router->get('shop/order/{id}', [ShopController::class, 'order']);
$router->get('shop/api/order-status/{id}', [ShopController::class, 'orderStatus']);
$router->get('shop/track', [ShopController::class, 'track']);
$router->post('shop/track', [ShopController::class, 'trackLookup']);
$router->post('mpesa/callback', [ShopController::class, 'mpesaCallback']); // public — called by Safaricom

/* ---- Sales, returns, orders ---- */
$router->get('sales', [SaleController::class, 'index'], ['auth' => true]);
$router->get('sales/export', [SaleController::class, 'export'], ['auth' => true, 'admin' => true]);
$router->get('sales/{id}', [SaleController::class, 'show'], ['auth' => true]);
$router->get('sales/{id}/pdf', [SaleController::class, 'pdf'], ['auth' => true]);
$router->get('sales/{id}/print', [SaleController::class, 'printThermal'], ['auth' => true]);
$router->post('sales/{id}/void', [SaleController::class, 'void'], ['auth' => true, 'admin' => true]);
$router->post('sales/{id}/mpesa-mark-paid', [SaleController::class, 'mpesaMarkPaid'], ['auth' => true, 'admin' => true]);
$router->post('sales/{id}/mpesa-retry', [SaleController::class, 'mpesaRetry'], ['auth' => true, 'admin' => true]);

$router->get('returns', [ReturnController::class, 'index'], ['auth' => true]);
$router->get('returns/new', [ReturnController::class, 'create'], ['auth' => true]);
$router->post('returns/review', [ReturnController::class, 'review'], ['auth' => true]);
$router->post('returns', [ReturnController::class, 'store'], ['auth' => true]);
$router->get('returns/{id}', [ReturnController::class, 'show'], ['auth' => true]);
$router->post('returns/{id}/approve', [ReturnController::class, 'approve'], ['auth' => true, 'admin' => true]);
$router->post('returns/{id}/reject', [ReturnController::class, 'reject'], ['auth' => true, 'admin' => true]);

/* ---- Expenses ---- */
$router->get('expenses', [ExpenseController::class, 'index'], ['auth' => true]);
$router->post('expenses', [ExpenseController::class, 'store'], ['auth' => true]);
$router->get('expenses/{id}/receipt', [ExpenseController::class, 'receipt'], ['auth' => true]);
$router->post('expenses/{id}/delete', [ExpenseController::class, 'delete'], ['auth' => true]);

/* ---- Reports (admins) ---- */
$router->get('reports', [ReportController::class, 'index'], ['auth' => true, 'admin' => true]);
$router->get('reports/export', [ReportController::class, 'export'], ['auth' => true, 'admin' => true]);
$router->get('reports/z', [ReportController::class, 'zReport'], ['auth' => true]);
$router->post('reports/z/close', [ReportController::class, 'closeDay'], ['auth' => true]);
$router->get('reports/vat', [ReportController::class, 'vatReport'], ['auth' => true, 'admin' => true]);
$router->get('reports/vat/export', [ReportController::class, 'vatExport'], ['auth' => true, 'admin' => true]);
$router->get('reports/purchases', [ReportController::class, 'purchases'], ['auth' => true, 'admin' => true]);
$router->get('reports/purchases/export', [ReportController::class, 'purchasesExport'], ['auth' => true, 'admin' => true]);

/* ---- Settings (admins) ---- */
$router->get('settings', [SettingsController::class, 'index'], ['auth' => true, 'admin' => true]);
$router->post('settings', [SettingsController::class, 'update'], ['auth' => true, 'admin' => true]);
$router->post('settings/test-email', [SettingsController::class, 'testEmail'], ['auth' => true, 'admin' => true]);
$router->post('settings/test-mpesa', [SettingsController::class, 'testMpesa'], ['auth' => true, 'admin' => true]);
$router->post('settings/hero', [HeroSlideController::class, 'create'], ['auth' => true, 'admin' => true]);
$router->post('settings/hero/{id}/edit', [HeroSlideController::class, 'edit'], ['auth' => true, 'admin' => true]);
$router->post('settings/hero/{id}/delete', [HeroSlideController::class, 'delete'], ['auth' => true, 'admin' => true]);
$router->post('settings/hero/{id}/toggle', [HeroSlideController::class, 'toggle'], ['auth' => true, 'admin' => true]);
$router->post('settings/hero/reorder', [HeroSlideController::class, 'reorder'], ['auth' => true, 'admin' => true]);

/* ---- Staff management (admins) ---- */
$router->get('staff', [StaffController::class, 'index'], ['auth' => true, 'admin' => true]);
$router->post('staff', [StaffController::class, 'store'], ['auth' => true, 'admin' => true]);
$router->post('staff/{id}', [StaffController::class, 'update'], ['auth' => true, 'admin' => true]);
$router->post('staff/{id}/password', [StaffController::class, 'password'], ['auth' => true, 'admin' => true]);
$router->post('staff/{id}/delete', [StaffController::class, 'delete'], ['auth' => true, 'admin' => true]);

$router->dispatch();
