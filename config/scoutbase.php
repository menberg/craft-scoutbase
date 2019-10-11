<?php
/*
 * Config scoutbase.php
 *
 * This file exists only as a template for the Scoutbase settings.
 * It does nothing on its own.
 *
 * Don't edit this file, instead copy it to 'craft/config' as 'scoutbase.php'
 * and make your changes there to override default settings.
 *
 * Once copied to 'craft/config', this file will be multi-environment aware as
 * well, so you can have different settings groups for each environment, just as
 * you do for 'general.php'
 */

return [
    /*
     * Scoutbase listens to numerous Element events to keep them updated in
     * their respective indices. You can disable these and update
     * your indices manually using the commands.
     */
    'sync' => true,

    /*
     * By default Scoutbase handles all indexing in a queued job, you can
     * disable this so the indices are updated as soon as the elements are
     * updated.
     */
    'queue' => true,

    /*
     * The connection timeout (in seconds), increase this only if necessary
     */
    'connect_timeout' => 1,

    /*
     * The batch size Scoutbase uses when importing a large amount of elements
     */
    'batch_size' => 1000,

    /*
     * The path to the Google service account credentials
     */
    'application_credentials' => '$GOOGLE_APPLICATION_CREDENTIALS',

    /*
    * The URL to the Firestore database
    */
    'project_id' => '$FIREBASE_PROJECT_ID',

    /*
     * A collection of indices that Scoutbase should sync to, these can be set
     * by using the \plansequenz\scoutbase\ScoutbaseIndex::create('IndexName')
     * command. Each index should define an ElementType, criteria and a
     * transformer.
     */
    'indices'       => [],
];
