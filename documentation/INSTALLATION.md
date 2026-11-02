# Installation and deployment

## Current implementation status

This repository is an in-progress application, not a production-ready pharmacy system. Read `PROJECT_PROGRESS.md` for implemented and outstanding modules. The application includes the public catalogue, customer cart and Excel/CSV order preview, customer order tracking, finance-recorded payment and paid-order invoice printing, multi-product restocking, inventory Excel imports, POS proformas and Administrator branding settings. This remains a local/development implementation; review the security and feature gaps below before production use.

## Local Windows setup (XAMPP)

1. Install XAMPP with PHP 8.1+ and MySQL, Composer 2, Node.js LTS, and VS Code.
2. Start MySQL from the XAMPP Control Panel. In phpMyAdmin, import `database/schema.sql`; this creates the `pharmacy` database and its schema.
3. Copy `.env.example` to `.env`. The configured local database uses MySQL user `root` with an empty password. The API uses `http://127.0.0.1:8000` and allows the Vite origin at `http://localhost:5173`.
4. Check that PHP has `pdo_mysql` enabled and import `database/schema.sql` into MariaDB/MySQL.
5. Start the PHP development server: `php -S 127.0.0.1:8000 -t backend/public`.
6. In another terminal: `cd frontend`, then `npm install`, then `npm run dev -- --host 127.0.0.1`.
7. Open `http://127.0.0.1:5173`. Choose “Set up pharmacy” to create the first Administrator. Enter the password directly in the browser; it must have at least 12 characters. Setup is locked once an Administrator exists. Then sign in through “Staff sign in.”
8. Public customer pages are available at `/products` and `/customer`. Customers can upload an Excel/CSV file with `Product Code` and `Quantity (Boxes)` columns, preview availability, sign in/register and submit an order. Staff confirm quantities; Finance records the payment. Stock is deducted only when marked paid, at which point the customer can open and print/save the invoice as PDF from order history. For an existing database, run `php backend/bin/migrate-customer-order-payments.php` and `php backend/bin/migrate-order-missing-products.php` once after setting `.env`. Logo image uploads support PNG, JPEG or WebP files up to 50 MB; the uploaded image is also used as the favicon. The bundled local XAMPP `php.ini` uses `upload_max_filesize=50M` and `post_max_size=55M`; restart PHP/Apache after changing these limits.
9. To seed local demo data, run `php backend/bin/seed-demo.php`. It creates an Administrator only if none exists and prints one-time demo credentials, then seeds products, stock, a supplier, customer and sample order. The demo Administrator must change the generated password on first sign-in.

To point Vite at another API address, create `frontend/.env` with `VITE_API_URL=http://localhost:8000/api` and restart the dev server. For link previews, share the footer?s **Share pharmacy site** link (the API `/share` endpoint); it renders the saved pharmacy name and uploaded logo for preview crawlers, then redirects visitors to the public homepage. In production, set `FRONTEND_ORIGIN` to the public website origin.

## Production build

From `frontend`, run `npm run build`. Vite writes static assets to `frontend/dist`; no Node server is needed to serve the built frontend. Set `VITE_API_URL` to the HTTPS production API URL before building if frontend and API are on separate hosts.

## Apache/shared hosting

1. Create a MySQL database and restricted database user in cPanel. Import `database/schema.sql`.
2. Upload the PHP API files under `backend/` to a private or web-accessible API directory. Point the API subdomain/document root at `backend/public`; keep `config`, `routes` and `src` outside the document root where possible. Enable Apache `mod_rewrite` and `.htaccess` overrides.
3. Configure `.env` on the server with production database credentials, `APP_ENV=production`, the exact frontend origin and a secure session name. Do not upload `.env.example` as `.env` or commit production secrets.
4. Build the React frontend with the production API URL and upload the contents of `frontend/dist` to the website document root. Configure the host's SPA fallback to `index.html` for client routes.
5. Enable HTTPS on both frontend and API. Configure PHP session storage, least-privilege file permissions, backups, SMTP and SMS gateway credentials, and database backups.
6. Create the first administrator using a deployment-only secure process. The current interactive seed script is intended only for local development.
7. Verify API health at `/api/health`, login, public catalogue, stock adjustment and finance permissions in a staging environment before any live data is used.

## Important security and feature gaps

- Authentication is email/phone plus password only; 2FA, recovery, rate limiting and automated credential delivery are outstanding.
- Review PHP session, CSRF, HTTPS, CORS, authorization, upload and audit policies with a security professional before production.
- Customer order payment is manually recorded by Finance and does not connect to a payment gateway or validate external payment evidence. Invoice download opens a print dialog so the customer can save a PDF. Confirm the payment evidence and reconciliation process before relying on it for real transactions.
- Do not use real customer or financial records in the current build.


The public landing page hero photo credit and license are recorded in `documentation/IMAGE_CREDITS.md`. Configure contact phone/email, address and the map URL under Admin Settings; they appear in the shared bottom footer. Admin/Staff sign-in is also in that footer.

### Outgoing email

Configure SMTP credentials in the project root .env file and use the administrator Settings > Email delivery tab to send a delivery test. See [EMAIL_SETUP.md](EMAIL_SETUP.md) for SMTP and Hesed Technologies / mShastra SMS credentials, notification events and delivery checks.

