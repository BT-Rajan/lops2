# Invoicing (India GST + GCC VAT)

The billing module (`/billing`, `/billing/entities`) issues invoices from one
or more **billing entities** — the legal entities you invoice from — using a
small set of **tax profiles** that cover the common India/GCC scenarios.

## 1. Set up a billing entity

Go to **Billing → Billing entities → New entity** and fill in:

| Field | Notes |
|---|---|
| Country | Decides which tax profiles get suggested |
| Registration type | `India — GST registered`, `GCC — VAT registered`, or `No VAT/GST registration` |
| GSTIN / TRN | Your entity's own tax registration number, printed on every invoice |
| State / Emirate | For India, this is compared against the client's place of supply to pick CGST+SGST vs IGST |
| Invoice number prefix | e.g. `LO-IN` or `LO-AE` — combined with a period and a gapless running number, e.g. `LO-IN/2526/0001` |

You can have more than one entity (e.g. an India entity and a UAE entity) and
issue invoices from whichever one applies to a given client.

## 2. Tax profiles (`config/tax_profiles.php`)

Every invoice is issued under one **tax profile**, which defines the tax
components and rates charged (e.g. `CGST 9% + SGST 9%`, or `UAE VAT 5%`).
When you pick a billing entity and fill in the client's country/state on the
invoice form, a profile is **suggested** automatically — but it's always a
suggestion, not a decision made for you. Cross-border place-of-supply rules
(especially intra-GCC reverse charge) have enough edge cases that a human in
the loop is safer than a fully automatic one, so double-check it before
issuing.

**Rates in `config/tax_profiles.php` are the commonly published standard
rates for each jurisdiction as of when this file was written.** Tax law
changes, and HSN/SAC mapping and registration thresholds are firm-specific —
have your CA/tax advisor confirm the rates and profile wording for your
actual entities before relying on this for real filings.

To add a new profile (a new country, a special zero-rated case, etc.), add
an entry to the array returned by `config/tax_profiles.php`:

```php
'MY_PROFILE_KEY' => [
    'label'       => 'Shown in the dropdown and on the PDF',
    'split'       => ['CGST' => 9, 'SGST' => 9], // tax components + rate %; [] = no tax
    'reg_label'   => 'GSTIN',   // what to call the tax reg. number on the PDF, or null to hide it
    'needs_hsn'   => true,      // show an HSN/SAC column on line items?
    'declaration' => null,      // optional fixed statement printed near the totals
],
```

## 3. Draft → issue → void — and why numbers never move

Invoices are created as **drafts** (editable, no number assigned yet — they
show as `DRAFT-{id}` until issued). **Issuing** an invoice reserves the next
sequential number for that entity + period (`libs/Invoicing.php:
next_invoice_number()`), using an Indian financial-year period (Apr–Mar) for
`IN_GST` entities and a calendar year for everyone else.

Once issued, an invoice cannot be edited or its number reused — that's
deliberate. If something's wrong with an issued invoice, **void** it instead
of deleting it: the number stays reserved and shows as void, keeping the
numbering gapless. Both Indian GST and Saudi ZATCA-style regimes expect
invoice numbers to never have unexplained gaps, so "delete the mistake and
try again" isn't safe once a number has been issued. Only draft invoices can
be deleted outright.

## 4. PDF downloads (optional dependency)

Invoice PDFs (`libs/InvoicePdf.php` + `includes/invoice_template.php`) are
rendered with [dompdf](https://github.com/dompdf/dompdf). This is the
**one** place in the codebase that uses Composer — everything else is
deliberately dependency-free. If dompdf isn't installed, the Billing page
shows a banner explaining that and everything else (creating, issuing,
voiding invoices) still works; you just can't download/print a PDF yet.

To enable it:

```
cd C:\xampp\htdocs\lops2
composer require dompdf/dompdf
```

`libs/InvoicePdf.php` checks for the Composer autoloader itself
(`invoice_pdf_engine_ready()`), so no other code needs to change.

## 5. Existing install?

If you're adding billing to an install that predates this module, run
`database/migrations/006_billing_module.sql` (it's idempotent — safe even if
you already have the tables).
