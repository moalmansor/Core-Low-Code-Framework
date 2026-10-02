<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/fortify/src/TwoFactorAuthenticationProvider.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Fortify\TwoFactorAuthenticationProvider
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-4bf081549c88ba319873d20665792dfa4485272e6efed62cda46972e77aff12a-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/fortify/src/TwoFactorAuthenticationProvider.php',
      ),
    ),
    'namespace' => 'Laravel\\Fortify',
    'name' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
    'shortName' => 'TwoFactorAuthenticationProvider',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 91,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Laravel\\Fortify\\Contracts\\TwoFactorAuthenticationProvider',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'engine' => 
      array (
        'declaringClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'implementingClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'name' => 'engine',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The underlying library providing two factor authentication helper services.
 *
 * @var \\PragmaRX\\Google2FA\\Google2FA
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cache' => 
      array (
        'declaringClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'implementingClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'name' => 'cache',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The cache repository implementation.
 *
 * @var \\Illuminate\\Contracts\\Cache\\Repository|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 21,
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'engine' => 
          array (
            'name' => 'engine',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'PragmaRX\\Google2FA\\Google2FA',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 33,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cache' => 
          array (
            'name' => 'cache',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 32,
                'endLine' => 32,
                'startTokenPos' => 70,
                'startFilePos' => 929,
                'endTokenPos' => 70,
                'endFilePos' => 932,
              ),
            ),
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
                      'name' => 'Illuminate\\Contracts\\Cache\\Repository',
                      'isIdentifier' => false,
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
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 52,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new two factor authentication provider instance.
 *
 * @param  \\PragmaRX\\Google2FA\\Google2FA  $engine
 * @param  \\Illuminate\\Contracts\\Cache\\Repository|null  $cache
 * @return void
 */',
        'startLine' => 32,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Fortify',
        'declaringClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'implementingClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'currentClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'aliasName' => NULL,
      ),
      'generateSecretKey' => 
      array (
        'name' => 'generateSecretKey',
        'parameters' => 
        array (
          'secretLength' => 
          array (
            'name' => 'secretLength',
            'default' => 
            array (
              'code' => '16',
              'attributes' => 
              array (
                'startLine' => 44,
                'endLine' => 44,
                'startTokenPos' => 109,
                'startFilePos' => 1183,
                'endTokenPos' => 109,
                'endFilePos' => 1184,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 39,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Generate a new secret key.
 *
 * @param  int  $secretLength
 * @return string
 */',
        'startLine' => 44,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Fortify',
        'declaringClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'implementingClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'currentClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'aliasName' => NULL,
      ),
      'qrCodeUrl' => 
      array (
        'name' => 'qrCodeUrl',
        'parameters' => 
        array (
          'companyName' => 
          array (
            'name' => 'companyName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 31,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'companyEmail' => 
          array (
            'name' => 'companyEmail',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'secret' => 
          array (
            'name' => 'secret',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 60,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the two factor authentication QR code URL.
 *
 * @param  string  $companyName
 * @param  string  $companyEmail
 * @param  string  $secret
 * @return string
 */',
        'startLine' => 57,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Fortify',
        'declaringClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'implementingClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'currentClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'aliasName' => NULL,
      ),
      'verify' => 
      array (
        'name' => 'verify',
        'parameters' => 
        array (
          'secret' => 
          array (
            'name' => 'secret',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'code' => 
          array (
            'name' => 'code',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 69,
            'endLine' => 69,
            'startColumn' => 37,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Verify the given code.
 *
 * @param  string  $secret
 * @param  string  $code
 * @return bool
 */',
        'startLine' => 69,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Fortify',
        'declaringClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'implementingClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
        'currentClassName' => 'Laravel\\Fortify\\TwoFactorAuthenticationProvider',
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