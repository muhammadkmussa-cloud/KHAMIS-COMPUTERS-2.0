# Khamis Computers Frontend and Workflow Modernization Plan

## Objective

Turn the current feature-complete POS and online shop into a polished retail operations product that feels trustworthy, fast, coherent, and ready for a high-value business. This document is the implementation plan only. It does not change application behavior.

## Audit evidence

The audit used Playwright MCP against the local SQLite application with seeded admin and cashier accounts.

- 47 distinct rendered screen states inspected across public, admin, and cashier roles.
- 96 screenshots captured at 1440×1000 and 375×812, including POS search and serial-selection states.
- Admin and cashier login succeeded; no uncaught page JavaScript errors were found.
- POS search returned eight products and opened the serial-number chooser correctly.
- 13 screen states overflow the 375px viewport.
- The reports overview overflows even at the desktop viewport by a small amount.
- 26 screen states contain controls without explicit programmatic labels; 160 control instances were detected.
- 46 of 47 screen states contain at least one visible target smaller than 40px in one dimension.
- The only expected failed HTTP response was the deliberately tested 404 route. A separate missing-resource console error should be resolved by adding the correct favicon/application icon.

### Highest-impact problems

1. Mobile navigation disappears instead of becoming a usable menu. Cashiers and admins lose clear access to major areas.
2. Tables are converted to anonymous vertical blocks on mobile. Values lose their column meaning and several pages still overflow.
3. The dashboard shows build-roadmap and development information instead of operational decisions and tasks.
4. Product detail, sales detail, reports, labels, GRN creation, and the filled cart overflow on mobile.
5. Forms use nearby `span` elements as visual labels rather than associated `label` elements, weakening accessibility and click behavior.
6. Destructive and consequential actions use native browser confirmation dialogs and provide limited preview of impact.
7. Settings is one very long mixed-purpose form containing identity, tax, email, SMTP, M-PESA, and delivery configuration.
8. Cashier inventory screens expose navigation links to admin-only categories, brands, suppliers, and goods-received pages.
9. The design is visually clean but too uniform. Important states, decisions, and next actions have weak hierarchy.
10. Loading, saving, success, error, retry, empty, and no-results states are inconsistent across workflows.

## Product direction

The target is a premium retail operations suite with three clearly differentiated modes:

- **Back office:** calm, information-dense, task-oriented, with strong status and exception handling.
- **POS:** fast, keyboard and scanner first, optimized for a cashier under pressure.
- **Online shop:** warmer and more product-led, with stronger imagery, trust signals, and a short checkout path.

The visual system will use the existing blue brand color, restrained neutral surfaces, clearer typography hierarchy, consistent 8px spacing increments, 12–16px radii, subtle borders, and shadows reserved for raised or actionable surfaces. Pills will be limited to statuses, compact filters, and tags. Buttons and form controls will use conventional rounded rectangles for clearer hierarchy.

## Shared foundation changes

### Application shell and navigation

- Replace the desktop-only horizontal navigation behavior with a responsive shell.
- Desktop: grouped primary navigation, clear active parent for subroutes, and an account menu.
- Tablet/mobile: fixed header with a menu button and a role-aware navigation drawer.
- Group Inventory and Reports into labeled sections while preserving one-click access to POS.
- Hide admin-only inventory tabs from cashiers.
- Add breadcrumbs on detail and edit screens; keep the parent area highlighted for nested routes.
- Keep POS and Shop visually distinct so staff always know which mode they are using.

### Page structure

- Standardize page headers: title, short purpose, status/context, primary action, secondary actions.
- Use one `h1` on every screen. Several create/edit, POS, and receipt screens currently have no `h1`.
- Introduce reusable section headers, KPI cards, filter bars, data cards, empty states, and action menus.
- Replace inline styles with named component classes so responsive behavior is predictable.

### Forms

- Associate every field with a real `label` and stable `id`.
- Mark required and optional fields consistently and show field-level validation beside the field.
- Preserve submitted values and scroll/focus to the first error.
- Add saving states, disable duplicate submission, and show persistent success confirmation.
- Use input masks and examples for Kenyan phone numbers, money, dates, serials, and M-PESA references.
- Move advanced or integration fields behind clearly named sections.

### Tables and responsive data

