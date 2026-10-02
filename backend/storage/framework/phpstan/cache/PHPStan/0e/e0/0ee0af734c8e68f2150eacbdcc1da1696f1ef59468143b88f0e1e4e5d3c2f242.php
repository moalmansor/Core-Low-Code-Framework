<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Encryption/Encrypter.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Encryption\Encrypter
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-2b8aca6c7503bf0e0849409e0f6b9a7ef7186854b85e3e9a3c9a5e10f8f853d9-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Encryption\\Encrypter',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Encryption/Encrypter.php',
      ),
    ),
    'namespace' => 'Illuminate\\Encryption',
    'name' => 'Illuminate\\Encryption\\Encrypter',
    'shortName' => 'Encrypter',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 405,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Encryption\\Encrypter',
      1 => 'Illuminate\\Contracts\\Encryption\\StringEncrypter',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'key' => 
      array (
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'name' => 'key',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The encryption key.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 18,
        'endLine' => 18,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'previousKeys' => 
      array (
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'name' => 'previousKeys',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 64,
            'startFilePos' => 564,
            'endTokenPos' => 65,
            'endFilePos' => 565,
          ),
        ),
        'docComment' => '/**
 * The previous / legacy encryption keys.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 33,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'cipher' => 
      array (
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'name' => 'cipher',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The algorithm used for encryption.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'supportedCiphers' => 
      array (
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'name' => 'supportedCiphers',
        'modifiers' => 20,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'aes-128-cbc\' => [\'size\' => 16, \'aead\' => false], \'aes-256-cbc\' => [\'size\' => 32, \'aead\' => false], \'aes-128-gcm\' => [\'size\' => 16, \'aead\' => true], \'aes-256-gcm\' => [\'size\' => 32, \'aead\' => true]]',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 44,
            'startTokenPos' => 85,
            'startFilePos' => 818,
            'endTokenPos' => 167,
            'endFilePos' => 1054,
          ),
        ),
        'docComment' => '/**
 * The supported cipher algorithms and their properties.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 39,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 6,
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
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 33,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cipher' => 
          array (
            'name' => 'cipher',
            'default' => 
            array (
              'code' => '\'aes-128-cbc\'',
              'attributes' => 
              array (
                'startLine' => 54,
                'endLine' => 54,
                'startTokenPos' => 185,
                'startFilePos' => 1268,
                'endTokenPos' => 185,
                'endFilePos' => 1280,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 54,
            'endLine' => 54,
            'startColumn' => 39,
            'endColumn' => 61,
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
 * Create a new encrypter instance.
 *
 * @param  string  $key
 * @param  string  $cipher
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 54,
        'endLine' => 66,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'supported' => 
      array (
        'name' => 'supported',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 38,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cipher' => 
          array (
            'name' => 'cipher',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 44,
            'endColumn' => 50,
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
 * Determine if the given key and cipher combination is valid.
 *
 * @param  string  $key
 * @param  string  $cipher
 * @return bool
 */',
        'startLine' => 75,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'generateKey' => 
      array (
        'name' => 'generateKey',
        'parameters' => 
        array (
          'cipher' => 
          array (
            'name' => 'cipher',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 90,
            'endLine' => 90,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new encryption key for the given cipher.
 *
 * @param  string  $cipher
 * @return string
 */',
        'startLine' => 90,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'encrypt' => 
      array (
        'name' => 'encrypt',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 29,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'serialize' => 
          array (
            'name' => 'serialize',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 104,
                'endLine' => 104,
                'startTokenPos' => 406,
                'startFilePos' => 2648,
                'endTokenPos' => 406,
                'endFilePos' => 2651,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 104,
            'endLine' => 104,
            'startColumn' => 60,
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
 * Encrypt the given value.
 *
 * @param  mixed  $value
 * @param  bool  $serialize
 * @return string
 *
 * @throws \\Illuminate\\Contracts\\Encryption\\EncryptException
 */',
        'startLine' => 104,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'encryptString' => 
      array (
        'name' => 'encryptString',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 141,
            'endLine' => 141,
            'startColumn' => 35,
            'endColumn' => 63,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encrypt a string without serialization.
 *
 * @param  string  $value
 * @return string
 *
 * @throws \\Illuminate\\Contracts\\Encryption\\EncryptException
 */',
        'startLine' => 141,
        'endLine' => 144,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'decrypt' => 
      array (
        'name' => 'decrypt',
        'parameters' => 
        array (
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
            'startLine' => 155,
            'endLine' => 155,
            'startColumn' => 29,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'unserialize' => 
          array (
            'name' => 'unserialize',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 155,
                'endLine' => 155,
                'startTokenPos' => 673,
                'startFilePos' => 4169,
                'endTokenPos' => 673,
                'endFilePos' => 4172,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 155,
            'endLine' => 155,
            'startColumn' => 39,
            'endColumn' => 57,
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
 * Decrypt the given value.
 *
 * @param  string  $payload
 * @param  bool  $unserialize
 * @return mixed
 *
 * @throws \\Illuminate\\Contracts\\Encryption\\DecryptException
 */',
        'startLine' => 155,
        'endLine' => 196,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'decryptString' => 
      array (
        'name' => 'decryptString',
        'parameters' => 
        array (
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
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Decrypt the given string without unserialization.
 *
 * @param  string  $payload
 * @return string
 *
 * @throws \\Illuminate\\Contracts\\Encryption\\DecryptException
 */',
        'startLine' => 206,
        'endLine' => 209,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'hash' => 
      array (
        'name' => 'hash',
        'parameters' => 
        array (
          'iv' => 
          array (
            'name' => 'iv',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 219,
            'endLine' => 219,
            'startColumn' => 29,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 219,
            'endLine' => 219,
            'startColumn' => 57,
            'endColumn' => 85,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 219,
            'endLine' => 219,
            'startColumn' => 88,
            'endColumn' => 114,
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
 * Create a MAC for the given value.
 *
 * @param  string  $iv
 * @param  mixed  $value
 * @param  string  $key
 * @return string
 */',
        'startLine' => 219,
        'endLine' => 222,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'getJsonPayload' => 
      array (
        'name' => 'getJsonPayload',
        'parameters' => 
        array (
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
            'startLine' => 232,
            'endLine' => 232,
            'startColumn' => 39,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the JSON array from the given payload.
 *
 * @param  string  $payload
 * @return array
 *
 * @throws \\Illuminate\\Contracts\\Encryption\\DecryptException
 */',
        'startLine' => 232,
        'endLine' => 248,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'validPayload' => 
      array (
        'name' => 'validPayload',
        'parameters' => 
        array (
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
            'startLine' => 256,
            'endLine' => 256,
            'startColumn' => 37,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Verify that the encryption payload is valid.
 *
 * @param  mixed  $payload
 * @return bool
 */',
        'startLine' => 256,
        'endLine' => 273,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'validMac' => 
      array (
        'name' => 'validMac',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 281,
            'endLine' => 281,
            'startColumn' => 33,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the MAC for the given payload is valid for the primary key.
 *
 * @param  array  $payload
 * @return bool
 */',
        'startLine' => 281,
        'endLine' => 284,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'validMacForKey' => 
      array (
        'name' => 'validMacForKey',
        'parameters' => 
        array (
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 39,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 72,
            'endColumn' => 75,
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
 * Determine if the MAC is valid for the given payload and key.
 *
 * @param  array  $payload
 * @param  string  $key
 * @return bool
 */',
        'startLine' => 293,
        'endLine' => 298,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'ensureTagIsValid' => 
      array (
        'name' => 'ensureTagIsValid',
        'parameters' => 
        array (
          'tag' => 
          array (
            'name' => 'tag',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 308,
            'endLine' => 308,
            'startColumn' => 41,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Ensure the given tag is a valid tag given the selected cipher.
 *
 * @param  string  $tag
 * @return void
 *
 * @throws \\Illuminate\\Contracts\\Encryption\\DecryptException
 */',
        'startLine' => 308,
        'endLine' => 317,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'shouldValidateMac' => 
      array (
        'name' => 'shouldValidateMac',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if we should validate the MAC while decrypting.
 *
 * @return bool
 */',
        'startLine' => 324,
        'endLine' => 327,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'appearsEncrypted' => 
      array (
        'name' => 'appearsEncrypted',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 335,
            'endLine' => 335,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if the given value appears to be encrypted by this encrypter.
 *
 * @param  mixed  $value
 * @return bool
 */',
        'startLine' => 335,
        'endLine' => 351,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'getKey' => 
      array (
        'name' => 'getKey',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the encryption key that the encrypter is currently using.
 *
 * @return string
 */',
        'startLine' => 358,
        'endLine' => 361,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'getAllKeys' => 
      array (
        'name' => 'getAllKeys',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the current encryption key and all previous encryption keys.
 *
 * @return array
 */',
        'startLine' => 368,
        'endLine' => 371,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'getPreviousKeys' => 
      array (
        'name' => 'getPreviousKeys',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the previous encryption keys.
 *
 * @return array
 */',
        'startLine' => 378,
        'endLine' => 381,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
        'aliasName' => NULL,
      ),
      'previousKeys' => 
      array (
        'name' => 'previousKeys',
        'parameters' => 
        array (
          'keys' => 
          array (
            'name' => 'keys',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'array',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 391,
            'endLine' => 391,
            'startColumn' => 34,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the previous / legacy encryption keys that should be utilized if decryption fails.
 *
 * @param  array  $keys
 * @return $this
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 391,
        'endLine' => 404,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Encryption',
        'declaringClassName' => 'Illuminate\\Encryption\\Encrypter',
        'implementingClassName' => 'Illuminate\\Encryption\\Encrypter',
        'currentClassName' => 'Illuminate\\Encryption\\Encrypter',
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