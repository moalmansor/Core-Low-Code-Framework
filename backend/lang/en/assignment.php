<?php

declare(strict_types=1);

return [
    'not_in_queue' => 'This record is not in a queue of yours.',
    'already_claimed' => 'Already claimed by :name.',
    'not_your_claim' => 'Only the person who claimed this record can release it.',
    'claimed_by' => 'This record is claimed by :name; only they can change it now.',
    'approval_not_found' => 'This approval request does not exist.',
    'approval_closed' => 'This approval request has already been decided.',
    'not_an_approver' => 'You are not an approver of this request.',
    'rejection_comment_required' => 'Explain why you reject the request.',
    'inactive_user' => 'Choose an active user.',
    'target_required' => 'Choose who to assign to.',
    'user_field_required' => 'Choose a user field of this form.',
    'invalid_priority' => 'Use a priority between -100 and 100.',
    'self_delegation' => 'A user cannot delegate to themselves.',
    'changed_elsewhere' => 'The assignment rules were changed by someone else. Review the latest version and apply your changes again.',
    'column_record_number' => 'Number',
    'column_created_at' => 'Created',
    'column_updated_at' => 'Updated',
    'column_status' => 'Status',
    'already_decided' => 'You have already decided on this request.',
    'queue_changed' => 'This queue was changed by someone else. Review the latest version and apply your changes again.',
];
