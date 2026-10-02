<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Monitoring/Models/ErrorGroup.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Monitoring\Models\ErrorGroup
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-7aed8b4345b6f8ed1704268b020fe9e6831f04f6cebf375238b12de98d1a5356',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Monitoring/Models/ErrorGroup.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Monitoring\\Models',
    'name' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
    'shortName' => 'ErrorGroup',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $fingerprint
 * @property string $exception_class
 * @property string $message_sample
 * @property string|null $module
 * @property string $severity
 * @property Carbon $first_seen_at
 * @property Carbon $last_seen_at
 * @property int $occurrences
 * @property string $status
 * @property int|null $assignee_user_id
 * @property string|null $notes
 * @property Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property Carbon|null $last_alerted_at
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 31,
    'endLine' => 68,
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
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'organization_id\', \'fingerprint\', \'exception_class\', \'message_sample\', \'module\', \'severity\', \'first_seen_at\', \'last_seen_at\', \'occurrences\', \'status\', \'assignee_user_id\', \'notes\', \'resolved_at\', \'resolved_by\', \'last_alerted_at\']',
          'attributes' => 
          array (
            'startLine' => 35,
            'endLine' => 35,
            'startTokenPos' => 73,
            'startFilePos' => 931,
            'endTokenPos' => 117,
            'endFilePos' => 1159,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 256,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'auditExclude' => 
      array (
        'declaringClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
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
          'code' => '[\'last_seen_at\', \'occurrences\', \'message_sample\', \'last_alerted_at\', \'fingerprint\', \'exception_class\', \'module\', \'severity\', \'first_seen_at\', \'organization_id\']',
          'attributes' => 
          array (
            'startLine' => 38,
            'endLine' => 38,
            'startTokenPos' => 130,
            'startFilePos' => 1266,
            'endTokenPos' => 159,
            'endFilePos' => 1425,
          ),
        ),
        'docComment' => '/** Occurrence bookkeeping is not an administrative change. */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 197,
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
        'startLine' => 40,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Monitoring\\Models',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'currentClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'aliasName' => NULL,
      ),
      'logs' => 
      array (
        'name' => 'logs',
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
        'docComment' => '/** @return HasMany<ErrorLog, $this> */',
        'startLine' => 46,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Monitoring\\Models',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'currentClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
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
        'namespace' => 'App\\Modules\\Monitoring\\Models',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'currentClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
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
        'namespace' => 'App\\Modules\\Monitoring\\Models',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'currentClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'aliasName' => NULL,
      ),
      'writeAudit' => 
      array (
        'name' => 'writeAudit',
        'parameters' => 
        array (
          'action' => 
          array (
            'name' => 'action',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'string',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'changes' => 
          array (
            'name' => 'changes',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionUnionType',
              'data' => 
              array (
                'types' => 
                array (
                  0 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'null',
                      'isIdentifier' => true,
                    ),
                  ),
                ),
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 61,
            'endLine' => 61,
            'startColumn' => 51,
            'endColumn' => 65,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 61,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Monitoring\\Models',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
        'currentClassName' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
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