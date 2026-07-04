<?php
/**
 * Reference lists for the "Court & proceedings" panel on a matter.
 * Kept as plain arrays (not DB tables) so they're easy to extend —
 * mirrors the pattern in libs/client_types.php.
 */

/** Court / forum types as they actually appear across Indian practice. */
function court_types(): array
{
    return [
        'Supreme Court', 'High Court', 'District Court', 'Sessions Court',
        'Magistrate Court', 'Family Court', 'Consumer Forum/Commission',
        'Labour Court / Industrial Tribunal', 'NCLT/NCLAT', 'Arbitration',
        'Revenue Court', 'Tribunal (Other)', 'Other',
    ];
}

/** Stage of proceedings — deliberately generic across civil/criminal/appellate matters. */
function case_stages(): array
{
    return [
        'Pre-filing', 'Filed', 'Notice/Summons Issued', 'Admission',
        'Written Statement/Reply', 'Framing of Issues', 'Evidence',
        'Arguments', 'Judgment Reserved', 'Judgment/Order Passed',
        'Disposed', 'In Appeal', 'Execution',
    ];
}

/** Relationship types between two matters. */
function case_link_types(): array
{
    return [
        'connected' => 'Connected matter',
        'appeal_of' => 'Appeal of another matter',
    ];
}

function case_link_type_label(string $type): string
{
    return case_link_types()[$type] ?? $type;
}
