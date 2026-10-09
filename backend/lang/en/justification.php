<?php

declare(strict_types=1);

return [
    'required' => 'Explain why you are making this change.',
    'invalid' => 'The justification is not complete.',
    'text_required' => 'Enter a reason.',
    'text_too_short' => 'Enter at least :min characters.',
    'text_too_long' => 'Use at most :max characters.',
    'code_required' => 'Choose a reason code.',
    'code_not_accepted' => 'A reason code is not used here.',
    'unknown_code' => 'Choose one of the listed reason codes.',
    'note_required' => 'This reason code needs a note.',
    'attachments_required' => 'Attach at least one supporting file.',
    'attachments_not_accepted' => 'Attachments are not used here.',
    'too_many_attachments' => 'Attach at most :max files.',
    'file_not_allowed' => 'The file :name is not an allowed type or is too large.',
    'unknown_target' => 'Choose a group, field, status or transition of this form.',
    'no_target' => 'This kind of rule does not take a target.',
    'unknown_subject' => 'Choose an existing role, department or user.',
    'level_when_required' => 'Choose the level that applies when the condition holds.',
    'level_when_without_condition' => 'Set a condition before choosing the level that applies when it holds.',
    'invalid_length' => 'The minimum length must not exceed the maximum (0–5000).',
    'unknown_code_set' => 'Choose a reason code list that has active codes.',
    'unknown_collection' => 'Choose a collection.',
    'invalid_max_attachments' => 'Allow between 1 and 20 attachments.',
    'invalid_attachment_rules' => 'The file rules are not valid.',
    'too_many' => 'A form can have at most 500 justification rules.',
    'changed_elsewhere' => 'The justification rules were changed by someone else. Review the latest version and apply your changes again.',
];
