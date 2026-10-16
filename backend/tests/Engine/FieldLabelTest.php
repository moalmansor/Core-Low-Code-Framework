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

it('names the values of a lookup preview by their field labels, never by their keys', function () {
    [, $col] = createForm($this, 'centers', 'collection');
    $code = fieldDoc('cost_code', 'text', null, ['i18n' => ['label' => ['en' => 'Cost code', 'ar' => 'رمز التكلفة']]]);
    $name = fieldDoc('name', 'text');
    saveDraft($this, $col, [], [$code, $name])->assertOk();
    expect(publish($this, $col)['status'])->toBe('applied');
    $this->postJson("/api/v1/r/{$col}", ['values' => ['cost_code' => 'FIN-1', 'name' => 'Finance']])->assertCreated();

    [, $form] = createForm($this, 'costs');
    $relation = ['uuid' => uid(), 'key' => 'center', 'type' => 'many_to_one', 'target' => $col, 'kind' => 'reference', 'onDelete' => 'restrict', 'display' => $name['uuid'], 'value' => null, 'inverse' => null];
    saveDraft($this, $form, [], [fieldDoc('center', 'lookup', null, ['relation' => $relation['uuid'], 'options' => ['source' => 'collection', 'collection' => $col, 'preview' => [['cost_code']]]])], [$relation])->assertOk();
    expect(publish($this, $form)['status'])->toBe('applied');

    $item = $this->getJson("/api/v1/r/{$form}/options/center")->assertOk()->json('data.0');
    expect($item['preview'])->toBe(['cost_code' => 'FIN-1'])
        ->and($item['previewLabels'])->toBe(['cost_code' => 'Cost code']);
});