- Keep true tables on desktop with sticky headers where lists are long.
- On mobile, render purpose-built cards with visible labels instead of hiding the table header and stacking anonymous cells.
- Move row actions into a consistent overflow menu where more than two actions exist.
- Add sorting, active filter chips, result counts, pagination context, and useful zero states.
- Keep important identifiers, statuses, totals, and primary actions visible without horizontal scrolling.

### Feedback and safety

- Replace native `alert()` and `confirm()` with branded dialogs that state the object, consequence, and recovery behavior.
- Require typed or explicit confirmation only for irreversible or high-impact operations.
- Add toast notifications for lightweight successes and inline banners for errors requiring action.
- Add skeleton/loading states for POS search, cart loading, reports, and remote payment status.
- Add retry actions for failed network operations and clear offline/queued/synced states.
- Standardize status language and colors across sales, payments, returns, stock units, staff, and orders.

### Accessibility and input ergonomics

- Target WCAG 2.2 AA color contrast and visible keyboard focus.
- Make primary touch targets at least 44×44px and compact desktop targets at least 40px high.
- Ensure dialogs trap focus, close with Escape, restore focus, and expose correct ARIA roles.
- Add skip navigation, landmarks, table captions where useful, and live regions for cart/POS updates.
- Preserve scanner and keyboard shortcuts in POS while adding visible shortcut hints.

## Screen-by-screen plan

### Authentication and global states

| Screen | Planned changes |
| --- | --- |
| Staff login | Add a focused brand panel, show/hide password, caps-lock warning, inline errors, loading state, and a production-safe recovery/help route. Remove demo credentials automatically outside development and add an application icon. |
| 404/error pages | Use the correct staff or shop shell, explain what happened in plain language, preserve context, and offer relevant destinations rather than one generic back button. |
| Installer | Present a guided readiness checklist, database connection status, password requirements, install progress, and a clear locked/installed state. Keep it visually separate from daily operations. |
| Global header | Add responsive menu, parent-route highlighting, account menu, role label, keyboard focus, and role-aware destinations. Remove crowded action duplication on narrow screens. |

### Dashboard

| Screen | Planned changes |
| --- | --- |
| Admin dashboard | Remove the build roadmap and raw system card. Replace them with today’s revenue versus prior period, transactions, gross profit, cash expected, pending returns, pending online payments, low-stock exceptions, and recent activity. Add direct actions for POS, receive stock, add product, and close day. |
| Cashier dashboard | Show shift-focused content: open POS, today’s personal sales, expected cash, queued offline sales, pending returns requiring handoff, and last transactions. Hide admin financial and system configuration detail. |

### Inventory and catalogue management

| Screen | Planned changes |
| --- | --- |
| Inventory list | Replace the oversized low-stock table with a compact exception banner and filter. Add stock/status/category filters, sortable columns, clearer product thumbnails, mobile product cards, and a primary `Add product` action. Group import/export and taxonomy actions under secondary menus. |
| Product create | Use sections for Identity, Pricing, Inventory policy, Warranty, and Shop visibility. Show inclusive VAT price live, validate SKU/barcode uniqueness before submit, explain serialized-stock consequences, and use a sticky save bar. |
| Product edit | Match create flow, show unsaved-changes protection, surface product status/history constraints before toggles, and provide a direct preview of the public product page. |
| Product detail | Split Overview, Images, Stock/Serials, Movements, and Labels into tabs. Keep KPIs compact. On mobile, replace the serial table with labeled unit cards. Add search/filter for serials and bulk operations with explicit selection. |
| Product images | Add drag/drop upload, progress, size/type errors, thumbnail reordering, primary-image selection, alt text, and a public-shop preview. |
| Shelf/serial labels | Treat this as a print workspace: clear preview controls, paper preset, quantity, serial/shelf mode, printer guidance, responsive preview scaling, and no forced horizontal page overflow. |
| Categories | Replace always-open inline edit forms with a compact list and edit drawer/modal. Show product count, active status, drag/sort controls, and clear consequences for delete/deactivate. |
| Brands | Use the same management pattern as Categories for consistency. Add logo support only if it will be used in the shop; otherwise keep the screen deliberately simple. |
| Suppliers | Separate Add supplier from supplier records. Use expandable detail or a drawer for editing, show delivery history and last GRN, and distinguish deactivate from delete. Add search and active/inactive filters. |

### Goods receiving

