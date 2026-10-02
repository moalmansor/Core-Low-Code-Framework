<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Monitoring/ErrorAlerts.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Monitoring\ErrorAlerts
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-5354e7c61cf8ffd0b2ae412a8940b49551ff26936f7dbb2843d0ea1765a0eb5b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Monitoring/ErrorAlerts.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Monitoring',
    'name' => 'App\\Modules\\Monitoring\\ErrorAlerts',
    'shortName' => 'ErrorAlerts',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * E-mail alerts for new or regressed error groups (specification §4.21),
 * sent to active users holding the configured roles, at most once per
 * cooldown period per group and only at or above the configured severity.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 48,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'ORDER' => 
      array (
        'declaringClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'implementingClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'name' => 'ORDER',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'debug\', \'info\', \'notice\', \'warning\', \'error\', \'critical\', \'alert\', \'emergency\']',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 58,
            'startFilePos' => 566,
            'endTokenPos' => 81,
            'endFilePos' => 646,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 108,
      ),
    ),
    'immediateProperties' => 
    array (
      'settings' => 
      array (
        'declaringClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'implementingClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'name' => 'settings',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Modules\\Core\\Settings\\SettingsService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 33,
        'endColumn' => 74,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'settings' => 
          array (
            'name' => 'settings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Modules\\Core\\Settings\\SettingsService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 22,
            'endLine' => 22,
            'startColumn' => 33,
            'endColumn' => 74,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 78,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Monitoring',
        'declaringClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'implementingClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'currentClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'aliasName' => NULL,
      ),
      'maybeAlert' => 
      array (
        'name' => 'maybeAlert',
        'parameters' => 
        array (
          'group' => 
          array (
            'name' => 'group',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Modules\\Monitoring\\Models\\ErrorGroup',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 32,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'reference' => 
          array (
            'name' => 'reference',
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
            'startLine' => 24,
            'endLine' => 24,
            'startColumn' => 51,
            'endColumn' => 67,
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
        'startLine' => 24,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Monitoring',
        'declaringClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'implementingClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
        'currentClassName' => 'App\\Modules\\Monitoring\\ErrorAlerts',
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