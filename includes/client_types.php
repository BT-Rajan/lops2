<?php
/**
 * Client entity types and the rules that vary by type:
 *  - whether a registration number applies (and what to call it)
 *  - what "leadership" means for that type, and which roles are valid
 *  - whether only one leader can be active at a time (singular) or many
 *    (e.g. a sole proprietor vs. a board of directors)
 *
 * Individual and Family don't get a statutory registration number; every
 * other entity type does, per how each is actually registered in India.
 */

function client_types(): array
{
    return [
        'individual' => [
            'label' => 'Individual',
            'registration_required' => false,
            'registration_label' => null,
            'leadership_label' => 'KYC details',
            'leadership_singular' => true,
            'leadership_roles' => ['Individual'],
        ],
        'family' => [
            'label' => 'Family (HUF)',
            'registration_required' => false,
            'registration_label' => 'HUF PAN reference',
            'leadership_label' => 'Karta',
            'leadership_singular' => true,
            'leadership_roles' => ['Karta'],
        ],
        'proprietorship' => [
            'label' => 'Proprietorship',
            'registration_required' => true,
            'registration_label' => 'Registration no. (GST / Shop Act / MSME)',
            'leadership_label' => 'Proprietor',
            'leadership_singular' => true,
            'leadership_roles' => ['Proprietor'],
        ],
        'partnership' => [
            'label' => 'Partnership',
            'registration_required' => true,
            'registration_label' => 'Firm registration no. (ROF) / LLPIN',
            'leadership_label' => 'Partners',
            'leadership_singular' => false,
            'leadership_roles' => ['Partner', 'Managing Partner', 'Authorized Signatory'],
        ],
        'opc' => [
            'label' => 'One Person Company (OPC)',
            'registration_required' => true,
            'registration_label' => 'CIN',
            'leadership_label' => 'Director & nominee',
            'leadership_singular' => false,
            'leadership_roles' => ['Director', 'Nominee Director'],
        ],
        'private_limited' => [
            'label' => 'Private Limited Company',
            'registration_required' => true,
            'registration_label' => 'CIN',
            'leadership_label' => 'Directors',
            'leadership_singular' => false,
            'leadership_roles' => ['Director', 'Managing Director', 'Authorized Signatory'],
        ],
        'public_limited' => [
            'label' => 'Public Limited Company',
            'registration_required' => true,
            'registration_label' => 'CIN',
            'leadership_label' => 'Directors',
            'leadership_singular' => false,
            'leadership_roles' => ['Director', 'Managing Director', 'Chairperson', 'Authorized Signatory'],
        ],
        'association' => [
            'label' => 'Association / Society',
            'registration_required' => true,
            'registration_label' => 'Registration no. (Societies Registration Act)',
            'leadership_label' => 'Office bearers',
            'leadership_singular' => false,
            'leadership_roles' => ['President', 'Secretary', 'Treasurer', 'Member'],
        ],
        'trust' => [
            'label' => 'Trust',
            'registration_required' => true,
            'registration_label' => 'Registration no. (Indian Trusts Act)',
            'leadership_label' => 'Trustees',
            'leadership_singular' => false,
            'leadership_roles' => ['Managing Trustee', 'Trustee'],
        ],
    ];
}

function client_type_meta(string $type): array
{
    $types = client_types();
    return $types[$type] ?? $types['individual'];
}

function client_type_label(string $type): string
{
    return client_type_meta($type)['label'];
}

/** Document categories offered in the upload form. */
function client_doc_types(): array
{
    return [
        'PAN Card', 'Aadhaar / ID Proof', 'Address Proof', 'Photograph',
        'Registration Certificate', 'Partnership Deed', 'MOA', 'AOA',
        'Trust Deed', 'Board Resolution / Authorization', 'Bank Statement', 'Other',
    ];
}

const CLIENT_ONBOARDING_STAGES = ['draft', 'kyc_pending', 'kyc_verified', 'active', 'inactive'];
const CLIENT_KYC_STAGES = ['pending', 'verified', 'rejected'];
