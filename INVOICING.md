# LegalOps — Invoicing engine (India + GCC)

A multi-jurisdiction invoicing module built directly into LegalOps — no
Laravel, no second app to host. Composer is used for exactly one thing:
`dompdf/dompdf`, the PDF renderer.

## Setup

1. **Pull the code.** Everything is plain files under the existing
   `legalops` folder — nothing to copy from elsewhere.
2. **Install the one new dependency:**
   ```
   cd legalops
   composer install
   ```
   This downloads `dompdf/dompdf` into `vendor/` (already `.gitignore`'d
   and protected by `vendor/.htaccess`). If you skip this step, LegalOps
   still runs fine — you can create, issue and void invoices — you just
   won't be able to download/print the PDF until you run it.
3. **Database:**
   - **Fresh install:** `sql/legalops.sql` already includes the new
     `legalops_billing_entities`, `legalops_invoice_number_sequences`,
     `legalops_invoices` and `legalops_invoice_items` tables, plus two
     demo billing entities (one India, one UAE) and one sample invoice.
   - **Existing install** (you already had LegalOps running before this
     feature existed): import `sql/2026_invoicing.sql` instead. It's
     idempotent — safe to run more than once — and seeds exactly one
     placeholder billing entity so the Billing screen isn't empty.
4. Visit **Billing → Billing entities** and fill in your real GSTIN/TRN,
   address, and bank details for each entity you invoice from (edit the
   demo ones or add new ones — India, UAE, Saudi Arabia, Bahrain, Oman
   are all supported out of the box).
5. Go to **Billing → New invoice**.

## How it's structured

| File | Purpose |
|---|---|
| `config/tax_profiles.php` | The only place tax *rates* live — GST splits, VAT rates per GCC country, export/zero-rated declarations. |
| `libs/Invoicing.php` | Tax-profile suggestion, totals/line-item math, financial-year-aware sequential numbering. Plain functions, no classes — same style as `config/bootstrap.php`. |
| `libs/InvoicePdf.php` | dompdf wrapper. Throws a clear error if `composer install` hasn't been run yet. |
| `includes/invoice_template.php` | The actual invoice layout (HTML/CSS fed to dompdf). |
| `billing.php` | List, create/edit drafts, issue, void. |
| `billing_entities.php` | CRUD for the legal entities you bill from. |
| `invoice_download.php` | Renders a PDF on demand straight from the DB — nothing is cached to disk, so the PDF always reflects the stored figures exactly. |
| `invoice_items.php` | Small JSON endpoint the edit panel uses to fetch a draft's line items. |

## How tax treatment is decided

Every invoice picks one **tax profile** (`config/tax_profiles.php`):
`IN_GST_domestic`, `IN_GST_interstate`, `IN_GST_export`, `AE_VAT`,
`SA_VAT`, `BH_VAT`, `OM_VAT`, `GCC_VAT_zero_rated`, or `NO_VAT`.

The form **suggests** one automatically (entity country/state vs. the
client's country/place of supply), but the person issuing the invoice
can always override it. This is deliberate — GCC cross-border
place-of-supply and reverse-charge rules have enough edge cases that a
human checking the dropdown is safer than the system silently guessing.

## Numbering

- India: financial-year keyed (`Apr–Mar`), e.g. `LO-IN/2627/0001` for
  FY2026-27.
- GCC: calendar-year keyed, e.g. `LO-AE/2026/0001`.
- Numbers are reserved atomically (`legalops_invoice_number_sequences`)
  only at the moment an invoice is **issued**, not when it's drafted —
  drafts carry a `DRAFT-<id>` placeholder so editing/discarding a draft
  never burns a real number.
- **Voiding never deletes or reuses a number.** Both GST and Saudi
  ZATCA expect a gapless audit trail; void invoices stay in the list
  with their original number, marked `void`.

## What's deliberately NOT in this version

- **No government e-invoicing integration.** India's IRN (via the GSP/
  IRP) and Saudi ZATCA Phase 2 clearance both need certificate
  onboarding and a live API dependency on government infrastructure —
  a separate, larger project. `legalops_invoices.compliance_status`
  exists already (`not_required` for now) so wiring this in later
  doesn't need a schema change — just a driver that flips it to
  `cleared` after issue.
- **No persisted PDF files.** Every download re-renders from the DB.
  If you later need long-term archival copies (KSA in particular
  expects retained copies), add a `pdf_path` column and save on issue.
- **Tax rates need your sign-off.** The rates in `tax_profiles.php` are
  the standard published rates as of when this was written. Tax law
  changes — have your CA/tax advisor confirm before relying on this
  for real filings, especially for HSN/SAC codes and any GCC
  cross-border treatment.
