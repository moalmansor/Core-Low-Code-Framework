<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Access/Models/PermissionAssignment.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Access\Models\PermissionAssignment
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-37314412b1e3420b9d80931ba1896da9eda0af3dd94c17932abae0bb67616235',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Access/Models/PermissionAssignment.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Access\\Models',
    'name' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
    'shortName' => 'PermissionAssignment',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property int $permission_id
 * @property string $subject_type
 * @property int $subject_id
 * @property string $effect
 * @property bool $include_descendants
 * @property Carbon|null $valid_until
 * @property int|null $granted_by
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 38,
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
        'declaringClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'permission_id\', \'subject_type\', \'subject_id\', \'effect\', \'include_descendants\', \'valid_until\', \'granted_by\']',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 60,
            'startFilePos' => 611,
            'endTokenPos' => 80,
            'endFilePos' => 719,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 136,
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
        'startLine' => 28,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'currentClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'aliasName' => NULL,
      ),
      'permission' => 
      array (
        'name' => 'permission',
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
        'docComment' => '/** @return BelongsTo<Permission, $this> */',
        'startLine' => 34,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
        'currentClassName' => 'App\\Modules\\Access\\Models\\PermissionAssignment',
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