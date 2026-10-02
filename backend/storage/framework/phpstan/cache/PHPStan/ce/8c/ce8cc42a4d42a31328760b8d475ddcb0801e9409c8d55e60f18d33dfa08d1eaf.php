<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../firebase/php-jwt/src/JWT.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Firebase\JWT\JWT
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-fa59d2771a8a6542cb33f40de460fd60f54c49b0334a568b1697be7154f6522c-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Firebase\\JWT\\JWT',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../firebase/php-jwt/src/JWT.php',
      ),
    ),
    'namespace' => 'Firebase\\JWT',
    'name' => 'Firebase\\JWT\\JWT',
    'shortName' => 'JWT',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * JSON Web Token implementation, based on this spec:
 * https://tools.ietf.org/html/rfc7519
 *
 * PHP version 5
 *
 * @category Authentication
 * @package  Authentication_JWT
 * @author   Neuman Vong <neuman@twilio.com>
 * @author   Anant Narayanan <anant@php.net>
 * @license  http://opensource.org/licenses/BSD-3-Clause 3-clause BSD
 * @link     https://github.com/firebase/php-jwt
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 28,
    'endLine' => 835,
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
      'ASN1_INTEGER' => 
      array (
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'name' => 'ASN1_INTEGER',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x2',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 68,
            'startFilePos' => 662,
            'endTokenPos' => 68,
            'endFilePos' => 665,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'ASN1_SEQUENCE' => 
      array (
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'name' => 'ASN1_SEQUENCE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x10',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 79,
            'startFilePos' => 702,
            'endTokenPos' => 79,
            'endFilePos' => 705,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 39,
      ),
      'ASN1_BIT_STRING' => 
      array (
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'name' => 'ASN1_BIT_STRING',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '0x3',
          'attributes' => 
          array (
            'startLine' => 32,
            'endLine' => 32,
            'startTokenPos' => 90,
            'startFilePos' => 744,
            'endTokenPos' => 90,
            'endFilePos' => 747,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 32,
        'endLine' => 32,
        'startColumn' => 5,
        'endColumn' => 41,
      ),
      'RSA_KEY_MIN_LENGTH' => 
      array (
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'name' => 'RSA_KEY_MIN_LENGTH',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2048',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 101,
            'startFilePos' => 790,
            'endTokenPos' => 101,
            'endFilePos' => 793,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 44,
      ),
    ),
    'immediateProperties' => 
    array (
      'leeway' => 
      array (
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'name' => 'leeway',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 43,
            'endLine' => 43,
            'startTokenPos' => 114,
            'startFilePos' => 998,
            'endTokenPos' => 114,
            'endFilePos' => 998,
          ),
        ),
        'docComment' => '/**
 * When checking nbf, iat or expiration times,
 * we want to provide some extra leeway time to
 * account for clock skew.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 43,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 30,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'timestamp' => 
      array (
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'name' => 'timestamp',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'null',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 127,
            'startFilePos' => 1228,
            'endTokenPos' => 127,
            'endFilePos' => 1231,
          ),
        ),
        'docComment' => '/**
 * Allow the current timestamp to be specified.
 * Useful for fixing a value within unit testing.
 * Will default to PHP time() value if null.
 *
 * @var ?int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 36,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'supported_algs' => 
      array (
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'name' => 'supported_algs',
        'modifiers' => 17,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'ES384\' => [\'openssl\', \'SHA384\'], \'ES256\' => [\'openssl\', \'SHA256\'], \'ES256K\' => [\'openssl\', \'SHA256\'], \'HS256\' => [\'hash_hmac\', \'SHA256\'], \'HS384\' => [\'hash_hmac\', \'SHA384\'], \'HS512\' => [\'hash_hmac\', \'SHA512\'], \'RS256\' => [\'openssl\', \'SHA256\'], \'RS384\' => [\'openssl\', \'SHA384\'], \'RS512\' => [\'openssl\', \'SHA512\'], \'PS256\' => [\'openssl\', \'SHA256\'], \'EdDSA\' => [\'sodium_crypto\', \'EdDSA\']]',
          'attributes' => 
          array (
            'startLine' => 57,
            'endLine' => 69,
            'startTokenPos' => 140,
            'startFilePos' => 1323,
            'endTokenPos' => 273,
            'endFilePos' => 1802,
          ),
        ),
        'docComment' => '/**
 * @var array<string, string[]>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 57,
        'endLine' => 69,
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
      'decode' => 
      array (
        'name' => 'decode',
        'parameters' => 
        array (
          'jwt' => 
          array (
            'name' => 'jwt',
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
            'startLine' => 101,
            'endLine' => 101,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'keyOrKeyArray' => 
          array (
            'name' => 'keyOrKeyArray',
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
            'startLine' => 102,
            'endLine' => 102,
            'startColumn' => 9,
            'endColumn' => 45,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'headers' => 
          array (
            'name' => 'headers',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 103,
                'endLine' => 103,
                'startTokenPos' => 307,
                'startFilePos' => 4016,
                'endTokenPos' => 307,
                'endFilePos' => 4019,
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
                      'name' => 'stdClass',
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
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 103,
            'endLine' => 103,
            'startColumn' => 9,
            'endColumn' => 34,
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
            'name' => 'stdClass',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Decodes a JWT string into a PHP object.
 *
 * @param string                 $jwt            The JWT
 * @param Key|ArrayAccess<string,Key>|array<string,Key> $keyOrKeyArray  The Key or associative array of key IDs
 *                                                                      (kid) to Key objects.
 *                                                                      If the algorithm used is asymmetric, this is
 *                                                                      the public key.
 *                                                                      Each Key object contains an algorithm and
 *                                                                      matching key.
 *                                                                      Supported algorithms are \'ES384\',\'ES256\',
 *                                                                      \'HS256\', \'HS384\', \'HS512\', \'RS256\', \'RS384\'
 *                                                                      and \'RS512\'.
 * @param stdClass               $headers                               Optional. Populates stdClass with headers.
 *
 * @return stdClass The JWT\'s payload as a PHP object
 *
 * @throws InvalidArgumentException     Provided key/key-array was empty or malformed
 * @throws DomainException              Provided JWT is malformed
 * @throws UnexpectedValueException     Provided JWT was invalid
 * @throws SignatureInvalidException    Provided JWT was invalid because the signature verification failed
 * @throws BeforeValidException         Provided JWT is trying to be used before it\'s eligible as defined by \'nbf\'
 * @throws BeforeValidException         Provided JWT is trying to be used before it\'s
 *                                      been created as defined by \'iat\'
 * @throws ExpiredException             Provided JWT has since expired, as defined by the \'exp\' claim
 *
 * @uses jsonDecode
 * @uses urlsafeB64Decode
 */',
        'startLine' => 100,
        'endLine' => 197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'encode' => 
      array (
        'name' => 'encode',
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
            'startLine' => 215,
            'endLine' => 215,
            'startColumn' => 9,
            'endColumn' => 22,
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 216,
            'endLine' => 216,
            'startColumn' => 9,
            'endColumn' => 35,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'alg' => 
          array (
            'name' => 'alg',
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
            'startLine' => 217,
            'endLine' => 217,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'keyId' => 
          array (
            'name' => 'keyId',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 218,
                'endLine' => 218,
                'startTokenPos' => 1216,
                'startFilePos' => 8964,
                'endTokenPos' => 1216,
                'endFilePos' => 8967,
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
                      'name' => 'string',
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
            'startLine' => 218,
            'endLine' => 218,
            'startColumn' => 9,
            'endColumn' => 29,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'head' => 
          array (
            'name' => 'head',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 219,
                'endLine' => 219,
                'startTokenPos' => 1226,
                'startFilePos' => 8993,
                'endTokenPos' => 1226,
                'endFilePos' => 8996,
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
            'startLine' => 219,
            'endLine' => 219,
            'startColumn' => 9,
            'endColumn' => 27,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
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
        'docComment' => '/**
 * Converts and signs a PHP array into a JWT string.
 *
 * @param array<mixed>          $payload PHP array
 * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate $key The secret key.
 * @param string                $alg     Supported algorithms are \'ES384\',\'ES256\', \'ES256K\', \'HS256\',
 *                                       \'HS384\', \'HS512\', \'RS256\', \'RS384\', and \'RS512\'
 * @param string                $keyId
 * @param array<string, string|string[]> $head  An array with header elements to attach
 *
 * @return string A signed JWT
 *
 * @uses jsonEncode
 * @uses urlsafeB64Encode
 */',
        'startLine' => 214,
        'endLine' => 247,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'sign' => 
      array (
        'name' => 'sign',
        'parameters' => 
        array (
          'msg' => 
          array (
            'name' => 'msg',
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
            'startLine' => 262,
            'endLine' => 262,
            'startColumn' => 9,
            'endColumn' => 19,
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 263,
            'endLine' => 263,
            'startColumn' => 9,
            'endColumn' => 35,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'alg' => 
          array (
            'name' => 'alg',
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
            'startLine' => 264,
            'endLine' => 264,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Sign a string with a given key and algorithm.
 *
 * @param string $msg  The message to sign
 * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate  $key  The secret key.
 * @param string $alg  Supported algorithms are \'EdDSA\', \'ES384\', \'ES256\', \'ES256K\', \'HS256\',
 *                    \'HS384\', \'HS512\', \'RS256\', \'RS384\', \'PS256\' and \'RS512\'
 *
 * @return string An encrypted message
 *
 * @throws DomainException Unsupported algorithm or bad key was specified
 */',
        'startLine' => 261,
        'endLine' => 309,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'verify' => 
      array (
        'name' => 'verify',
        'parameters' => 
        array (
          'msg' => 
          array (
            'name' => 'msg',
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
            'startLine' => 326,
            'endLine' => 326,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'signature' => 
          array (
            'name' => 'signature',
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
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'keyMaterial' => 
          array (
            'name' => 'keyMaterial',
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
            'startLine' => 328,
            'endLine' => 328,
            'startColumn' => 9,
            'endColumn' => 43,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'alg' => 
          array (
            'name' => 'alg',
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
            'startLine' => 329,
            'endLine' => 329,
            'startColumn' => 9,
            'endColumn' => 19,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Verify a signature with the message, key and method. Not all methods
 * are symmetric, so we must have a separate verify and sign method.
 *
 * @param string $msg         The original message (header and body)
 * @param string $signature   The original signature
 * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate  $keyMaterial For Ed*, ES*, HS*, a string key works.
 *                                                        for RS*, must be an instance of OpenSSLAsymmetricKey
 * @param string $alg         The algorithm
 *
 * @return bool
 *
 * @throws DomainException Invalid Algorithm, bad key, or OpenSSL failure
 */',
        'startLine' => 325,
        'endLine' => 379,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'jsonDecode' => 
      array (
        'name' => 'jsonDecode',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
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
            'startLine' => 390,
            'endLine' => 390,
            'startColumn' => 39,
            'endColumn' => 51,
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
 * Decode a JSON string into a PHP object.
 *
 * @param string $input JSON string
 *
 * @return mixed The decoded JSON string
 *
 * @throws DomainException Provided string was invalid JSON
 */',
        'startLine' => 390,
        'endLine' => 400,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'jsonEncode' => 
      array (
        'name' => 'jsonEncode',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
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
            'startLine' => 411,
            'endLine' => 411,
            'startColumn' => 39,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Encode a PHP array into a JSON string.
 *
 * @param array<mixed> $input A PHP array
 *
 * @return string JSON representation of the PHP array
 *
 * @throws DomainException Provided object could not be encoded to valid JSON
 */',
        'startLine' => 411,
        'endLine' => 423,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'urlsafeB64Decode' => 
      array (
        'name' => 'urlsafeB64Decode',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
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
            'startLine' => 434,
            'endLine' => 434,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Decode a string with URL-safe Base64.
 *
 * @param string $input A Base64 encoded string
 *
 * @return string A decoded string
 *
 * @throws InvalidArgumentException invalid base64 characters
 */',
        'startLine' => 434,
        'endLine' => 437,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'convertBase64UrlToBase64' => 
      array (
        'name' => 'convertBase64UrlToBase64',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
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
            'startLine' => 449,
            'endLine' => 449,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Convert a string in the base64url (URL-safe Base64) encoding to standard base64.
 *
 * @param string $input A Base64 encoded string with URL-safe characters (-_ and no padding)
 *
 * @return string A Base64 encoded string with standard characters (+/) and padding (=), when
 * needed.
 *
 * @see https://www.rfc-editor.org/rfc/rfc4648
 */',
        'startLine' => 449,
        'endLine' => 457,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'urlsafeB64Encode' => 
      array (
        'name' => 'urlsafeB64Encode',
        'parameters' => 
        array (
          'input' => 
          array (
            'name' => 'input',
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
            'startLine' => 466,
            'endLine' => 466,
            'startColumn' => 45,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Encode a string with URL-safe Base64.
 *
 * @param string $input The string you want encoded
 *
 * @return string The base64 encode of what you passed in
 */',
        'startLine' => 466,
        'endLine' => 469,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'getKey' => 
      array (
        'name' => 'getKey',
        'parameters' => 
        array (
          'keyOrKeyArray' => 
          array (
            'name' => 'keyOrKeyArray',
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
            'startLine' => 482,
            'endLine' => 482,
            'startColumn' => 9,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'kid' => 
          array (
            'name' => 'kid',
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
                      'name' => 'string',
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
            'startLine' => 483,
            'endLine' => 483,
            'startColumn' => 9,
            'endColumn' => 20,
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
            'name' => 'Firebase\\JWT\\Key',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Determine if an algorithm has been provided for each Key
 *
 * @param Key|ArrayAccess<string,Key>|array<string,Key> $keyOrKeyArray
 * @param string|null            $kid
 *
 * @throws UnexpectedValueException
 *
 * @return Key
 */',
        'startLine' => 481,
        'endLine' => 503,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'constantTimeEquals' => 
      array (
        'name' => 'constantTimeEquals',
        'parameters' => 
        array (
          'left' => 
          array (
            'name' => 'left',
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
            'startLine' => 510,
            'endLine' => 510,
            'startColumn' => 47,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'right' => 
          array (
            'name' => 'right',
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
            'startLine' => 510,
            'endLine' => 510,
            'startColumn' => 61,
            'endColumn' => 73,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param string $left  The string of known length to compare against
 * @param string $right The user-supplied string
 * @return bool
 */',
        'startLine' => 510,
        'endLine' => 524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'handleJsonError' => 
      array (
        'name' => 'handleJsonError',
        'parameters' => 
        array (
          'errno' => 
          array (
            'name' => 'errno',
            'default' => NULL,
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
            'startLine' => 535,
            'endLine' => 535,
            'startColumn' => 45,
            'endColumn' => 54,
            'parameterIndex' => 0,
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
        'docComment' => '/**
 * Helper method to create a JSON error.
 *
 * @param int $errno An error number from json_last_error()
 *
 * @throws DomainException
 *
 * @return void
 */',
        'startLine' => 535,
        'endLine' => 549,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'safeStrlen' => 
      array (
        'name' => 'safeStrlen',
        'parameters' => 
        array (
          'str' => 
          array (
            'name' => 'str',
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
            'startLine' => 558,
            'endLine' => 558,
            'startColumn' => 40,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the number of bytes in cryptographic strings.
 *
 * @param string $str
 *
 * @return int
 */',
        'startLine' => 558,
        'endLine' => 564,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'signatureToDER' => 
      array (
        'name' => 'signatureToDER',
        'parameters' => 
        array (
          'sig' => 
          array (
            'name' => 'sig',
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
            'startLine' => 572,
            'endLine' => 572,
            'startColumn' => 44,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Convert an ECDSA signature to an ASN.1 DER sequence
 *
 * @param   string $sig The ECDSA signature to convert
 * @return  string The encoded DER object
 */',
        'startLine' => 572,
        'endLine' => 596,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'encodeDER' => 
      array (
        'name' => 'encodeDER',
        'parameters' => 
        array (
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
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
            'startLine' => 606,
            'endLine' => 606,
            'startColumn' => 39,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 606,
            'endLine' => 606,
            'startColumn' => 50,
            'endColumn' => 62,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encodes a value into a DER object.
 *
 * @param   int     $type DER tag
 * @param   string  $value the value to encode
 *
 * @return  string  the encoded object
 */',
        'startLine' => 606,
        'endLine' => 620,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'signatureFromDER' => 
      array (
        'name' => 'signatureFromDER',
        'parameters' => 
        array (
          'der' => 
          array (
            'name' => 'der',
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
            'startLine' => 630,
            'endLine' => 630,
            'startColumn' => 46,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'keySize' => 
          array (
            'name' => 'keySize',
            'default' => NULL,
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
            'startLine' => 630,
            'endLine' => 630,
            'startColumn' => 59,
            'endColumn' => 70,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encodes signature from a DER object.
 *
 * @param   string  $der binary signature in DER format
 * @param   int     $keySize the number of bits in the key
 *
 * @return  string  the signature
 */',
        'startLine' => 630,
        'endLine' => 647,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'readDER' => 
      array (
        'name' => 'readDER',
        'parameters' => 
        array (
          'der' => 
          array (
            'name' => 'der',
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
            'startLine' => 658,
            'endLine' => 658,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 658,
                'endLine' => 658,
                'startTokenPos' => 3767,
                'startFilePos' => 24368,
                'endTokenPos' => 3767,
                'endFilePos' => 24368,
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
            'startLine' => 658,
            'endLine' => 658,
            'startColumn' => 50,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
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
        'docComment' => '/**
 * Reads binary DER-encoded data and decodes into a single object
 *
 * @param string $der the binary data in DER format
 * @param int $offset the offset of the data stream containing the object
 * to decode
 *
 * @return array{int, string|null} the new offset and the decoded object
 */',
        'startLine' => 658,
        'endLine' => 688,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'validateHmacKeyLength' => 
      array (
        'name' => 'validateHmacKeyLength',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 698,
            'endLine' => 698,
            'startColumn' => 51,
            'endColumn' => 61,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'algorithm' => 
          array (
            'name' => 'algorithm',
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
            'startLine' => 698,
            'endLine' => 698,
            'startColumn' => 64,
            'endColumn' => 80,
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
        'docComment' => '/**
 * Validate HMAC key length
 *
 * @param string $key HMAC key material
 * @param string $algorithm The algorithm
 *
 * @throws DomainException Provided key is too short
 */',
        'startLine' => 698,
        'endLine' => 705,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'validateRsaKeyLength' => 
      array (
        'name' => 'validateRsaKeyLength',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'OpenSSLAsymmetricKey',
                'isIdentifier' => false,
              ),
            ),
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
            'startLine' => 713,
            'endLine' => 713,
            'startColumn' => 50,
            'endColumn' => 97,
            'parameterIndex' => 0,
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
        'docComment' => '/**
 * Validate RSA key length
 *
 * @param OpenSSLAsymmetricKey $key RSA key material
 * @throws DomainException Provided key is too short
 */',
        'startLine' => 713,
        'endLine' => 721,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'validateEcKeyLength' => 
      array (
        'name' => 'validateEcKeyLength',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'OpenSSLAsymmetricKey',
                'isIdentifier' => false,
              ),
            ),
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
            'startLine' => 731,
            'endLine' => 731,
            'startColumn' => 9,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'algorithm' => 
          array (
            'name' => 'algorithm',
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
            'startLine' => 732,
            'endLine' => 732,
            'startColumn' => 9,
            'endColumn' => 25,
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
        'docComment' => '/**
 * Validate RSA key length
 *
 * @param OpenSSLAsymmetricKey $key RSA key material
 * @param string $algorithm The algorithm
 * @throws DomainException Provided key is too short
 */',
        'startLine' => 730,
        'endLine' => 741,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'validateEdDSAKey' => 
      array (
        'name' => 'validateEdDSAKey',
        'parameters' => 
        array (
          'keyMaterial' => 
          array (
            'name' => 'keyMaterial',
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
            'startLine' => 747,
            'endLine' => 747,
            'startColumn' => 46,
            'endColumn' => 80,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate  $keyMaterial
 * @return non-empty-string
 */',
        'startLine' => 747,
        'endLine' => 762,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'signPS256' => 
      array (
        'name' => 'signPS256',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'OpenSSLAsymmetricKey',
                      'isIdentifier' => false,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'OpenSSLCertificate',
                      'isIdentifier' => false,
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 772,
            'endLine' => 772,
            'startColumn' => 9,
            'endColumn' => 82,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'message' => 
          array (
            'name' => 'message',
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
            'startLine' => 773,
            'endLine' => 773,
            'startColumn' => 9,
            'endColumn' => 23,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Signs a message with a PS256 algorithm
 *
 * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate $key
 * @param string $message
 * @throws DomainException Provided key is invalid
 */',
        'startLine' => 771,
        'endLine' => 796,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
        'aliasName' => NULL,
      ),
      'verifyPS256' => 
      array (
        'name' => 'verifyPS256',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'OpenSSLAsymmetricKey',
                      'isIdentifier' => false,
                    ),
                  ),
                  2 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'OpenSSLCertificate',
                      'isIdentifier' => false,
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
              0 => 
              array (
                'name' => 'SensitiveParameter',
                'isRepeated' => false,
                'arguments' => 
                array (
                ),
              ),
            ),
            'startLine' => 807,
            'endLine' => 807,
            'startColumn' => 9,
            'endColumn' => 82,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'message' => 
          array (
            'name' => 'message',
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
            'startLine' => 808,
            'endLine' => 808,
            'startColumn' => 9,
            'endColumn' => 23,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'signature' => 
          array (
            'name' => 'signature',
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
            'startLine' => 809,
            'endLine' => 809,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
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
        'docComment' => '/**
 * Validates a PS256 algorithm signature.
 *
 * @param string|OpenSSLAsymmetricKey|OpenSSLCertificate $key
 * @param string $message
 * @param string $signature
 * @throws DomainException Provided key is invalid
 */',
        'startLine' => 806,
        'endLine' => 834,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'Firebase\\JWT',
        'declaringClassName' => 'Firebase\\JWT\\JWT',
        'implementingClassName' => 'Firebase\\JWT\\JWT',
        'currentClassName' => 'Firebase\\JWT\\JWT',
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