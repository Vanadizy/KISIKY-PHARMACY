# Pharmacy System Progress

## Status

Phase 1 (inspection and architecture) complete. The workspace was empty when development began. The project uses a React/Vite frontend and a PHP REST API backed by MySQL/PDO, deployable as static assets plus PHP on Apache.

## Completed in foundation slice

- Initial project structure, environment examples, local setup and deployment documentation.
- MySQL schema for access control, products, stock, suppliers, restocking, orders, POS proformas, payments, invoices, audit, notifications and settings.
- PHP API foundation with environment configuration, PDO, JSON routing, session authentication, permission checks, CSRF protection and audit records.
- Authentication endpoints for login, logout, current user, and a CLI-only first administrator seed.
- Product catalogue and stock overview endpoints; product create/edit and transactional inventory adjustment.
- React public pharmacy landing/catalogue and permission-aware management shell with dashboard, inventory, orders, POS and finance views.
- Sample pharmacy branding is configuration driven; no live pharmacy secrets or credentials are committed.
- Product creation endpoint, first-administrator CLI seed utility, stock adjustment endpoint and transaction/idempotency guard on payment approval.
- npm dependencies installed (`frontend/package-lock.json` committed) and Vite production build succeeds.
- Local MariaDB `pharmacy` schema imported and `.env` configured for local root/no-password access.
- One-time first Administrator setup API and browser form; password is entered directly by the operator, setup locks after the first administrator.
- Local PHP API and Vite development server started on `127.0.0.1:8000` and `127.0.0.1:5173`.
- Added backend paths for user listing/permission assignment, customer registration/login, box orders and confirmation, suppliers/restocking, POS proformas and payment submission.
- Added local demo seeder: first-login Administrator, six public sample products with opening stock, four categories, demo supplier, wholesale customer and sample pending order.
- Added administrator first-login password change, checkbox permission assignment for new staff, user list, individual restock/history UI, order confirmation UI, POS proforma/payment UI, and finance approve/reject UI.
- Added a dedicated public `/products` catalogue page with customer cart actions and availability by full box. Added a separate `/customer` account/cart/order-history page with registration, email/phone login, persistent cart, Excel/CSV product-list availability preview, cart preview before submission, order tracking/details, and downloadable paid-order invoices. The homepage features products with Add to cart, a mobile-visible cart/count, View more products navigation and scroll reveal animations.
- Added Administrator-only pharmacy branding settings, validated PNG/JPEG/WebP logo file uploads, brand colors, homepage copy, contact details, address, map and social links; public pages reflect saved settings; browser titles, favicon and social preview metadata use the saved pharmacy name/logo, with a server-rendered share-preview URL for crawlers. The public landing page now uses a full-width responsive medicine photograph with product/contact calls to action; its shared footer shows saved logo, contact details, map link and Admin/Staff sign-in on home, catalogue and customer account pages.
- Enforced full payment amount when partial payments are disabled; user, customer order and finance endpoints enforce permissions/CSRF for their current operations. Customer wholesale order confirmation now sets confirmed quantities without deducting inventory; Finance records full payment, deducts stock transactionally, records the payment and creates an invoice. Sensitive workspace actions now use a shared review dialog with a detail preview and explicit acknowledgement, including stock adjustments/imports, restocks, customer order confirmation, POS proformas and payments, finance approval/rejection, staff/product setup, and pharmacy settings. Draft cart/restock/POS line removals are confirmed before removal.

## Outstanding

- Email/phone verification, delivery providers, 2FA, recovery flows, outbound credential delivery, rate limiting and production session hardening.
- User editing, suspension/deletion, editing existing permissions and permission summaries. New-user permission checkboxes are implemented.
- Excel inventory import with preview, add/replace modes and audited stock movement records. Customer Excel order-list upload is implemented; product/export workbooks remain outstanding.
- Supplier management UI, restock batches/expiry/price history and Excel bulk restock remain outstanding. Multi-product restocking and inventory Excel import are implemented.
- Customer product Excel/CSV upload preview, availability checks, downloadable order template, cart-based preview, order creation, customer order details and paid invoice print-to-PDF are implemented. Orders are confirmed by staff, then paid by Finance; stock is deducted only when payment is recorded. No stock is reserved during preview, and payment is manually recorded (no payment gateway). Customer profile/address management and order notifications remain outstanding.
- POS payment evidence, partial payments, printable receipts, refunds and separation of duties. Print-ready invoices for approved sales with browser Save as PDF and full-payment proforma flow are implemented.
- Notification delivery, reports/exports, configurable hero image/about content/hours/FAQ/SEO and PWA assets.
- Automated tests, end-to-end/security testing and production configuration review.

## Database/API

- Schema: `database/schema.sql`.
- API entry: `backend/public/index.php`.
- Routes: `backend/routes/api.php`.
- Seed command: `php backend/bin/seed-admin.php` (requires an interactive password prompt).

## Verification