| Screen | Planned changes |
| --- | --- |
| GRN list | Add supplier/date/reference filters, total purchase amount, item count, and status. Make each record clearly openable and offer CSV/print only as secondary actions. |
| Receive stock | Turn the page into a guided receiving workspace: supplier and reference first, then a responsive line-item table. Add searchable product picker, barcode input, inline quantity/cost validation, serial count matching, duplicate detection, running totals, and a review step before committing stock. |
| GRN detail | Add a polished receipt/delivery document layout with supplier identity, receiver, cost totals, item/serial detail, print/download actions, and links back to affected products. |

### Point of Sale

| Screen/state | Planned changes |
| --- | --- |
| POS empty state | Replace the small hint card in a large empty canvas with popular/recent products, category shortcuts, scanner status, keyboard shortcuts, and recent held/queued activity. |
| POS search/results | Make results visually scannable with thumbnail, stock, price, SKU, and serialized badge. Highlight exact barcode/serial matches and show loading/no-result/retry states. Keep Enter-to-add and scanner behavior. |
| Serial picker | Show selected count, warranty/status, search within serials, unavailable reasons, and a clear `Add selected units` action. Make it keyboard accessible. |
| Cart | Increase line clarity, make quantity adjustment obvious, show serials inline, support item-level discount only if business rules permit, and protect against accidental clear. Persist the active cart locally. |
| Customer/payment | Use progressive disclosure: customer is optional, payment fields appear only when relevant, cash change is prominent, references are validated, and manager approval appears as a focused authorization step. |
| Checkout | Add a final compact review, submission lock, progress state, clear success transition, and recovery for server/network errors. Avoid duplicate sales. |
| Offline mode | Show explicit `Offline`, `Saved on this device`, `Syncing`, `Needs attention`, and `Synced` states with a queue drawer. Allow retry and conflict resolution without obscuring the current sale. |
| POS receipt | Use a responsive receipt layout with a dominant `New sale` action, print/download choices, customer/order context, and no mobile overflow. |

### Sales and after-sale service

| Screen | Planned changes |
| --- | --- |
| Sales list | Condense filters into one responsive filter bar/drawer, show active filter chips, preserve filters across navigation, and use labeled mobile order cards. Move `Void` out of every row into a detail/action menu. |
| Sale detail | Use a two-column desktop summary and stacked mobile cards. Keep order identity/status sticky, group customer/payment/fulfilment metadata, and make totals readable. Move PDF/print/return actions into a clear hierarchy. |
| Void sale | Replace browser confirm with an impact dialog showing sale total, stock restoration, payment implications, reason input, and final confirmation. Record and display who voided it and why. |
| Pending M-PESA sale | Present payment timeline, last attempt, failure reason, safe retry, manual mark-paid controls, and clear stock reservation status. |
| PDF and thermal print | Keep documents print-first, add print preview guidance, ensure 58mm/80mm presets are explicit, and test long names, multiple serials, discounts, and delivery fees. |

### Returns

| Screen | Planned changes |
| --- | --- |
| Returns list | Add status/date/customer filters, summary counts, clearer refund totals, and separate `Pending approval` attention from completed history. Use mobile return cards. |
| Start return | Replace the plain recent-sales table with order/receipt/phone search. After selecting a sale, use a stepper: Select sale → Select items → Refund and reason → Review. |
| Return item selection | Make the checkbox, quantity, maximum returnable amount, serial, sold price, and refund amount one coherent row/card. Recalculate refund totals live and prevent invalid refund amounts inline. |
| Return detail | Add a status timeline, original sale link, reason/evidence area, refund method, audit trail, and clear stock outcome. |
| Approve/reject | Use dedicated dialogs requiring a decision note. State whether serialized units return to stock, become damaged, or remain unavailable; show the financial effect before confirming. |

### Expenses and close of day

| Screen | Planned changes |
| --- | --- |
| Expenses | Separate the create action into a modal/drawer so the list remains primary. Add receipt attachment, recurring/category presets, date filters, period total, creator, and a reasoned delete flow. Use labeled mobile cards. |
| Z-report | Build a shift-closing checklist: sales complete, offline queue clear, pending payments reviewed, cash counted, variance calculated, notes entered, then close. Separate expected cash from counted cash and record variance. |
| Closed Z-report history | Provide immutable snapshots, cashier/date filters, printable close report, variance highlighting, and a clear distinction between live and closed days. |

### Reporting

