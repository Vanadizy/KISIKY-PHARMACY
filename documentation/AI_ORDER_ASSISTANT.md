# AI Order Assistant

## Setup

The scanner uses the server-side Gemini API. Put the key in the project `.env` on the local server and API host. Keep it private and never put it in a frontend `.env` file.

```dotenv
GEMINI_API_KEY=your_google_ai_studio_key
GEMINI_MODEL=gemini-3.5-flash-lite
AI_INPUT_COST_PER_MILLION=0.30
AI_OUTPUT_COST_PER_MILLION=2.50
AI_DAILY_LIMIT_USD=2
AI_MONTHLY_LIMIT_USD=20
AI_DAILY_REQUEST_LIMIT=30
AI_MONTHLY_REQUEST_LIMIT=500
AI_DOCUMENT_RETENTION_DAYS=180
AI_MAX_UPLOAD_FILES=5
AI_MAX_DOCUMENT_MB=50
AI_MAX_BATCH_UPLOAD_MB=50
AI_MAX_SPREADSHEET_ROWS=20000
AI_MAX_ORDER_LINES=20000
```

Cost values are estimates in usage logs. They do not enable billing or change Google AI Studio quotas; the free tier's model and account quotas still apply. Gemini 3.5 Flash-Lite accepts image and PDF inputs. Images use high media resolution to help with handwriting; PDFs use medium resolution to balance legibility and speed. Refer to the [model documentation](https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite), [media resolution](https://ai.google.dev/gemini-api/docs/generate-content/media-resolution), and [pricing](https://ai.google.dev/gemini-api/docs/pricing).

Excel workbooks are parsed on the PHP server with PhpSpreadsheet; Gemini is not used for XLS/XLSX/CSV. Worksheets are read independently, and CSV delimiter detection checks comma, semicolon, tab and pipe. Worksheet and row references are preserved in review and exports. `AI_MAX_SPREADSHEET_ROWS` is a per-file safety limit; PhpSpreadsheet loads rows in memory, so set the limit to fit the host's memory. Spreadsheet matching works without a Gemini API key.

Upload limits are configured on the API host and also depend on PHP `upload_max_filesize`/`post_max_size`, the web server and the host proxy. The local XAMPP CLI settings inspected on 2026-11-08 reported 50 MB per file, 55 MB per POST and 512 MB memory. Check the cPanel web PHP settings separately.

## Database and deployment

`backend/bin/migrate-ai-order-assistant.php` applies additive migrations `007_ai_order_assistant.sql` and `008_ai_processing_jobs.sql`. Migration 008 adds processing job/batch records and source/page metadata. It does not change existing inventory, user, settings, order, finance or payment records. The cPanel deployment invokes this migration runner. Hosting PHP must enable cURL, GD, ZIP and XML support.

Keep `backend/storage/ai-documents` writable and outside the public web root. Schedule `backend/bin/cleanup-ai-documents.php` to apply document retention. The API worker runs short jobs immediately for interactive users; add cPanel cron as a recovery worker for closed sessions or interrupted requests. Example only—replace the PHP binary and home directory with the values in your cPanel account:

```sh
/usr/local/bin/php /home/ACCOUNT/pharmacy-api/backend/bin/ai-document-worker.php 10
```

A 2–5 minute schedule is reasonable. The final number limits a worker run to ten documents/page groups. Grant `Access AI Order Assistant` to authorized staff in User Management. Product creation from an unmatched POS line separately requires `products.create` and starts with zero stock. Customer matching only offers products visible in the public catalog.

## Workflow

1. Staff launch the assistant from POS. Customers launch it from Cart and must sign in before processing/submitting an order.
2. Upload up to five PDFs, photos or spreadsheets. The browser splits PDFs into page groups controlled by `VITE_AI_PDF_PAGES_PER_REQUEST` (default four). If Gemini cannot fit a group response, that group is split smaller and retried. Each group gets its own durable job with the original page offset and common source reference.
3. The system reports the current upload/job stage. You can close the overlay and resume saved jobs later. Completed document groups remain saved even if another group fails. Transient service errors are retried within the configured attempt limit; failed jobs can be explicitly retried.
4. Review the extracted text, product, strength/form, quantity, unit, source page/row, match and stock. Crossed-out lines start excluded. A trailing `- 5` is treated as a clear quantity with default unit BOX; uncertain lines are flagged.
5. Resolve included lines against actual active products. Product spelling is normalized and fuzzy matched, with strength/form safeguards and human review. Customer order submissions continue through the existing order preview/confirmation flow; POS continues through proforma/payment. Extraction itself does not change stock.
6. Download the extracted rows and inventory comparison as Excel, or download a PDF review report.

The progress percentage is based on file upload plus backend stages; a single Gemini response does not expose reliable generation progress. Usage logs retain model, token counts, estimated cost, status and duration, not the uploaded document text or API key.

## Local verification

From the project root, run `npm.cmd --prefix frontend run build`, `php -l` on the AI PHP files, and `php backend/bin/migrate-ai-order-assistant.php`. A live image/PDF extraction requires a configured Gemini key and consumes that account's quota. The worker can be run manually with `php backend/bin/ai-document-worker.php 10`.

See [AI document processing audit](AI_DOCUMENT_PROCESSING_AUDIT.md) for verified local capabilities and remaining limits.
