<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Audit/Models/AuditLog.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Audit\Models\AuditLog
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-d9d3cbb2767e0c446922ee219abc8c7d17a25984166df9d1921590886a48289b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Audit/Models/AuditLog.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Audit\\Models',
    'name' => 'App\\Modules\\Audit\\Models\\AuditLog',
    'shortName' => 'AuditLog',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Append-only audit entry (specification §4.20, ADR-0011). No code path can
 * update or delete one; corrections are new entries.
 *
 * @property int $id
 * @property Carbon $occurred_at
 * @property int $organization_id
 * @property int $chain_id
 * @property int $chain_seq
 * @property string $event
 * @property string $category
 * @property string|null $object_type
 * @property int|null $object_id
 * @property list<array<string, mixed>>|null $changes
 * @property int|null $actor_user_id
 * @property int|null $subject_user_id
 * @property int|null $on_behalf_of_user_id
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $correlation_id
 * @property array<string, mixed>|null $meta
 * @property string $prev_hash
 * @property string $hash
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 35,
    'endLine' => 51,
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
    ),
    'immediateProperties' => 
    array (
      'timestamps' => 
      array (
        'declaringClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'implementingClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'name' => 'timestamps',
        'modifiers' => 1,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'false',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 50,
            'startFilePos' => 1027,
            'endTokenPos' => 50,
            'endFilePos' => 1031,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'guarded' => 
      array (
        'declaringClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'implementingClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'name' => 'guarded',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 59,
            'startFilePos' => 1060,
            'endTokenPos' => 60,
            'endFilePos' => 1061,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 28,
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
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Audit\\Models',
        'declaringClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'implementingClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'currentClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'aliasName' => NULL,
      ),
      'booted' => 
      array (
        'name' => 'booted',
        'parameters' => 
        array (
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
        'startLine' => 46,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'App\\Modules\\Audit\\Models',
        'declaringClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'implementingClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
        'currentClassName' => 'App\\Modules\\Audit\\Models\\AuditLog',
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