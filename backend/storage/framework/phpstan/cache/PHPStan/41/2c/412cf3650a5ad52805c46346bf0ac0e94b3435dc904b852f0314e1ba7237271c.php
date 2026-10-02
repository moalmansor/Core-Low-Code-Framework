<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Http/Middleware/EnsureTwoFactorEnrolled.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Identity\Http\Middleware\EnsureTwoFactorEnrolled
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-365edab70b780a30680dd3aabf19da5fd8a5ea2b7a0d39f3fbd339132afaf380',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Identity\\Http\\Middleware\\EnsureTwoFactorEnrolled',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Http/Middleware/EnsureTwoFactorEnrolled.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Identity\\Http\\Middleware',
    'name' => 'App\\Modules\\Identity\\Http\\Middleware\\EnsureTwoFactorEnrolled',
    'shortName' => 'EnsureTwoFactorEnrolled',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Two-factor authentication is mandatory for roles that require it
 * (specification §2, §5): until it is confirmed, every protected API call
 * answers 403 `two_factor_enrollment_required`.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 28,
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
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'handle' => 
      array (
        'name' => 'handle',
        'parameters' => 
        array (
          'request' => 
          array (
            'name' => 'request',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\Request',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 28,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'next' => 
          array (
            'name' => 'next',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 19,
            'endLine' => 19,
            'startColumn' => 46,
            'endColumn' => 58,
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
            'name' => 'Symfony\\Component\\HttpFoundation\\Response',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 19,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Http\\Middleware',
        'declaringClassName' => 'App\\Modules\\Identity\\Http\\Middleware\\EnsureTwoFactorEnrolled',
        'implementingClassName' => 'App\\Modules\\Identity\\Http\\Middleware\\EnsureTwoFactorEnrolled',
        'currentClassName' => 'App\\Modules\\Identity\\Http\\Middleware\\EnsureTwoFactorEnrolled',
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