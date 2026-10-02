<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Setup/Http/Middleware/SetupOpen.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Setup\Http\Middleware\SetupOpen
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-b837e61cb2d3ebbba6c2b6d9a2289c56d8e0c6784ee2e6c32de90e7e32817711',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Setup/Http/Middleware/SetupOpen.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Setup\\Http\\Middleware',
    'name' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
    'shortName' => 'SetupOpen',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The wizard is locked permanently once setup completes (specification §2):
 * every setup endpoint then answers 404. While it is open, calls other than
 * the status check must carry the setup token.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 32,
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
      'TOKEN_HEADER' => 
      array (
        'declaringClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'implementingClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'name' => 'TOKEN_HEADER',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'X-Setup-Token\'',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 19,
            'startTokenPos' => 53,
            'startFilePos' => 470,
            'endTokenPos' => 53,
            'endFilePos' => 484,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 19,
        'startColumn' => 5,
        'endColumn' => 48,
      ),
    ),
    'immediateProperties' => 
    array (
      'state' => 
      array (
        'declaringClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'implementingClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'name' => 'state',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Modules\\Setup\\SetupState',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 33,
        'endColumn' => 66,
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
          'state' => 
          array (
            'name' => 'state',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Modules\\Setup\\SetupState',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 33,
            'endColumn' => 66,
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
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 70,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Setup\\Http\\Middleware',
        'declaringClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'implementingClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'currentClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'aliasName' => NULL,
      ),
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
            'startLine' => 23,
            'endLine' => 23,
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
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 46,
            'endColumn' => 58,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'tokenRequired' => 
          array (
            'name' => 'tokenRequired',
            'default' => 
            array (
              'code' => '\'token\'',
              'attributes' => 
              array (
                'startLine' => 23,
                'endLine' => 23,
                'startTokenPos' => 96,
                'startFilePos' => 644,
                'endTokenPos' => 96,
                'endFilePos' => 650,
              ),
            ),
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
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 61,
            'endColumn' => 91,
            'parameterIndex' => 2,
            'isOptional' => true,
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
        'startLine' => 23,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Setup\\Http\\Middleware',
        'declaringClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'implementingClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
        'currentClassName' => 'App\\Modules\\Setup\\Http\\Middleware\\SetupOpen',
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