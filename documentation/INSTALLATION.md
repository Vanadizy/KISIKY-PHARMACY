# Installation and deployment

## Current implementation status

This repository is an in-progress application, not a production-ready pharmacy system. Read `PROJECT_PROGRESS.md` for implemented and outstanding modules. The application includes the public catalogue, customer cart and Excel/CSV order preview, customer order tracking, finance-recorded payment and paid-order invoice printing, multi-product restocking, inventory Excel imports, POS proformas and Administrator branding settings. This remains a local/development implementation; review the security and feature gaps below before production use.

## Local Windows setup (XAMPP)

1. Install XAMPP with PHP 8.1+ and MySQL, plus Node.js LTS.
2. Start MySQL from the XAMPP Control Panel. The existing local setup uses the `pharmacy` database; import `database/schema.sql` only if the database has not been initialized yet.
3. Ensure the project root `.env` exists and has the correct `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`. The example defaults are `127.0.0.1`, `3306`, `pharmacy`, `root`, and an empty password.
4. Confirm the PHP CLI has `pdo_mysql` enabled: `php -m | findstr /I pdo_mysql`.
5. From the project root, start the API with `php -S 127.0.0.1:8000 -t backend/public backend/router.php`. Keep this terminal open. The explicit IPv4 address matches the frontend API URL; the router forwards API requests and serves uploaded files. If an old PHP server is running, stop it with Ctrl+C first.
6. In a second terminal, run `cd frontend`, then `npm install` if needed, and `npm run dev -- --host 127.0.0.1`. Keep this terminal open too.
7. Verify `http://127.0.0.1:8000/api/health` returns `{"ok":true,"service":"pharmacy-api"}`, then open `http://127.0.0.1:5173`.
8. Sign in with the existing Administrator account if one is already present. Otherwise, use the setup screen to create the first Administrator. Public customer pages are `/products` and `/customer`.

If PHP is not on PATH, use the XAMPP executable in step 5, for example `C:\xampp\php\php.exe -S 127.0.0.1:8000 -t backend/public backend/router.php`.

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

## cPanel Git deployment from GitHub

The repository root contains `.cpanel.yml`, which cPanel runs when a commit is deployed. Its tasks copy the committed static website to `$HOME/public_html` and the PHP API to `$HOME/pharmacy-api/backend`, then install production Composer dependencies. The API subdomain should use `$HOME/pharmacy-api/backend/public` as its document root. If your hosting account uses different paths, update `.cpanel.yml` before deploying.

cPanel deploys a commit in its own Git Version Control repository; it does not continuously watch GitHub by itself. The included GitHub Actions workflow forwards each push to `main` to a cPanel-managed Git repository over SSH. cPanel's deployment hook then runs `.cpanel.yml`. Configure the cPanel Git repository for the `main` branch and enable deployment. Keep that checkout clean; make source changes in GitHub rather than editing the cPanel checkout.

### One-time server setup

1. In cPanel, create a Git Version Control repository with a writable SSH Git remote and deployment enabled for `main`. Confirm that cPanel recognizes the root `.cpanel.yml`.
2. Create the website document root at `$HOME/public_html` and configure the API subdomain (for example, `api.kisikypharmacy.com`) to use `$HOME/pharmacy-api/backend/public`. The deployment creates the parent directories.
3. Create the production MySQL database/user, import `database/schema.sql`, and create `$HOME/pharmacy-api/.env` on the server. Set production values for `APP_ENV`, `APP_URL`, `FRONTEND_ORIGIN`, `SESSION_NAME`, `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASSWORD`, plus the email/SMS gateway settings you use. Keep this `.env` outside Git; deployment does not overwrite it.
4. Ensure the deployment shell has PHP 8.1+, `pdo_mysql`, and Composer available. Make `backend/storage` and `backend/public/uploads` writable by the PHP process. Enable HTTPS for the website and API.
5. Add these repository secrets in GitHub under **Settings → Secrets and variables → Actions**:

   - `CPANEL_DEPLOY_KEY`: private SSH key authorized to push to the cPanel Git repository.
   - `CPANEL_SSH_KNOWN_HOSTS`: the verified SSH host-key line for your cPanel host (including a nonstandard port in `[host]:port` form if applicable).
   - `CPANEL_GIT_REMOTE`: the SSH Git URL/path for the cPanel-managed repository.

   Do not put private keys or server credentials in the repository or in chat. Configure the cPanel repository branch as `main` to match the workflow.
6. Commit and push `.cpanel.yml`, `.github/workflows/deploy-cpanel.yml`, and your application changes to GitHub `main`. Check the GitHub Actions run and cPanel deployment log after the first push, then verify `https://api.kisikypharmacy.com/api/health` and the public website.

The production frontend files (`index.html`, `assets/`, and related root files) are already committed as deployable static output, so cPanel does not need Node.js. When changing the frontend API URL or frontend code, build it using the production API URL and copy the new build output to the repository root before pushing. If the cPanel deployment reports uncommitted changes, restore its Git checkout to a clean state; generated application uploads and `.env` belong in the deployment target, not in manual edits to the checkout.

## Important security and feature gaps

- Authentication is email/phone plus password only; 2FA, recovery, rate limiting and automated credential delivery are outstanding.
- Review PHP session, CSRF, HTTPS, CORS, authorization, upload and audit policies with a security professional before production.
- Customer order payment is manually recorded by Finance and does not connect to a payment gateway or validate external payment evidence. Invoice download opens a print dialog so the customer can save a PDF. Confirm the payment evidence and reconciliation process before relying on it for real transactions.
- Do not use real customer or financial records in the current build.


The public landing page hero photo credit and license are recorded in `documentation/IMAGE_CREDITS.md`. Configure contact phone/email, address and the map URL under Admin Settings; they appear in the shared bottom footer. Admin/Staff sign-in is also in that footer.

### Outgoing email

Configure SMTP credentials in the project root .env file and use the administrator Settings > Email delivery tab to send a delivery test. See [EMAIL_SETUP.md](EMAIL_SETUP.md) for SMTP and Hesed Technologies / mShastra SMS credentials, notification events and delivery checks.

