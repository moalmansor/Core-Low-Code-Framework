<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/Organization.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Core\Models\Organization
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-3c22cead76fdc3fefb8df6b0001e96dd446d8fcde48f8073aac2aa19f15a025c',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Core\\Models\\Organization',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/Organization.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Core\\Models',
    'name' => 'App\\Modules\\Core\\Models\\Organization',
    'shortName' => 'Organization',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property bool $is_platform
 * @property string $status
 * @property string $default_locale
 * @property string $timezone
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 39,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Models\\BaseModel',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Support\\Models\\HasStableUuid',
      1 => 'App\\Modules\\Core\\I18n\\HasTranslations',
      2 => 'App\\Support\\Models\\TracksActor',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'translatable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'name' => 'translatable',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[\'name\']',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 70,
            'startFilePos' => 587,
            'endTokenPos' => 72,
            'endFilePos' => 594,
          ),
        ),
        'docComment' => '/** @var list<string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 42,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'parent_id\', \'key\', \'is_platform\', \'status\', \'default_locale\', \'timezone\', \'settings_overrides\']',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 81,
            'startFilePos' => 624,
            'endTokenPos' => 101,
            'endFilePos' => 720,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 124,
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
        'startLine' => 30,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Core\\Models',
        'declaringClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'currentClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'aliasName' => NULL,
      ),
      'translationType' => 
      array (
        'name' => 'translationType',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Core\\Models',
        'declaringClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\Organization',
        'currentClassName' => 'App\\Modules\\Core\\Models\\Organization',
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