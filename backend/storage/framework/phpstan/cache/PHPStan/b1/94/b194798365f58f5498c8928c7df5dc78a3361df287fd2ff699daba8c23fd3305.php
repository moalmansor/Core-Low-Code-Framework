<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Models/User.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Identity\Models\User
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-cdc09c7433cb3289d03874172597d20f6e12fe7db9a1ba85a5cbdf0336ad97c3',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Identity\\Models\\User',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Models/User.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Identity\\Models',
    'name' => 'App\\Modules\\Identity\\Models\\User',
    'shortName' => 'User',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $uuid
 * @property int $organization_id
 * @property string $name
 * @property string $email
 * @property string|null $username
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property int|null $department_id
 * @property int|null $manager_id
 * @property string|null $job_title
 * @property string|null $phone
 * @property string $status
 * @property string $auth_source
 * @property string|null $external_subject
 * @property array<string, mixed>|null $attributes
 * @property Carbon|null $two_factor_confirmed_at
 * @property Carbon|null $password_changed_at
 * @property Carbon|null $last_login_at
 * @property int $failed_login_count
 * @property Carbon|null $locked_until
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 46,
    'endLine' => 136,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Foundation\\Auth\\User',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Modules\\Audit\\Auditable',
      1 => 'App\\Support\\Models\\BelongsToOrganization',
      2 => 'App\\Support\\Models\\HasStableUuid',
      3 => 'Illuminate\\Notifications\\Notifiable',
      4 => 'Illuminate\\Database\\Eloquent\\SoftDeletes',
      5 => 'App\\Support\\Models\\TracksActor',
      6 => 'Laravel\\Fortify\\TwoFactorAuthenticatable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'dateFormat' => 
      array (
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'name' => 'dateFormat',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'Y-m-d H:i:s.u\'',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 132,
            'startFilePos' => 1701,
            'endTokenPos' => 132,
            'endFilePos' => 1715,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 44,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'name\', \'email\', \'username\', \'department_id\', \'manager_id\', \'job_title\', \'phone\', \'status\', \'auth_source\', \'external_subject\', \'attributes\']',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 141,
            'startFilePos' => 1745,
            'endTokenPos' => 173,
            'endFilePos' => 1885,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 168,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'hidden' => 
      array (
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'name' => 'hidden',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'password\', \'remember_token\', \'two_factor_secret\', \'two_factor_recovery_codes\']',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 184,
            'startFilePos' => 1942,
            'endTokenPos' => 195,
            'endFilePos' => 2021,
          ),
        ),
        'docComment' => '/** @var list<string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 105,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'auditExclude' => 
      array (
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'name' => 'auditExclude',
        'modifiers' => 2,
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
          'code' => '[\'last_login_at\', \'last_login_ip\', \'failed_login_count\', \'remember_token\']',
          'attributes' => 
          array (
            'startLine' => 58,
            'endLine' => 58,
            'startTokenPos' => 208,
            'startFilePos' => 2129,
            'endTokenPos' => 219,
            'endFilePos' => 2202,
          ),
        ),
        'docComment' => '/** Bookkeeping columns that are not administrative changes. */',
        'attributes' => 
        array (
        ),
        'startLine' => 58,
        'endLine' => 58,
        'startColumn' => 5,
        'endColumn' => 111,
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
        'startLine' => 60,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
        'aliasName' => NULL,
      ),
      'roles' => 
      array (
        'name' => 'roles',
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
        'docComment' => '/** @return BelongsToMany<Role, $this> */',
        'startLine' => 76,
        'endLine' => 80,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
        'aliasName' => NULL,
      ),
      'department' => 
      array (
        'name' => 'department',
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
        'startLine' => 83,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
        'aliasName' => NULL,
      ),
      'preference' => 
      array (
        'name' => 'preference',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Illuminate\\Database\\Eloquent\\Relations\\HasOne',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return HasOne<UserPreference, $this> */',
        'startLine' => 89,
        'endLine' => 92,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
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
        'startLine' => 95,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
        'aliasName' => NULL,
      ),
      'activeRoles' => 
      array (
        'name' => 'activeRoles',
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
        'docComment' => '/** Roles whose validity window includes now. */',
        'startLine' => 101,
        'endLine' => 108,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
        'aliasName' => NULL,
      ),
      'roleKeys' => 
      array (
        'name' => 'roleKeys',
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
        'docComment' => '/** @return list<string> */',
        'startLine' => 111,
        'endLine' => 114,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
        'aliasName' => NULL,
      ),
      'requiresTwoFactor' => 
      array (
        'name' => 'requiresTwoFactor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** Whether any active role requires two-factor authentication (§2, §5). */',
        'startLine' => 117,
        'endLine' => 120,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
        'aliasName' => NULL,
      ),
      'isLocked' => 
      array (
        'name' => 'isLocked',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 122,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
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
        'startLine' => 127,
        'endLine' => 130,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
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
        'startLine' => 132,
        'endLine' => 135,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Models',
        'declaringClassName' => 'App\\Modules\\Identity\\Models\\User',
        'implementingClassName' => 'App\\Modules\\Identity\\Models\\User',
        'currentClassName' => 'App\\Modules\\Identity\\Models\\User',
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