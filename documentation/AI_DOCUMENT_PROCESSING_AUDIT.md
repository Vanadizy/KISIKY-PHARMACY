# AI Document Processing Audit

Date: 2026-11-08

## Scope and current state

Reviewed the React assistant, PHP upload/extraction handlers, Gemini client, database migrations, local PHP configuration, and MySQL schema. Migration `008_ai_processing_jobs.sql` has been applied locally. It only adds AI document metadata and queue tables. It does not alter or seed users, settings, products, inventory, orders, finance, or payments.

## Findings addressed

| Earlier issue | Current handling |
| --- | --- |
| One bad document stopped later uploads | Each upload is validated independently. Successful extractions remain available while failed documents are listed. |
| Long synchronous work timed out and was not resumable | Uploads can be queued per document/page chunk. MySQL records stage, progress, attempts, result, lock expiry and error. HTTP worker requests release the session and ignore client disconnects. |
| PDF pages were retried as one large request | Browser splits PDFs into configurable page groups. Groups carry original page offsets and a shared source reference. A response-length failure is retried as smaller groups. Completed groups remain durable if another group fails. |
| XLS/XLSX parsed only one worksheet and CSV required commas | Each worksheet is parsed, and CSV detects comma, semicolon, tab or pipe separators. Source worksheet/row details are retained in review data and exports. |
| Local files or tabs were needed to resume | Staff/customer job lists load from the database and permit resume/retry from another session after authenticating as the same account and branch context. |
| Progress was shown as a blind spinner | The UI displays upload progress and server-reported job stages/progress. The AI generation part is still coarse-grained rather than token-level. |
| Retry could duplicate extracted rows | Extraction replacement runs inside a transaction; retry removes prior rows for that document (and cascaded candidate matches) before inserting the new result. |

## Implemented components

- `backend/migrations/008_ai_processing_jobs.sql`: additive job and page-batch tracking tables plus document source/page metadata.
- `backend/src/AIAssistant.php`: ownership/branch checks, durable enqueue/status/retry/run endpoints, secure private document storage, per-job progress, result persistence and idempotent extraction retry.
- `backend/bin/ai-document-worker.php`: command-line worker for queued and expired jobs. It can also run from hosting cron as a fallback.
- `backend/src/Gemini.php`: expanded workbook parsing, larger configurable row caps, spreadsheet source refs and expanded extraction line limit.
- `frontend/src/AIOrderAssistant.jsx`: independent per-file processing, PDF page grouping, smaller-group recovery, persistent job resume/retry, progress, and Excel/PDF exports.

## Local checks completed

- PHP syntax checks passed for `AIAssistant.php`, `Gemini.php`, the migration runner and worker.
- The migration runner was applied locally; schema can be re-applied safely.
- `php backend/bin/ai-document-worker.php 1` completed successfully and reported no queued jobs.
- `npm.cmd run build` completed successfully. Vite reports the main JavaScript chunks exceed 500 kB; the build succeeds, but further code splitting could improve initial load time.
- Earlier parser checks passed for the customer order template, a two-sheet XLSX workbook, semicolon CSV, and a legacy XLS workbook.

## Hosting setup and limits

The local XAMPP CLI configuration inspected on 2026-11-08 reported PHP 8.2, cURL, GD, ZIP, XML and PDO MySQL, with 512 MB memory, 50 MB per upload and 55 MB POST limit. These values do not establish the cPanel account's web PHP limits. `pdftotext` and `pdfinfo` were not present locally.

The UI and server default to five files, 50 MB per file/batch, 20,000 rows per spreadsheet and 20,000 extracted lines. Actual accepted uploads are still bounded by PHP, Apache/LiteSpeed, proxy and hosting limits. Configure matching values in server `.env` and frontend upload validation for the host.

For cPanel recovery processing, add a cron entry using the account's PHP executable and API path, for example:

```sh
/usr/local/bin/php /home/ACCOUNT/pharmacy-api/backend/bin/ai-document-worker.php 10
```

Use the actual PHP path and home directory shown by cPanel. Run every 2–5 minutes if you want cron recovery; foreground job requests already process promptly when the user remains connected. Keep `backend/storage/ai-documents` writable and outside `public_html`, and schedule `backend/bin/cleanup-ai-documents.php` for retention cleanup.

## Remaining limitations and verification

- Spreadsheet rows are still loaded into memory with PhpSpreadsheet. `AI_MAX_SPREADSHEET_ROWS` is a safety cap, not streaming import.
- PDF splitting happens in the browser; very large/complex PDFs can use substantial client memory. Server-side page rendering/OCR is not part of this implementation.
- Job progress is stage-based and coarse. Gemini does not provide a reliable percentage for a single generation call.
- A live Gemini extraction was not run during this pass to avoid consuming the configured API quota. Re-run with the configured API key and representative image/PDF samples, and verify request size/timeouts on cPanel before production use.
- Automatic result-to-column mapping for spreadsheets without recognizable headers is not implemented; valid workbooks need a detectable product/code and quantity header.
- The local SQL migration and empty worker path were verified. A complete authenticated browser-to-database upload, PDF recovery, cPanel cron execution and email/notification cycle were not replayed in this pass.

## Data safety

No user, settings, inventory, product, order, finance or payment rows were modified during this work. The only local database change was the additive AI processing migration.