| Screen | Planned changes |
| --- | --- |
| Reports overview | Fix desktop and mobile overflow. Replace the 30 thin daily bars with responsive aggregation and tooltips. Add period presets, comparison values, net margin, channel mix, top products, and actionable low-stock links. |
| VAT report | Put summary and assumptions first, then collapsible daily/detail sections. Make the long data tables responsive with sticky columns or mobile cards. Clearly distinguish output VAT, returns credit, input VAT, and net payable/credit. |
| Purchases report | Add supplier/date presets, spend trend, supplier share, delivery count, last delivery, and drill-down without overflowing. Keep export aligned with selected filters. |
| Report navigation | Keep Reports active for all report subroutes and provide one consistent tab/dropdown pattern across desktop and mobile. |

### Settings and staff administration

| Screen | Planned changes |
| --- | --- |
| Settings | Split into tabs/sections: Business profile, Tax & receipts, Discounts, Notifications, M-PESA, Delivery, and Deployment. Add sticky save status and unsaved-change warning. Keep secrets masked and add `Test email`/`Test M-PESA configuration` actions with safe feedback. |
| Delivery zones | Move the inline table into a responsive list with an edit drawer. Show active state, fee, order, affected checkout availability, and a clear deactivate/delete distinction. |
| Production checklist | Show only in development or a dedicated system-health screen, not as part of normal business settings. Link to actionable health checks. |
| Staff | Separate Add staff into a modal/drawer. Show each employee as a clear record with role, status, last login, and actions. Replace auto-submit status toggles and inline password boxes with explicit dialogs. Add audit history and explain permission differences before role changes. |

### Online shop

| Screen | Planned changes |
| --- | --- |
| Shop home | Add real product photography priority, trust signals, store location/contact, delivery/pickup promise, featured categories, and stronger hierarchy. Make category navigation a mobile menu instead of an off-screen horizontal strip. |
| Product browse | Label search/sort/filter controls, add a mobile filter drawer, active filter chips, result count, useful no-results recovery, consistent card heights, and pagination/load-more behavior for growth. |
| Product detail | Improve image gallery, specifications, stock urgency, warranty, delivery estimate, pickup availability, quantity selection, and a sticky mobile add-to-cart bar. |
| Cart empty | Do not keep hidden checkout controls in the accessibility tree. Show recommendations or recently viewed products and a strong continue-shopping action. |
| Cart filled | Fix 467px mobile overflow. Reflow each line into a two-row card, increase remove/quantity target sizes, show price/VAT consistently, and keep the order summary sticky only on larger screens. |
| Checkout | Use a short staged flow: Contact → Fulfilment → Payment → Review. Validate phone and delivery zone inline, explain M-PESA timing, preserve the cart on failure, and show submission progress. |
| Order confirmation | Ensure confirmation can be revisited securely. Show order number, status timeline, pickup/delivery instructions, payment state, support contact, and print/share actions. The direct test route currently redirects because access depends on session state. |
| M-PESA pending | Use a live status panel with countdown/retry guidance, safe polling, explicit cancelled/failed paths, and a route back to the cart when payment cannot complete. |
| Track order | Label fields correctly, normalize Kenyan phone input, retain values on error, show a non-revealing not-found state, and present results as a status timeline with fulfilment details. |
| Shop footer | Add real contact/location/hours, policies, warranty/returns information, and consistent navigation. |

## Workflow redesign

### 1. Staff sign-in and role routing

`Sign in → role-specific dashboard → clear primary task → secure sign out`

- Route cashiers toward POS and shift information.
- Route admins toward business exceptions and management actions.
- Keep unauthorized destinations out of navigation; preserve server-side authorization.

### 2. Product setup and first stock

`Create identity → set price/tax → choose stock model → save → add images → receive/add stock → preview shop listing`

- Explain serialized versus quantity stock before creation.
- Offer a guided next step after saving instead of ending on a generic detail page.
- Keep labels and public preview available from the completed workflow.

### 3. Goods receiving

`Choose supplier → add/search products → enter quantities/costs → enter exact serials → validate → review totals → commit → GRN confirmation`

- No stock changes occur until the review step is confirmed.
- Duplicate serials, quantity mismatches, and unknown products are resolved before submit.

### 4. POS sale

`Scan/search → select exact unit → review cart → identify customer if needed → choose payment → authorize discount if needed → complete → receipt/new sale`

- Optimize for scanner and keyboard use.
- Keep the cashier informed of stock, connectivity, queue, and payment state.
- Protect against accidental duplicate completion and cart loss.

