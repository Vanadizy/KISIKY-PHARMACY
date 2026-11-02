# Pharmacy email setup

Outgoing messages use PHPMailer over SMTP. Mail credentials stay in the project root `.env` file and are not stored in MySQL.

1. Open the project root `.env` (copy `.env.example` first if needed).
2. Set the mail server values:

   ```ini
   SMTP_HOST=mail.your-domain.example
   SMTP_PORT=587
   SMTP_USERNAME=notifications@your-domain.example
   SMTP_PASSWORD=your-mailbox-password-or-app-password
   SMTP_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=notifications@your-domain.example
   MAIL_FROM_NAME=Your Pharmacy Name
   ```

   Use the SMTP host, port, and encryption method provided by your email host. Port 587 usually uses `tls`; port 465 usually uses `ssl`. Keep `.env` private and never commit real credentials.

3. Install backend dependencies if they are not already installed:

   ```powershell
   composer install --working-dir=backend
   ```

4. Restart the PHP API server. In the admin dashboard open **Settings → Email delivery** and send a test message to the administrator account email.

The application emails staff account credentials on creation and reset; emails customers when their order is submitted, confirmed and waiting for payment, and completed/paid; emails Administrators for new orders, orders awaiting payment, and successful payments; and emails customers when POS payments are approved. Customer order and POS payment success messages are also sent by SMS. Every notification includes the website share-preview link. Customer order PDFs remain available in the customer account. Staff password reset changes only after the reset email has been accepted for delivery.

Messages are skipped if SMTP is not configured; the admin user creation screen continues to show the temporary credential for secure manual handoff. Delivery failures are written to the PHP error log. SMTP credentials are required before real delivery can be verified.


## SMS setup (Hesed Technologies / mShastra)

The SMS gateway follows the JSON API example in `documentation/API Test PHP.php`. Add these values to the project root `.env`, then restart PHP:

```ini
SMS_API_URL=https://mshastra.com/sendsms_api_json.aspx
SMS_PROFILE_ID=your-provider-profile-id
SMS_PASSWORD=your-provider-password
SMS_SENDER_ID=your-approved-sender-id
```

PHP cURL must be enabled. Customer numbers are normalized to Tanzania's `255` country code from local `0...` or `6...` / `7...` numbers; international `+255...` numbers are also accepted. SMS notifications are sent when a customer order is submitted, confirmed, and marked paid. In **Settings ? Email delivery**, use **Send test SMS** to verify delivery to a Tanzanian mobile number. The gateway uses the request and response shape shown in `documentation/API Test PHP.php`; `SMS_PROFILE_ID` is passed unchanged as the `user` value. The test screen displays the provider `response` status and HTTP status. `send success` or `send successful` is shown as sent; all other responses are shown as not sent. A provider send status means accepted for processing, not confirmed handset delivery. Keep credentials private and out of source control.
