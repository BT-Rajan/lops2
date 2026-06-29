<?php
/**
 * LegalOps — invoicing engine.
 *
 * Plain functions, same style as config/bootstrap.php — no framework,
 * no classes, nothing beyond what billing.php / invoice_download.php
 * need. Tax *rates* live in config/tax_profiles.php; this file is the
 * arithmetic and the numbering.
 */

require_once __DIR__ . '/../config/tax_profiles.php';

/** All tax profiles, keyed by profile key. */
function tax_profiles(): array
{
    static $profiles = null;
    if ($profiles === null) {
        $profiles = require __DIR__ . '/../config/tax_profiles.php';
    }
    return $profiles;
}

function tax_profile(string $key): ?array
{
    return tax_profiles()[$key] ?? null;
}

/**
 * Suggest a tax profile key for a new invoice, based on the billing
 * entity and the client's location. This is a DEFAULT only — the
 * billing.php form always lets the user override it before saving.
 */
function suggest_tax_profile(array $entity, string $clientCountry, ?string $clientState = null): string
{
    $clientCountry = strtoupper(trim($clientCountry));

    if ($entity['entity_type'] === 'IN_GST') {
        if ($clientCountry !== '' && $clientCountry !== 'IN') {
            return 'IN_GST_export';
        }
        $sameState = $clientState !== null
            && trim(mb_strtolower($clientState)) === trim(mb_strtolower($entity['state_or_emirate'] ?? ''));
        return $sameState ? 'IN_GST_domestic' : 'IN_GST_interstate';
    }

    if ($entity['entity_type'] === 'GCC_VAT') {
        if ($clientCountry !== '' && $clientCountry !== $entity['country']) {
            return 'GCC_VAT_zero_rated';
        }
        $byCountry = [
            'AE' => 'AE_VAT',
            'SA' => 'SA_VAT',
            'BH' => 'BH_VAT',
            'OM' => 'OM_VAT',
        ];
        return $byCountry[$entity['country']] ?? 'NO_VAT';
    }

    return 'NO_VAT';
}

/**
 * Compute per-line and invoice-level totals for a given tax profile.
 *
 * $items: list of ['description'=>, 'hsn_sac'=>, 'quantity'=>, 'unit_price'=>, 'tax_rate'=>(optional override)]
 * Returns ['items' => [...computed...], 'subtotal'=>, 'tax_total'=>, 'grand_total'=>, 'tax_breakdown'=>[...]]
 *
 * The profile's `split` defines the *components* (e.g. CGST 9% + SGST
 * 9%); a line's effective rate is the sum of those components unless
 * the line itself carries its own `tax_rate` (rare — kept for
 * flexibility, e.g. a zero-rated line inside an otherwise taxed
 * invoice).
 */
function compute_invoice_totals(array $items, string $taxProfileKey): array
{
    $profile = tax_profile($taxProfileKey);
    if ($profile === null) {
        throw new InvalidArgumentException("Unknown tax profile: {$taxProfileKey}");
    }

    $profileRate = array_sum($profile['split']);

    $subtotal = 0.0;
    $taxTotal = 0.0;
    $computedItems = [];
    $breakdown = [];
    foreach ($profile['split'] as $component => $rate) {
        $breakdown[$component] = ['rate' => $rate, 'amount' => 0.0];
    }

    foreach ($items as $item) {
        $qty = (float)($item['quantity'] ?? 1);
        $unitPrice = (float)($item['unit_price'] ?? 0);
        $rate = isset($item['tax_rate']) && $item['tax_rate'] !== '' ? (float)$item['tax_rate'] : $profileRate;

        $lineSubtotal = round($qty * $unitPrice, 2);
        $lineTax = round($lineSubtotal * $rate / 100, 2);
        $lineTotal = round($lineSubtotal + $lineTax, 2);

        $subtotal += $lineSubtotal;
        $taxTotal += $lineTax;

        // Spread this line's tax across the profile's components
        // proportionally (e.g. CGST 9 + SGST 9 on an 18% line splits
        // the line's tax exactly in half between the two).
        if ($profileRate > 0) {
            foreach ($profile['split'] as $component => $rate2) {
                $share = round($lineTax * ($rate2 / $profileRate), 2);
                $breakdown[$component]['amount'] += $share;
            }
        }

        $computedItems[] = [
            'description'   => (string)($item['description'] ?? ''),
            'hsn_sac'       => $item['hsn_sac'] ?? null,
            'quantity'      => $qty,
            'unit_price'    => $unitPrice,
            'tax_rate'      => $rate,
            'line_subtotal' => $lineSubtotal,
            'line_tax'      => $lineTax,
            'line_total'    => $lineTotal,
        ];
    }

    foreach ($breakdown as $component => $row) {
        $breakdown[$component]['amount'] = round($row['amount'], 2);
    }

    return [
        'items'         => $computedItems,
        'subtotal'      => round($subtotal, 2),
        'tax_total'     => round($taxTotal, 2),
        'grand_total'   => round($subtotal + $taxTotal, 2),
        'tax_breakdown' => $breakdown,
    ];
}

/**
 * The numbering "period" for an entity on a given date — Indian
 * financial year (Apr–Mar) for IN_GST entities, calendar year for
 * everyone else. Drives gapless, jurisdiction-correct numbering.
 */
function invoice_period_key(array $entity, ?string $date = null): string
{
    $ts = $date ? strtotime($date) : time();
    $year = (int)date('Y', $ts);
    $month = (int)date('n', $ts);

    if ($entity['entity_type'] === 'IN_GST') {
        $startYear = $month >= 4 ? $year : $year - 1;
        return substr((string)$startYear, 2, 2) . substr((string)($startYear + 1), 2, 2); // e.g. "2526"
    }

    return (string)$year;
}

/**
 * Atomically reserve the next sequential invoice number for an entity
 * + period. Safe under concurrent requests: the UPDATE either claims
 * an existing row (LAST_INSERT_ID() trick) or, if no row exists yet,
 * an INSERT IGNORE races safely against the UNIQUE(entity, period)
 * key and the loop retries the UPDATE.
 */
function next_invoice_number(PDO $pdo, int $entityId, string $periodKey, string $prefix): string
{
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $stmt = $pdo->prepare(
            'UPDATE legalops_invoice_number_sequences
                SET last_number = LAST_INSERT_ID(last_number + 1)
              WHERE billing_entity_id = ? AND period_key = ?'
        );
        $stmt->execute([$entityId, $periodKey]);

        if ($stmt->rowCount() > 0) {
            $next = (int)$pdo->lastInsertId();
            return sprintf('%s/%s/%04d', $prefix, $periodKey, $next);
        }

        $insert = $pdo->prepare(
            'INSERT IGNORE INTO legalops_invoice_number_sequences (billing_entity_id, period_key, last_number)
             VALUES (?, ?, 0)'
        );
        $insert->execute([$entityId, $periodKey]);
        // loop back around and the UPDATE above will now find the row
    }

    throw new RuntimeException('Could not reserve an invoice number — please try again.');
}

/** Simple money formatter for on-screen display (PDF template formats independently). */
function format_money(float $amount, string $currency): string
{
    return $currency . ' ' . number_format($amount, 2);
}
