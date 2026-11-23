<?php

return [
    'simulate_mark_missed_failure' => env(
        'CLINIC_SIMULATE_MARK_MISSED_FAILURE',
        false
    ),
    'simulate_lift_bans_failure' => env('CLINIC_SIMULATE_LIFT_BANS_FAILURE', false),
];
