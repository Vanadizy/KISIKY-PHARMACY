# Multi-branch ordering and document extraction plan

## Target workflow

1. Keep one shared product catalogue. Do not include branch names, branch stock counts, or selling prices in public landing/catalogue responses.
2. In the customer cart/order review, let the customer choose an active branch. Recalculate the order against that branch's stock and price rules before confirmation. Products that cannot be matched or are not currently stocked can still be submitted for branch review; their invoice line price remains null until staff resolve it.
3. Store the chosen branch on the order. Route new-order and payment notifications to that branch's finance email/contact, and display only that branch's location, payment instructions, and cash-at-office option on the order/payment screens.
4. Branch staff process only records for their assigned branches. Administrators can select any branch in the administration dashboard and can manage all branches and assignments.
5. Deduct stock atomically from the selected branch only after Finance verifies the payment. Record the receiving Finance user and timestamp. Record order processing, confirmation, payment review, stock movements, branch/user changes, and other sensitive actions in the audit log with actor, entity, branch, and before/after details where relevant.
6. Apply the same branch context to POS proformas, payment approval, inventory, restocking, reports, and invoices. Preserve a branch snapshot on historical documents so later branch edits do not rewrite old invoices.

## Data model foundation added

`database/migrations/002_multi_branch_foundation.sql` adds branches and branch contact/payment fields, per-branch inventory, user-to-branch assignments, branch references on orders and POS proformas, and nullable customer-order line prices. Existing global stock is copied into a default branch during migration. Fresh installations receive a default branch from the pharmacy name.

Back up the database before applying the migration. It is a one-time upgrade script; do not run it twice. The implementation now scopes the customer order, inventory, restock, POS, finance, expense, dashboard, and report paths to the selected branch. Build and syntax checks pass; complete an end-to-end staging pass with real credentials and sample branches before production cutover.

## Admin and staff controls

- Branch management is a dedicated workspace page with the pharmacy logo and branch name, location, address, phone, contact email, Google Maps link, payment instructions, and active state. Global pharmacy branding and website settings remain in general Settings. Branch finance email recipients are active staff assigned the branch with Finance permissions.
- User creation/editing assigns each non-administrator staff account to exactly one branch and retains the existing account level/permissions. Administrator accounts are global; an administrator may switch the active branch context.
- Non-administrator APIs must enforce branch assignment server-side for list, view, create, process, finance, POS, inventory, restock, and report operations. Hiding records in the interface alone is not access control.
- Audit entries should identify the actor and branch. Payment approval must record `reviewed_by`/`paid_by` and server time; stock movement rows must reference the branch and responsible user.

## Price, availability, and invoice behavior

- The landing page and public catalogue show product information without prices or branch stock status.
- Show a price only after the customer opens the cart/order review. Confirm the pricing rule before implementation: the current product price is global; this data model does not yet store different prices by branch.
- A branch order can include matched and unmatched items. A matched item unavailable at the selected branch remains a pending line and has a null price until branch staff resolve it. Never convert null to zero in invoice rendering or totals; unresolved lines must be visibly marked pending and excluded from payable totals until priced.
- The branch invoice/payment instructions must be used for order notification and customer payment. Finance marks the order paid only after checking evidence or confirming cash received, then stock deduction and paid invoice creation happen in one transaction.

## AI document upload: pending feature placement

Show **Import from PDF, spreadsheet, or image (AI, pending)** in the customer cart and POS cart. Clicking it should show a clear “planned / not enabled yet” notice; it must not imply that a document was uploaded, analyzed, or invoiced. Spreadsheet import can continue using the current deterministic importer while AI extraction is pending.

When enabled, the flow should be: upload to a private/quarantined area → validate size/type and scan → extract text/tables/OCR through a selected AI provider → return proposed product matches and quantities → require a human to review every match and quantity → run the normal branch/POS stock and price preview → require explicit confirmation → create the ordinary order/proforma and audit the source file and extraction result. Never auto-submit an extracted order or auto-deduct stock. Do not send customer documents to an AI provider until the provider, retention policy, privacy terms, and credentials are chosen.

## Completion sequence

1. Apply and verify the database migration on a backup/staging database.
2. Add branch setup and user assignment screens; add administrator branch switching and API-level branch access checks.
3. Make catalog, inventory, restock, and stock movement operations branch-scoped.
4. Add branch selection, unresolved/null-price order lines, branch notifications, branch payment instructions, and finance receipt review to customer ordering.
5. Apply branch context to POS, payments, invoices, dashboard, reports, and audit screens.
6. Hide public prices/availability and show cart-only prices; add pending AI import controls in customer cart and POS.
7. Run end-to-end review on staging: cross-branch access denial, order routing, receipt review, correct branch-only deduction, audit attribution, invoice totals with unresolved lines, and notifications.



## Implemented branch workflow (November 2026)

- Customers choose a fulfilment branch from selectable cards in the order review dialog. The chosen branch's location, contact details, map link, and payment instructions are shown before submission. Orders and inventory effects remain associated with that branch.
- Administrators switch branch context from the pharmacy identity area in the workspace sidebar. Branch management is a separate navigation page, while pharmacy branding and other general settings remain global.
- Non-administrator staff have exactly one assigned branch. Administrators are global and switch context. A staff member with the `branches.manage` permission may edit details for their assigned branch; creating branches and activating or deactivating them remain administrator actions.
- Branch finance email recipients are active staff assigned to the branch with Finance permissions. Order notices go to branch order and Finance staff, plus administrators. Messages identify the branch.
- POS uses Products and Cart tabs on small screens. Customer and workspace request errors and notices use the shared, top-right auto-dismiss snackbar.
- Migration `003_branch_permissions_maps_single_assignment.sql` adds the branch map link and scoped branch permission and reduces existing non-administrator multi-branch assignments to one branch. Back up first and apply it after migration 002 on other installations.


## Implemented in the application

- Branch management uses a selectable table and detail preview. Branch address, contacts, payment instructions and Google Maps link belong to each branch. WhatsApp, Instagram and Facebook links remain global administrator settings.
- Administrators can switch branch context from the workspace header. Non-administrator accounts have one branch assignment; staff and user records are branch scoped.
- Each branch has its own selling price, buying price and stock. Apply migration 004 after migrations 002 and 003 to upgrade an existing database.
- POS accepts CSV/XLSX imports, offers exact matches and suggested product matches, and requires unresolved rows to be matched or removed before adding them to the cart.
- User audit records include actor and branch context; administrator user management can review the system audit list.
- Order notification emails link to the specific order page. AI extraction from PDFs and images remains planned and is not enabled.

- Public site additions: a branch map/contact grid below the team section, an About page, and a public feedback form. Existing databases need migration 006 for feedback storage.
