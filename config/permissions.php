<?php

return [
    /**
     * Comma-separated user IDs dari .env SUPERUSER_IDS
     * contoh: "2542,1001,1002"
     */
    'superuser_ids' => array_values(array_unique(array_filter(
        array_map(
            fn ($v) => (int) $v,
            preg_split('/\s*,\s*/', (string) env('SUPERUSER_IDS', ''), -1, PREG_SPLIT_NO_EMPTY)
        )
    ))),

    /**
     * Mask bypass untuk superuser.
     * Default 31 (create+read+update+delete+approve)
     */
    'superuser_action_mask' => (int) env('SUPERUSER_ACTION_MASK', 31),
];


