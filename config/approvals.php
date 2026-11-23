<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Approval cutoff timestamps
    |--------------------------------------------------------------------------
    |
    | Unix timestamps used to decide which tickets appear in the pending-review
    | badge and whether old tickets are hidden from the subordinate review list.
    | These replace the legacy application_settings lookup.
    |
    */

    'badge_cutoff' => (int) env('APPROVAL_BADGE_CUTOFF', 1784553600),

    'hide_old_cutoff' => (int) env('APPROVAL_HIDE_OLD_CUTOFF', 1784692800),
];
