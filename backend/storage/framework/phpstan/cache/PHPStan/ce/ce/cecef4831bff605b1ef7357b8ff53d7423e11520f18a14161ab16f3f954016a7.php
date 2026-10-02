<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Organization/Models/Department.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Organization\Models\Department
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-770c3028d269dba7aa1a4ee16e4798a6394a0ff768a91cdd80fa9106cdc4dbd2',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Organization\\Models\\Department',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Organization/Models/Department.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Organization\\Models',
    'name' => 'App\\Modules\\Organization\\Models\\Department',
    'shortName' => 'Department',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $uuid
 * @property int|null $parent_id
 * @property string $code
 * @property int|null $manager_user_id
 * @property int $depth
 * @property int $sort_order
 * @property bool $is_active
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 80,
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
      4 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      5 => 'App\\Support\\Models\\TracksActor',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'translatable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
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
            'startLine' => 33,
            'endLine' => 33,
            'startTokenPos' => 109,
            'startFilePos' => 939,
            'endTokenPos' => 111,
            'endFilePos' => 946,
          ),
        ),
        'docComment' => '/** @var list<string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
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
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'parent_id\', \'code\', \'manager_user_id\', \'depth\', \'sort_order\', \'is_active\']',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 120,
            'startFilePos' => 976,
            'endTokenPos' => 137,
            'endFilePos' => 1051,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 103,
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
        'startLine' => 37,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'aliasName' => NULL,
      ),
      'parent' => 
      array (
        'name' => 'parent',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return BelongsTo<Department, $this> */',
        'startLine' => 43,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'aliasName' => NULL,
      ),
      'children' => 
      array (
        'name' => 'children',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return HasMany<Department, $this> */',
        'startLine' => 49,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'aliasName' => NULL,
      ),
      'manager' => 
      array (
        'name' => 'manager',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\BelongsTo',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return BelongsTo<User, $this> */',
        'startLine' => 55,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'aliasName' => NULL,
      ),
      'members' => 
      array (
        'name' => 'members',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasMany',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return HasMany<User, $this> */',
        'startLine' => 61,
        'endLine' => 64,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
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
        'startLine' => 66,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
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
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
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
        'startLine' => 76,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Organization\\Models',
        'declaringClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'implementingClassName' => 'App\\Modules\\Organization\\Models\\Department',
        'currentClassName' => 'App\\Modules\\Organization\\Models\\Department',
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