### 5. Offline POS and synchronization

`Connection lost → catalogue remains available → sale saved locally → receipt marked pending → queue visible → automatic/manual sync → conflict resolution → confirmed sync`

- A queued sale must never look identical to a server-confirmed sale.
- Conflicts must name the affected line and provide a recovery action.

### 6. Online purchase

`Discover → product detail → cart → contact → pickup/delivery → payment → review → confirmation/tracking`

- Keep VAT and delivery costs understandable throughout.
- Preserve cart and entered details after recoverable failures.
- Make M-PESA pending, success, timeout, and cancellation distinct.

### 7. Return and refund

`Find sale → select returnable items → set quantity/refund/reason → review → create pending return → admin decision → stock/refund outcome`

- Every step displays the remaining returnable quantity and financial effect.
- Approval explicitly chooses the stock condition for returned serialized units.

### 8. End-of-day close

`Review shift → resolve queued/pending transactions → count cash → compare expected vs counted → explain variance → close and print`

- Closing creates an immutable snapshot with cashier, timestamp, notes, and variance.

### 9. Staff administration

`Invite/create staff → assign role → first sign-in/password change → activity → role/status changes → deactivate/delete`

- Replace inline edits with explicit account actions and audit feedback.
- Explain immediate access consequences before deactivation or role changes.

### 10. Settings and integrations

`Open one settings area → edit → validate/test → save → confirmation/audit record`

- Save sections independently where practical so one invalid integration field does not block unrelated business details.
- Never expose stored secrets back to the browser.

## Delivery order

### Implementation progress

- [x] Phase 1, Step 1 — Shared design tokens, reusable UI components, and responsive shells. Playwright verified; reviewer approved.
- [x] Phase 1, Step 2 — Mobile navigation and role-aware Inventory/Reports navigation. Playwright verified for shop, admin, and cashier roles; reviewer findings resolved and approved.
- [x] Phase 1, Step 3 — Mobile tables now render as labeled data cards, and measured phone/tablet overflow failures are resolved. Playwright verified; reviewer findings resolved and approved.
- [x] Phase 1, Step 4 — All rendered form controls now have programmatic labels, with shared inline validation and first-error focus. Playwright verified across public/admin/cashier roles; reviewer findings resolved and approved.
- [x] Phase 1, Step 5 — Native browser dialogs are replaced by accessible branded confirmations and live-region toasts; POST forms expose loading state and reject duplicate submissions without losing named submit actions. Playwright verified cancel/confirm/focus/mobile and exact POST serialization; reviewer finding resolved and approved.
- [x] Phase 2, Step 1 — The cashier POS now supports quick picks and categories, explicit search/loading/retry states, exact cached serial scans, searchable unit selection, persistent carts, progressive payment validation, final review, idempotent checkout, an actionable offline queue, and a responsive receipt handoff. Playwright and model integration tests passed; reviewer findings resolved and approved.
- [x] Phase 2, Step 2 — The online shop now has product-led discovery, labelled catalogue filtering, stronger product availability and warranty details, responsive empty/filled carts, a four-stage checkout, protected idempotent retries, verified M-PESA callbacks with pending/failure recovery, and secure order confirmation/tracking. Playwright checks and 24 integration assertions passed; reviewer payment/security findings were resolved and approved.
- [x] Phase 2, Step 3 — Sales now has compact responsive filtering, active filter chips, professional order detail and payment-recovery workspaces, audited void confirmations, race-safe stock restoration and M-PESA finalization, plus PDF and explicit 58/80mm receipt previews. Desktop/mobile Playwright checks, 38 integration assertions, and 68 route/role checks passed; reviewer race-condition findings were resolved and approved.
- [x] Phase 3, Step 1 — Inventory now has responsive catalogue filtering and KPIs, structured product create/edit, live identity and VAT feedback, tabbed detail, image metadata and ordering, searchable and bulk-managed serials, audited stock changes, and VAT-inclusive A4/thermal label previews. Desktop/mobile Playwright checks, 43 integration assertions, and 72 route/role/body checks passed; reviewer findings were resolved and approved.
- [x] Phase 3, Step 2 — Suppliers now have a searchable active/inactive directory, expandable editing and delivery history, plus explicit deactivate/delete behavior. Goods receiving now uses a guided, responsive workflow with product lookup, exact quantity/serial validation, running totals, a server-side review gate before stock mutation, filtered receipt history and CSV export, and polished printable receipt details. Desktop/mobile Playwright checks, 55 integration assertions, and 77 route/role/body checks passed; reviewer findings were resolved and approved.
- [x] Phase 3, Step 3 — Returns now have searchable status/date/customer history, operational summaries, receipt and phone sale lookup, responsive item selection with live refund limits, a server-side review gate, explicit refund context, decision timelines, required approval/rejection notes, and per-item restock/damaged/unavailable outcomes. Desktop/tablet/mobile Playwright checks, 65 integration assertions, 84 route/body checks, and 15 RBAC checks passed; reviewer findings were resolved and approved.
- [x] Phase 4, Step 1 — Admin and cashier dashboards, audited expenses with receipt attachments, immutable close-of-day reconciliation, VAT and supplier-purchase analysis, and the reports overview now use responsive professional workflows. Net-sales and cost snapshots keep profit metrics VAT-exclusive, VAT rows reconcile returns, and filtered charts match their detail. Desktop/tablet/mobile Playwright checks, 76 integration assertions, 89 route/body checks, and 15 RBAC checks passed; all reviewer findings were resolved and approved.
- [x] Phase 4, Step 2 — Settings now validates configuration before writes, preserves sensitive credentials when fields are blank, exposes the tokenized M-PESA callback URL, supports safe delivery-zone activation, and provides responsive labeled staff management with explicit account-state safeguards. M-PESA charges and retry failures are reconciled safely, sales filters are accessible, and the schema snapshots unit cost for margin reporting. Desktop/tablet/mobile Playwright checks, 81 integration assertions, 89 route/body checks, and 15 RBAC checks passed; all reviewer findings were resolved and approved.

