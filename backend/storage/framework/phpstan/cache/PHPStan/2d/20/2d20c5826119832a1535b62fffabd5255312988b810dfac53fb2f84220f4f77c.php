<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/EgressAllowlistEntry.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Core\Models\EgressAllowlistEntry
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-2079c58eca42391f200448f2e992b60b26906a619d374dfa8b55d6ea571d793d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/Models/EgressAllowlistEntry.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Core\\Models',
    'name' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
    'shortName' => 'EgressAllowlistEntry',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $uuid
 * @property string $host_pattern
 * @property list<int> $ports
 * @property bool $allow_http
 * @property string|null $description
 * @property bool $is_active
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 33,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Models\\BaseModel',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Support\\Models\\BelongsToOrganization',
      1 => 'App\\Support\\Models\\HasStableUuid',
      2 => 'App\\Support\\Models\\TracksActor',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'table' => 
      array (
        'declaringClassName' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'egress_allowlist\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 66,
            'startFilePos' => 576,
            'endTokenPos' => 66,
            'endFilePos' => 593,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
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
        'declaringClassName' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'host_pattern\', \'ports\', \'allow_http\', \'description\', \'is_active\']',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 75,
            'startFilePos' => 623,
            'endTokenPos' => 89,
            'endFilePos' => 689,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 94,
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
        'startLine' => 29,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Core\\Models',
        'declaringClassName' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
        'implementingClassName' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
        'currentClassName' => 'App\\Modules\\Core\\Models\\EgressAllowlistEntry',
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