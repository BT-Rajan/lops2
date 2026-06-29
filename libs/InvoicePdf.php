<?php
/**
 * LegalOps — invoice PDF renderer.
 *
 * Thin wrapper around dompdf (Composer-only, no framework). This is
 * the one new dependency the invoicing feature needs:
 *
 *   composer require dompdf/dompdf
 *
 * If vendor/autoload.php isn't present yet, render_invoice_pdf() throws
 * a clear RuntimeException — billing.php catches it and shows a flash
 * message instead of a blank screen.
 */

require_once __DIR__ . '/Invoicing.php';

function dompdf_autoload_path(): string
{
    return __DIR__ . '/../vendor/autoload.php';
}

function invoice_pdf_engine_ready(): bool
{
    return is_file(dompdf_autoload_path());
}

/**
 * Render one invoice (header row from legalops_invoices + its line
 * items + its billing entity row) to PDF bytes.
 *
 * @param array $invoice Row from legalops_invoices, tax_breakdown still JSON-encoded
 * @param array $items   Rows from legalops_invoice_items, in display order
 * @param array $entity  Row from legalops_billing_entities
 */
function render_invoice_pdf(array $invoice, array $items, array $entity): string
{
    if (!invoice_pdf_engine_ready()) {
        throw new RuntimeException(
            'PDF engine not installed yet. Run "composer require dompdf/dompdf" in the legalops folder, ' .
            'then try again.'
        );
    }

    require_once dompdf_autoload_path();

    $profile = tax_profile($invoice['tax_profile_key']) ?? [
        'label' => $invoice['tax_profile_key'], 'reg_label' => null, 'needs_hsn' => false, 'declaration' => null,
    ];
    $breakdown = is_string($invoice['tax_breakdown'])
        ? (json_decode($invoice['tax_breakdown'], true) ?: [])
        : ($invoice['tax_breakdown'] ?? []);

    $html = render_invoice_html($invoice, $items, $entity, $profile, $breakdown);

    $dompdf = new \Dompdf\Dompdf([
        'isRemoteEnabled' => false, // no external logo/image fetches — keeps this safe & fast
        'defaultPaperSize' => 'A4',
    ]);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    return $dompdf->output();
}

/** Builds the HTML string that gets fed to dompdf — kept in its own template file. */
function render_invoice_html(array $invoice, array $items, array $entity, array $profile, array $breakdown): string
{
    ob_start();
    require __DIR__ . '/../includes/invoice_template.php';
    return ob_get_clean();
}
