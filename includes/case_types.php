<?php
/**
 * Document type vocabulary for matter (case) documents — kept separate
 * from client_doc_types() in client_types.php since the two lists serve
 * different purposes: that one is KYC/onboarding paperwork, this one is
 * the working file of a legal matter.
 */

function case_doc_types(): array
{
    return [
        'Pleading', 'Evidence / Exhibit', 'Correspondence', 'Contract / Agreement',
        'Order / Judgment', 'Notice', 'Affidavit', 'Power of Attorney',
        'Invoice / Receipt', 'Research Note', 'Other',
    ];
}
