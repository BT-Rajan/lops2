<?php
/**
 * LegalOps — tax profiles.
 *
 * One entry per "how is this invoice taxed" scenario. The billing
 * screen uses the entity's country + the client's country/state to
 * SUGGEST a default profile, but the person issuing the invoice can
 * always override it from the dropdown — cross-border place-of-supply
 * rules (especially intra-GCC reverse charge) have enough edge cases
 * that a human in the loop is safer than a fully automatic decision.
 *
 * IMPORTANT — verify before going live:
 *   Rates below are the standard rates as commonly published for each
 *   jurisdiction. Tax law changes; HSN/SAC mapping and registration
 *   thresholds are firm-specific. Have your CA/tax advisor confirm the
 *   rates and wording for your entities before relying on this for
 *   real filings.
 *
 * Each profile:
 *   label        – shown in the dropdown and printed on the PDF
 *   split        – ['CGST' => 9, ...] tax components and their
 *                  rate (%). Empty array = no tax charged.
 *   reg_label    – what to call the tax-registration number on the
 *                  PDF ("GSTIN", "TRN", or null to hide the field)
 *   needs_hsn    – whether line items should show an HSN/SAC column
 *   declaration  – optional fixed statement printed near the totals
 *                  (e.g. the LUT/export declaration India requires)
 */

return [

    // ---- India (GST) --------------------------------------------------
    'IN_GST_domestic' => [
        'label'       => 'GST — intra-state (CGST + SGST)',
        'split'       => ['CGST' => 9, 'SGST' => 9],
        'reg_label'   => 'GSTIN',
        'needs_hsn'   => true,
        'declaration' => null,
    ],
    'IN_GST_interstate' => [
        'label'       => 'GST — inter-state (IGST)',
        'split'       => ['IGST' => 18],
        'reg_label'   => 'GSTIN',
        'needs_hsn'   => true,
        'declaration' => null,
    ],
    'IN_GST_export' => [
        'label'       => 'Export of services (zero-rated, under LUT)',
        'split'       => [],
        'reg_label'   => 'GSTIN',
        'needs_hsn'   => true,
        'declaration' => 'Supply meant for export / supply to SEZ under Letter of Undertaking (LUT), '
                        . 'without payment of integrated tax (Rule 96A of the CGST Rules, 2017).',
    ],

    // ---- GCC — VAT-registered entity issuing locally ------------------
    'AE_VAT' => [
        'label'       => 'UAE VAT (5%)',
        'split'       => ['VAT' => 5],
        'reg_label'   => 'TRN',
        'needs_hsn'   => false,
        'declaration' => null,
    ],
    'SA_VAT' => [
        'label'       => 'Saudi Arabia VAT (15%)',
        'split'       => ['VAT' => 15],
        'reg_label'   => 'TRN / VAT No.',
        'needs_hsn'   => false,
        'declaration' => null,
    ],
    'BH_VAT' => [
        'label'       => 'Bahrain VAT (10%)',
        'split'       => ['VAT' => 10],
        'reg_label'   => 'TRN',
        'needs_hsn'   => false,
        'declaration' => null,
    ],
    'OM_VAT' => [
        'label'       => 'Oman VAT (5%)',
        'split'       => ['VAT' => 5],
        'reg_label'   => 'TRN',
        'needs_hsn'   => false,
        'declaration' => null,
    ],
    'GCC_VAT_zero_rated' => [
        'label'       => 'Zero-rated / export of services (GCC entity)',
        'split'       => ['VAT' => 0],
        'reg_label'   => 'TRN',
        'needs_hsn'   => false,
        'declaration' => 'Zero-rated supply. Confirm place-of-supply treatment with your tax advisor '
                        . 'before relying on this for cross-border GCC billing.',
    ],

    // ---- No VAT in force at the entity's home jurisdiction ------------
    'NO_VAT' => [
        'label'       => 'No VAT applicable',
        'split'       => [],
        'reg_label'   => null,
        'needs_hsn'   => false,
        'declaration' => null,
    ],
];
