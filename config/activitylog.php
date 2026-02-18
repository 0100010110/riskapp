<?php

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recorded activities older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => (int) env('ACTIVITY_LOGGER_DELETE_OLDER_THAN_DAYS', 365),

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     *
     * Recommendation: set to something app-specific, e.g. "risk".
     */
    'default_log_name' => env('ACTIVITY_LOGGER_DEFAULT_LOG_NAME', 'risk'),

    /*
     * You can specify an auth driver (guard) here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     *
     * Examples:
     * - null (default): follow current auth driver
     * - 'web': typical Laravel session guard
     * - 'filament': if your app uses a dedicated Filament guard
     */
    'default_auth_driver' => env('ACTIVITY_LOGGER_AUTH_DRIVER', null),

    /*
     * If set to true, the subject returns soft deleted models.
     * Useful for audits (so a deleted subject can still be resolved).
     */
    'subject_returns_soft_deleted_models' => (bool) env('ACTIVITY_LOGGER_SUBJECT_RETURNS_SOFT_DELETED', true),

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * This is the name of the table that will be created by the migration and
     * used by the Activity model shipped with this package.
     */
    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    /*
     * This is the database connection that will be used by the migration and
     * the Activity model shipped with this package. In case it's not set
     * Laravel's database.default will be used instead.
     */
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION', null),
];
