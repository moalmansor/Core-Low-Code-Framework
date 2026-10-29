<?php

declare(strict_types=1);

return [
    'already_running' => 'This policy is already running. Wait for the run to finish.',
    'archive_altered' => 'The archived copy does not match the hash recorded when it was archived, so it was not restored.',
    'archive_missing' => 'The archived copy is no longer in cold storage.',
    'audit_archive_only' => 'Audit log entries can only be archived. They are never deleted.',
    'audit_chain_broken' => 'The audit log of :month was not archived: :count entries do not match their hash. Run the audit chain verification.',
    'bad_application' => 'Choose an application that exists.',
    'bad_cron' => 'Enter a valid schedule.',
    'bad_data_class' => 'Choose what this policy applies to.',
    'bad_date_basis' => 'Only record policies can count from the last change or the final status.',
    'bad_days' => 'Enter a number of days between 1 and 36,500.',
    'bad_disk' => 'Choose a cold storage location.',
    'bad_end_action' => 'Choose what happens at the end of the retention period.',
    'bad_form' => 'Choose a published form.',
    'changed_meanwhile' => 'Someone else changed this meanwhile. The latest version has been loaded.',
    'form_gone' => 'The form of this record is no longer published.',
    'has_runs' => 'This policy has already run, so it is kept for its history. Switch it off instead.',
    'hold_already_lifted' => 'This hold has already been lifted.',
    'hold_scope_required' => 'Choose a record or set a condition for the hold.',
    'name_required' => 'Enter a name.',
    'not_restorable' => 'This archive entry cannot be restored.',
    'pd' => [
        'anonymized_user' => 'Anonymized user',
        'not_awaiting' => 'This request is not waiting for review.',
        'subject_required' => 'Enter at least one way to identify the person: an e-mail address, an identifier, a name or a user.',
    ],
    'quota_bad_limit' => 'Enter a hard limit in MB.',
    'quota_bad_target' => 'Choose what this quota applies to.',
    'quota_bad_warn' => 'The warning threshold must be at least 1 MB and no more than the hard limit.',
    'quota_duplicate' => 'There is already a quota for this.',
    'quota_exceeded' => 'Storage is full: this file would pass the storage quota. Ask an administrator for more space.',
    'restore_exists' => 'A record with the same identity exists already, so the archived one was not restored.',
    'restore_wrong_form' => 'This archived copy belongs to another form.',
    'some_failed' => ':count item(s) could not be processed. See Error Monitoring.',
];
