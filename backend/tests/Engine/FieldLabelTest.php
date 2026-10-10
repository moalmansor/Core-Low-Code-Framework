<?php

declare(strict_types=1);

require_once __DIR__.'/helpers.php';

beforeEach(function () {
    $this->completeSetup();
    $this->flushSession();
    $this->actingAs($this->superAdmin(), 'web');
});

it('reports a field without a label in the default language, so records never show its key', function () {
    [, $form] = createForm($this, 'labels');
    $unlabelled = static function (array $field, array $label = []): array {
        $field['i18n']['label'] = (object) $label;

        return $field;
    };
    $problems = saveDraft($this, $form, [], [
        $unlabelled(fieldDoc('code', 'text'), ['ar' => 'الرمز']),
        fieldDoc('name', 'text'),
        $unlabelled(fieldDoc('token', 'hidden')),
        $unlabelled(fieldDoc('intro', 'heading')),
    ])->assertOk()->json('data.problems');

    expect(array_column($problems, 'code'))->toBe(['label_missing'])
        ->and($problems[0]['path'])->toBe('fields.0.i18n.label');
});