### Phase 1 — Foundations and blockers

1. Build shared tokens/components and responsive application shells.
2. Add mobile navigation and role-aware inventory/report navigation.
3. Replace mobile table behavior and fix all 13 overflow failures plus desktop reports overflow.
4. Convert all visible field captions to associated labels and normalize focus states.
5. Add dialogs, toasts, loading states, and form submission guards.

### Phase 2 — Revenue-critical workflows

1. POS search, serial picker, cart, payments, offline queue, checkout, and receipt.
2. Online shop browse, product detail, cart, checkout, M-PESA pending, and confirmation.
3. Sales detail, voiding, payment recovery, receipt, and PDF/thermal actions.

### Phase 3 — Inventory and after-sales operations

1. Inventory list, product create/edit/detail, images, serials, and labels.
2. Suppliers and goods receiving with review-before-commit.
3. Return selection, refund review, approval/rejection, and audit history.

### Phase 4 — Finance and administration

1. Dashboard variants, expenses, Z-report, VAT, purchases, and reports overview.
2. Settings sections, integration testing, delivery zones, and staff management.
3. Error, empty, no-results, and permission-denied states.

### Phase 5 — Verification and release polish

1. Establish approved desktop, tablet, and mobile screenshot baselines.
2. Run Playwright MCP journeys for admin, cashier, and customer roles.
3. Add automated accessibility checks, then manually test keyboard/focus order.
4. Test scanner input, offline queue, M-PESA status paths, printing, long content, and empty datasets.
5. Validate all routes at 375px, 768px, and 1440px with no unintended horizontal overflow.

## Acceptance criteria

- Every route has one clear page title, primary action, and next step.
- Admin-only links never appear to cashiers.
- No unintended horizontal overflow at 375px, 768px, or 1440px.
- Every form control has an associated label and a visible error state.
- Core actions work by keyboard and meet the target-size standard.
- Consequential actions show object-specific impact before confirmation.
- POS can be operated efficiently by barcode scanner and keyboard.
- Offline sales expose their full queue and synchronization state.
- Cart and checkout recover cleanly from validation, server, payment, and network failures.
- Tables retain the meaning of each value on mobile.
- Reports remain readable with empty, typical, and large datasets.
- Print views work for supported receipt and label sizes.
- Approved visual baselines exist for every key screen and role variant.

## Recommended implementation boundary

Keep the current PHP, CSS, and vanilla JavaScript architecture. Introduce small reusable view partials and focused JavaScript helpers rather than a framework migration. The professional result depends more on consistent patterns, hierarchy, responsive layouts, and workflow feedback than on changing the technology stack.
