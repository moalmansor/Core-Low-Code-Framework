<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/OutboxEvent.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Core\Models\OutboxEvent
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-d400b8d977cb40d31e24afd8ff897c875a46bfd0770eac528f72eed9e2564862',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/OutboxEvent.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Core\\Models',
    'name' => 'App\\Modules\\Core\\Models\\OutboxEvent',
    'shortName' => 'OutboxEvent',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property int $organization_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $correlation_id
 * @property Carbon $available_at
 * @property Carbon|null $dispatched_at
 * @property int $attempts
 * @property string|null $last_error
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 31,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Models\\BaseModel',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'UPDATED_AT' => 
      array (
        'declaringClassName' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'name' => 'UPDATED_AT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 47,
            'startFilePos' => 521,
            'endTokenPos' => 47,
            'endFilePos' => 524,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
    ),
    'immediateProperties' => 
    array (
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'organization_id\', \'event_type\', \'payload\', \'correlation_id\', \'available_at\', \'dispatched_at\', \'attempts\', \'last_error\']',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 56,
            'startFilePos' => 554,
            'endTokenPos' => 79,
            'endFilePos' => 674,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 148,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      'casts' => 
      array (
        'name' => 'casts',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 27,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Core\\Models',
        'declaringClassName' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'currentClassName' => 'App\\Modules\\Core\\Models\\OutboxEvent',
        'aliasName' => NULL,
      ),
    ),
    'traitsData' => 
    array (
      'aliases' => 
      array (
      ),
      'modifiers' => 
      array (
      ),
      'precedences' => 
      array (
      ),
      'hashes' => 
      array (
      ),
    ),
  ),
));