- `php -l` passed for all eight PHP files after the latest API changes.
- `npm install --no-audit --no-fund --ignore-scripts --prefer-online` completed successfully after pinning the React Vite plugin to the Vite 6-compatible major.
- `npm run build` passed after adding the XLSX dependency; Vite emits only the existing large-chunk advisory.
- Settings endpoint code passes PHP syntax; browser/API save round-trip was not performed because the previously issued temporary Administrator password no longer authenticated.
- Local MariaDB version 10.4.32 accepted root/no-password authentication; `database/schema.sql` was imported.
- API `/api/health` returned healthy and `/api/setup/status` reported first-run setup required.
- PHP API and Vite dev server are running in local terminal sessions. Browser automation was unavailable for visual validation.
- Local API list checks passed for products, inventory, orders, finance payments, users, restocks and restock product selection under the Administrator session.
- Customer catalogue API returned six products with box availability; Vite served `/products` and `/customer` with HTTP 200.
- Fixed the browser CORS failure by allowing only the configured local Vite origins (`localhost` and `127.0.0.1`) and aligning the frontend API default to `127.0.0.1`; verified CORS response and OPTIONS preflight.
- Enabled React Router v7 future flags to quiet the reported migration warnings.
- Demo Administrator requires a password change on first sign-in.
- Automated business-logic tests and full workflow integration tests have not been run.
- Customer order payment and invoice migration was run against the configured local MySQL database. Payment recording is manual and should be verified against the pharmacy's actual payment evidence workflow before use for real transactions.

## Next phase

Complete customer ordering and supplier screens, then build automated tests for restock, order confirmation, POS approval and duplicate/stock protection.

- Public site headers now stay visible while scrolling on desktop and mobile; staff workspace top navigation also stays sticky. Added a Help dialog to the public homepage, product catalogue and customer account with saved pharmacy address, phone, email and map details. Frontend production build was checked after these changes (initial build was still running at handoff).

- Made the staff mobile app-bar navigation icon explicit, accessible, and visibly styled as a menu button at the mobile breakpoint.

- Customer product availability/order preview now opens in a modal overlay instead of expanding below the account page. It preserves the availability table, totals, validation, and create-order step.

- Added a website navigation icon beside Help and Cart. Its responsive menu includes Home, Products, My orders (customer account/order history), and Contact on desktop and mobile.

- Restricted the website header menu icon and dropdown to mobile widths; desktop retains its normal navigation links. Moved website Help to a fixed bottom-right control alongside WhatsApp, which uses the pharmacy contact phone saved in settings.

- Added the dedicated /my-orders website destination in desktop and mobile navigation. It presents sign-in and order history separately from the cart page. Customer checkout now routes unauthenticated users to sign-in/register while preserving their cart and availability preview; new registration signs in automatically and resumes the order review step.

- Added My orders to the desktop landing navigation as well as the mobile website menu. /my-orders is its own customer page with order-history access and sign-in/register. Unauthenticated customers must authenticate before order submission; the reviewed cart/availability is retained, registration signs in automatically, and order confirmation resumes.

- Standardized preview and sensitive-action modal colors for contrast, legible row banding, visible warnings, and clearer review instructions. Customer availability preview now explicitly explains quantity/price review, sign-in/register, and payment-gated stock effects; POS preview explains proforma and finance steps.

- Pharmacy settings now show the existing or selected logo in both the live settings preview and the save confirmation, and capture street address, city/region, P.O. Box, contact phone/email and map URL. API settings validation accepts location and P.O. Box fields. Sales and customer invoice print layouts now include logo and all configured pharmacy contact/location details. Added admin sidebar collapse/expand on desktop and explicit close on mobile.

- Consolidated admin sidebar navigation controls into one app-bar icon. The same icon opens/closes the mobile drawer and collapses/expands the desktop sidebar.

- Settings now display the saved/selected logo preview and a live Google Maps location preview. Staff can enter an optional Google Maps URL or rely on an address/location search query; website contact footer displays all configured details, an embedded map, and a Google Maps link.

- Fixed mobile admin sidebar close control: made the X an explicit button, stopped event propagation, and raised it above the drawer scrim with a clear clickable target.

- Aligned the admin sidebar drawer breakpoint with the app-bar navigation control through 760px, including small tablet widths. Positioned the X close target above the scrim and added pointer-down closing for touch devices.

- Sidebar X now closes through a standard click event; removed pointer-down state changes that could interrupt the click/touch sequence. Enlarged the close target and retained its layer above the scrim.

- Removed the X close control from the administration sidebar as requested. The app-bar navigation toggle and outside scrim remain available to close the drawer.

- Fixed the Settings white-screen runtime error by defining the Google Maps embed URL in SettingsPanel from the configured address/location or map link. Frontend production build passes.

- Added practical search, status and date filters to staff customer orders, invoices, finance order/payment queues, restock history and user management. Inventory adds in-stock/low-stock filtering; generic record tables are now searchable; customer My Orders history includes search/status/date filters. Shared filter controls adapt to mobile.

- Refined Settings logo management into a dedicated large current-logo preview with the change-logo file picker directly below. The location search preview now shows the actual address/search terms and P.O. Box, refreshes from the name/address/location inputs, and public contact uses the same search details for its embedded map and contact block. Final filter and Settings build passed.

- Removed Staff position from the administration Add user form and its submitted form state. Made the dialog scroll within the viewport with a sticky header and bottom action buttons so all fields and controls remain reachable on short screens.

- Clarified responsive tab/modal behavior: auth tabs can scroll horizontally on narrow screens, and shared form modal headings/actions stay reachable while their contents scroll. Invoice print-to-PDF output now includes configured WhatsApp/Facebook/Instagram alongside logo, address, P.O. Box, phone, email and map details.

- Fixed custom Google Maps link handling: Settings and customer contact map now derive embeds from configured Google Maps embed, search, place and coordinate URLs; original map link remains directly clickable in both interfaces.
