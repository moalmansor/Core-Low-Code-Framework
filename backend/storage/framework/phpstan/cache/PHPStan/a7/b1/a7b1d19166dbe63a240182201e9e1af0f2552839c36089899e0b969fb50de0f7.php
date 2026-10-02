<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Access/Models/Role.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Access\Models\Role
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-663705be7816d5bd7e965882fae2f8afd178c6180d2920bebe76607d0c55a7a0',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Access\\Models\\Role',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Access/Models/Role.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Access\\Models',
    'name' => 'App\\Modules\\Access\\Models\\Role',
    'shortName' => 'Role',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $uuid
 * @property string $key
 * @property bool $is_system
 * @property string $audience
 * @property bool $requires_2fa
 * @property bool $is_admin_role
 * @property int $sort_order
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 26,
    'endLine' => 60,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Models\\BaseModel',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Modules\\Audit\\Auditable',
      1 => 'App\\Support\\Models\\BelongsToOrganization',
      2 => 'App\\Support\\Models\\HasStableUuid',
      3 => 'App\\Modules\\Core\\I18n\\HasTranslations',
      4 => 'App\\Support\\Models\\TracksActor',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'translatable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Role',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Role',
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
          'code' => '[\'name\', \'description\']',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 96,
            'startFilePos' => 818,
            'endTokenPos' => 101,
            'endFilePos' => 840,
          ),
        ),
        'docComment' => '/** @var list<string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 57,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Role',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Role',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'key\', \'is_system\', \'audience\', \'requires_2fa\', \'is_admin_role\', \'sort_order\']',
          'attributes' => 
          array (
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 110,
            'startFilePos' => 870,
            'endTokenPos' => 127,
            'endFilePos' => 948,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 106,
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
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Role',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Role',
        'currentClassName' => 'App\\Modules\\Access\\Models\\Role',
        'aliasName' => NULL,
      ),
      'users' => 
      array (
        'name' => 'users',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsToMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return BelongsToMany<User, $this> */',
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Role',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Role',
        'currentClassName' => 'App\\Modules\\Access\\Models\\Role',
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
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Role',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Role',
        'currentClassName' => 'App\\Modules\\Access\\Models\\Role',
        'aliasName' => NULL,
      ),
      'auditCategory' => 
      array (
        'name' => 'auditCategory',
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
        'startLine' => 51,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Role',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Role',
        'currentClassName' => 'App\\Modules\\Access\\Models\\Role',
        'aliasName' => NULL,
      ),
      'auditType' => 
      array (
        'name' => 'auditType',
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
        'startLine' => 56,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Role',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Role',
        'currentClassName' => 'App\\Modules\\Access\\Models\\Role',
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