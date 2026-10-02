<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/Setting.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Core\Models\Setting
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-d3da932487e4b5c41ca156a6f954389df75e8bddaf1c797c88e2c5e42e0f3ad6',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Core\\Models\\Setting',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/Setting.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Core\\Models',
    'name' => 'App\\Modules\\Core\\Models\\Setting',
    'shortName' => 'Setting',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property string|null $encrypted_value
 * @property bool $is_encrypted
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 31,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Models\\BaseModel',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Support\\Models\\BelongsToOrganization',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Core\\Models\\Setting',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\Setting',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'organization_id\', \'group\', \'key\', \'value\', \'encrypted_value\', \'is_encrypted\', \'updated_by\']',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 50,
            'startFilePos' => 429,
            'endTokenPos' => 70,
            'endFilePos' => 521,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 120,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hidden' => 
      array (
        'declaringClassName' => 'App\\Modules\\Core\\Models\\Setting',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\Setting',
        'name' => 'hidden',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'encrypted_value\']',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 81,
            'startFilePos' => 578,
            'endTokenPos' => 83,
            'endFilePos' => 596,
          ),
        ),
        'docComment' => '/** @var list<string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 44,
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
        'declaringClassName' => 'App\\Modules\\Core\\Models\\Setting',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\Setting',
        'currentClassName' => 'App\\Modules\\Core\\Models\\Setting',
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