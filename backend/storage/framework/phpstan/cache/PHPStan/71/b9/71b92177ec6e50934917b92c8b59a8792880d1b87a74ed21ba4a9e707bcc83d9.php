<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Sessions/SessionHandler.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Identity\Sessions\SessionHandler
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-d6c10a401c9ec3c3883e03aa7e70b462aaf47173731ab700050346c04634c0c9',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Identity\\Sessions\\SessionHandler',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Sessions/SessionHandler.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Identity\\Sessions',
    'name' => 'App\\Modules\\Identity\\Sessions\\SessionHandler',
    'shortName' => 'SessionHandler',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Database sessions with the extra columns of the `sessions` table
 * (architecture §10.4): guard, creation time, and absolute expiry. Sessions are
 * listed and revoked through the API (specification §5).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 34,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Session\\DatabaseSessionHandler',
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
    ),
    'immediateMethods' => 
    array (
      'performInsert' => 
      array (
        'name' => 'performInsert',
        'parameters' => 
        array (
          'sessionId' => 
          array (
            'name' => 'sessionId',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 38,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 50,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/** @param array<string, mixed> $payload */',
        'startLine' => 19,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Identity\\Sessions',
        'declaringClassName' => 'App\\Modules\\Identity\\Sessions\\SessionHandler',
        'implementingClassName' => 'App\\Modules\\Identity\\Sessions\\SessionHandler',
        'currentClassName' => 'App\\Modules\\Identity\\Sessions\\SessionHandler',
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