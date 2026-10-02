<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../bacon/bacon-qr-code/src/Encoder/Encoder.php-PHPStan\BetterReflection\Reflection\ReflectionClass-BaconQrCode\Encoder\Encoder
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-89bb2beba25474ebe196fc0b2d81b8dadeaea4ce008c678a48d8e6e668f74d58-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'BaconQrCode\\Encoder\\Encoder',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../bacon/bacon-qr-code/src/Encoder/Encoder.php',
      ),
    ),
    'namespace' => 'BaconQrCode\\Encoder',
    'name' => 'BaconQrCode\\Encoder\\Encoder',
    'shortName' => 'Encoder',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Encoder.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 18,
    'endLine' => 666,
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
      'DEFAULT_BYTE_MODE_ENCODING' => 
      array (
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'name' => 'DEFAULT_BYTE_MODE_ENCODING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ISO-8859-1\'',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 76,
            'startFilePos' => 483,
            'endTokenPos' => 76,
            'endFilePos' => 494,
          ),
        ),
        'docComment' => '/**
 * Default byte encoding.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 59,
      ),
      'DEFAULT_BYTE_MODE_ECODING' => 
      array (
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'name' => 'DEFAULT_BYTE_MODE_ECODING',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => 'self::DEFAULT_BYTE_MODE_ENCODING',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 89,
            'startFilePos' => 597,
            'endTokenPos' => 91,
            'endFilePos' => 628,
          ),
        ),
        'docComment' => '/** @deprecated use DEFAULT_BYTE_MODE_ENCODING */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 78,
      ),
      'ALPHANUMERIC_CHARS' => 
      array (
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'name' => 'ALPHANUMERIC_CHARS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ $%*+-./:\'',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 104,
            'startFilePos' => 740,
            'endTokenPos' => 104,
            'endFilePos' => 786,
          ),
        ),
        'docComment' => '/**
 * Allowed characters for the Alphanumeric Mode.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 87,
      ),
      'ALPHANUMERIC_TABLE' => 
      array (
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'name' => 'ALPHANUMERIC_TABLE',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    // 0x00-0x0f
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    -1,
    // 0x10-0x1f
    36,
    -1,
    -1,
    -1,
    37,
    38,
    -1,
    -1,
    -1,
    -1,
    39,
    40,
    -1,
    41,
    42,
    43,
    // 0x20-0x2f
    0,
    1,
    2,
    3,
    4,
    5,
    6,
    7,
    8,
    9,
    44,
    -1,
    -1,
    -1,
    -1,
    -1,
    // 0x30-0x3f
    -1,
    10,
    11,
    12,
    13,
    14,
    15,
    16,
    17,
    18,
    19,
    20,
    21,
    22,
    23,
    24,
    // 0x40-0x4f
    25,
    26,
    27,
    28,
    29,
    30,
    31,
    32,
    33,
    34,
    35,
    -1,
    -1,
    -1,
    -1,
    -1,
]',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 43,
            'startTokenPos' => 117,
            'startFilePos' => 922,
            'endTokenPos' => 470,
            'endFilePos' => 1444,
          ),
        ),
        'docComment' => '/**
 * The original table is defined in the table 5 of JISX0510:2004 (p.19).
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 43,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'codecs' => 
      array (
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'name' => 'codecs',
        'modifiers' => 20,
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
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 485,
            'startFilePos' => 1569,
            'endTokenPos' => 486,
            'endFilePos' => 1570,
          ),
        ),
        'docComment' => '/**
 * Codec cache.
 *
 * @var array<string,ReedSolomonCodec>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 38,
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
      'encode' => 
      array (
        'name' => 'encode',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 9,
            'endColumn' => 23,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ecLevel' => 
          array (
            'name' => 'ecLevel',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\ErrorCorrectionLevel',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'encoding' => 
          array (
            'name' => 'encoding',
            'default' => 
            array (
              'code' => 'self::DEFAULT_BYTE_MODE_ENCODING',
              'attributes' => 
              array (
                'startLine' => 58,
                'endLine' => 58,
                'startTokenPos' => 516,
                'startFilePos' => 1784,
                'endTokenPos' => 518,
                'endFilePos' => 1815,
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
            'startLine' => 58,
            'endLine' => 58,
            'startColumn' => 9,
            'endColumn' => 59,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'forcedVersion' => 
          array (
            'name' => 'forcedVersion',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 59,
                'endLine' => 59,
                'startTokenPos' => 528,
                'startFilePos' => 1852,
                'endTokenPos' => 528,
                'endFilePos' => 1855,
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
                      'name' => 'BaconQrCode\\Common\\Version',
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
            'startLine' => 59,
            'endLine' => 59,
            'startColumn' => 9,
            'endColumn' => 38,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'prefixEci' => 
          array (
            'name' => 'prefixEci',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 61,
                'endLine' => 61,
                'startTokenPos' => 539,
                'startFilePos' => 2001,
                'endTokenPos' => 539,
                'endFilePos' => 2004,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
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
            'startColumn' => 9,
            'endColumn' => 30,
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
            'name' => 'BaconQrCode\\Encoder\\QrCode',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Encodes "content" with the error correction level "ecLevel".
 */',
        'startLine' => 55,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'getAlphanumericCode' => 
      array (
        'name' => 'getAlphanumericCode',
        'parameters' => 
        array (
          'byte' => 
          array (
            'name' => 'byte',
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
            'startLine' => 160,
            'endLine' => 160,
            'startColumn' => 49,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets the alphanumeric code for a byte.
 */',
        'startLine' => 160,
        'endLine' => 163,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'chooseMode' => 
      array (
        'name' => 'chooseMode',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 40,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'encoding' => 
          array (
            'name' => 'encoding',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 168,
                'endLine' => 168,
                'startTokenPos' => 1235,
                'startFilePos' => 6483,
                'endTokenPos' => 1235,
                'endFilePos' => 6486,
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
            'startLine' => 168,
            'endLine' => 168,
            'startColumn' => 57,
            'endColumn' => 80,
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
            'name' => 'BaconQrCode\\Common\\Mode',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Chooses the best mode for a given content.
 */',
        'startLine' => 168,
        'endLine' => 187,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'calculateMaskPenalty' => 
      array (
        'name' => 'calculateMaskPenalty',
        'parameters' => 
        array (
          'matrix' => 
          array (
            'name' => 'matrix',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Encoder\\ByteMatrix',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 192,
            'endLine' => 192,
            'startColumn' => 50,
            'endColumn' => 67,
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
 * Calculates the mask penalty for a matrix.
 */',
        'startLine' => 192,
        'endLine' => 200,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'isOnlyDoubleByteKanji' => 
      array (
        'name' => 'isOnlyDoubleByteKanji',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 205,
            'endLine' => 205,
            'startColumn' => 51,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Checks if content only consists of double-byte kanji characters (or is empty).
 */',
        'startLine' => 205,
        'endLine' => 228,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'isOnlyAlphanumeric' => 
      array (
        'name' => 'isOnlyAlphanumeric',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 233,
            'endLine' => 233,
            'startColumn' => 48,
            'endColumn' => 62,
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
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Checks if content only consists of alphanumeric characters (or is empty).
 */',
        'startLine' => 233,
        'endLine' => 236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'chooseMaskPattern' => 
      array (
        'name' => 'chooseMaskPattern',
        'parameters' => 
        array (
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 242,
            'endLine' => 242,
            'startColumn' => 9,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ecLevel' => 
          array (
            'name' => 'ecLevel',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\ErrorCorrectionLevel',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 243,
            'endLine' => 243,
            'startColumn' => 9,
            'endColumn' => 37,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'version' => 
          array (
            'name' => 'version',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\Version',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 244,
            'endLine' => 244,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'matrix' => 
          array (
            'name' => 'matrix',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Encoder\\ByteMatrix',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 245,
            'endLine' => 245,
            'startColumn' => 9,
            'endColumn' => 26,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Chooses the best mask pattern for a matrix.
 */',
        'startLine' => 241,
        'endLine' => 261,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'chooseVersion' => 
      array (
        'name' => 'chooseVersion',
        'parameters' => 
        array (
          'numInputBits' => 
          array (
            'name' => 'numInputBits',
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
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 43,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ecLevel' => 
          array (
            'name' => 'ecLevel',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\ErrorCorrectionLevel',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 62,
            'endColumn' => 90,
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
            'name' => 'BaconQrCode\\Common\\Version',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Chooses the best version for the input.
 *
 * @throws WriterException if data is too big
 */',
        'startLine' => 268,
        'endLine' => 286,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'terminateBits' => 
      array (
        'name' => 'terminateBits',
        'parameters' => 
        array (
          'numDataBytes' => 
          array (
            'name' => 'numDataBytes',
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
            'startLine' => 294,
            'endLine' => 294,
            'startColumn' => 43,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 294,
            'endLine' => 294,
            'startColumn' => 62,
            'endColumn' => 75,
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
 * Terminates the bits in a bit array.
 *
 * @throws WriterException if data bits cannot fit in the QR code
 * @throws WriterException if bits size does not equal the capacity
 */',
        'startLine' => 294,
        'endLine' => 323,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'getNumDataBytesAndNumEcBytesForBlockId' => 
      array (
        'name' => 'getNumDataBytesAndNumEcBytesForBlockId',
        'parameters' => 
        array (
          'numTotalBytes' => 
          array (
            'name' => 'numTotalBytes',
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
            'startLine' => 335,
            'endLine' => 335,
            'startColumn' => 9,
            'endColumn' => 26,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'numDataBytes' => 
          array (
            'name' => 'numDataBytes',
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
            'startLine' => 336,
            'endLine' => 336,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'numRsBlocks' => 
          array (
            'name' => 'numRsBlocks',
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
            'startLine' => 337,
            'endLine' => 337,
            'startColumn' => 9,
            'endColumn' => 24,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'blockId' => 
          array (
            'name' => 'blockId',
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
            'startLine' => 338,
            'endLine' => 338,
            'startColumn' => 9,
            'endColumn' => 20,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets number of data- and EC bytes for a block ID.
 *
 * @return int[]
 * @throws WriterException if block ID is too large
 * @throws WriterException if EC bytes mismatch
 * @throws WriterException if RS blocks mismatch
 * @throws WriterException if total bytes mismatch
 */',
        'startLine' => 334,
        'endLine' => 373,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'interleaveWithEcBytes' => 
      array (
        'name' => 'interleaveWithEcBytes',
        'parameters' => 
        array (
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 383,
            'endLine' => 383,
            'startColumn' => 9,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'numTotalBytes' => 
          array (
            'name' => 'numTotalBytes',
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
            'startLine' => 384,
            'endLine' => 384,
            'startColumn' => 9,
            'endColumn' => 26,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'numDataBytes' => 
          array (
            'name' => 'numDataBytes',
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
            'startLine' => 385,
            'endLine' => 385,
            'startColumn' => 9,
            'endColumn' => 25,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'numRsBlocks' => 
          array (
            'name' => 'numRsBlocks',
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
            'startLine' => 386,
            'endLine' => 386,
            'startColumn' => 9,
            'endColumn' => 24,
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
            'name' => 'BaconQrCode\\Common\\BitArray',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Interleaves data with EC bytes.
 *
 * @throws WriterException if number of bits and data bytes does not match
 * @throws WriterException if data bytes does not match offset
 * @throws WriterException if an interleaving error occurs
 */',
        'startLine' => 382,
        'endLine' => 449,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'generateEcBytes' => 
      array (
        'name' => 'generateEcBytes',
        'parameters' => 
        array (
          'dataBytes' => 
          array (
            'name' => 'dataBytes',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'SplFixedArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 457,
            'endLine' => 457,
            'startColumn' => 45,
            'endColumn' => 68,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'numEcBytesInBlock' => 
          array (
            'name' => 'numEcBytesInBlock',
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
            'startLine' => 457,
            'endLine' => 457,
            'startColumn' => 71,
            'endColumn' => 92,
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
            'name' => 'SplFixedArray',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Generates EC bytes for given data.
 *
 * @param  SplFixedArray<int> $dataBytes
 * @return SplFixedArray<int>
 */',
        'startLine' => 457,
        'endLine' => 471,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'getCodec' => 
      array (
        'name' => 'getCodec',
        'parameters' => 
        array (
          'numDataBytes' => 
          array (
            'name' => 'numDataBytes',
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
            'startLine' => 476,
            'endLine' => 476,
            'startColumn' => 38,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'numEcBytesInBlock' => 
          array (
            'name' => 'numEcBytesInBlock',
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
            'startLine' => 476,
            'endLine' => 476,
            'startColumn' => 57,
            'endColumn' => 78,
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
            'name' => 'BaconQrCode\\Common\\ReedSolomonCodec',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Gets an RS codec and caches it.
 */',
        'startLine' => 476,
        'endLine' => 492,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'appendModeInfo' => 
      array (
        'name' => 'appendModeInfo',
        'parameters' => 
        array (
          'mode' => 
          array (
            'name' => 'mode',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\Mode',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 497,
            'endLine' => 497,
            'startColumn' => 44,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 497,
            'endLine' => 497,
            'startColumn' => 56,
            'endColumn' => 69,
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
 * Appends mode information to a bit array.
 */',
        'startLine' => 497,
        'endLine' => 500,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'appendLengthInfo' => 
      array (
        'name' => 'appendLengthInfo',
        'parameters' => 
        array (
          'numLetters' => 
          array (
            'name' => 'numLetters',
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
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 46,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'version' => 
          array (
            'name' => 'version',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\Version',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 63,
            'endColumn' => 78,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\Mode',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 81,
            'endColumn' => 90,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 507,
            'endLine' => 507,
            'startColumn' => 93,
            'endColumn' => 106,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Appends length information to a bit array.
 *
 * @throws WriterException if num letters is bigger than expected
 */',
        'startLine' => 507,
        'endLine' => 516,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'appendBytes' => 
      array (
        'name' => 'appendBytes',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 41,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'mode' => 
          array (
            'name' => 'mode',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\Mode',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 58,
            'endColumn' => 67,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 70,
            'endColumn' => 83,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'encoding' => 
          array (
            'name' => 'encoding',
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
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 86,
            'endColumn' => 101,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Appends bytes to a bit array in a specific mode.
 */',
        'startLine' => 521,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'appendNumericBytes' => 
      array (
        'name' => 'appendNumericBytes',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 534,
            'endLine' => 534,
            'startColumn' => 48,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 534,
            'endLine' => 534,
            'startColumn' => 65,
            'endColumn' => 78,
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
 * Appends numeric bytes to a bit array.
 */',
        'startLine' => 534,
        'endLine' => 559,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'appendAlphanumericBytes' => 
      array (
        'name' => 'appendAlphanumericBytes',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 566,
            'endLine' => 566,
            'startColumn' => 53,
            'endColumn' => 67,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 566,
            'endLine' => 566,
            'startColumn' => 70,
            'endColumn' => 83,
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
 * Appends alpha-numeric bytes to a bit array.
 *
 * @throws WriterException if an invalid alphanumeric code was found
 */',
        'startLine' => 566,
        'endLine' => 594,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'append8BitBytes' => 
      array (
        'name' => 'append8BitBytes',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 601,
            'endLine' => 601,
            'startColumn' => 45,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 601,
            'endLine' => 601,
            'startColumn' => 62,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'encoding' => 
          array (
            'name' => 'encoding',
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
            'startLine' => 601,
            'endLine' => 601,
            'startColumn' => 78,
            'endColumn' => 93,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Appends regular 8-bit bytes to a bit array.
 *
 * @throws WriterException if content cannot be encoded to target encoding
 */',
        'startLine' => 601,
        'endLine' => 614,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'appendKanjiBytes' => 
      array (
        'name' => 'appendKanjiBytes',
        'parameters' => 
        array (
          'content' => 
          array (
            'name' => 'content',
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
            'startLine' => 622,
            'endLine' => 622,
            'startColumn' => 46,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 622,
            'endLine' => 622,
            'startColumn' => 63,
            'endColumn' => 76,
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
 * Appends KANJI bytes to a bit array.
 *
 * @throws WriterException if content does not seem to be encoded in SHIFT-JIS
 * @throws WriterException if an invalid byte sequence occurs
 */',
        'startLine' => 622,
        'endLine' => 655,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'aliasName' => NULL,
      ),
      'appendEci' => 
      array (
        'name' => 'appendEci',
        'parameters' => 
        array (
          'eci' => 
          array (
            'name' => 'eci',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\CharacterSetEci',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 660,
            'endLine' => 660,
            'startColumn' => 39,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'bits' => 
          array (
            'name' => 'bits',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Common\\BitArray',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 660,
            'endLine' => 660,
            'startColumn' => 61,
            'endColumn' => 74,
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
 * Appends ECI information to a bit array.
 */',
        'startLine' => 660,
        'endLine' => 665,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'BaconQrCode\\Encoder',
        'declaringClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'implementingClassName' => 'BaconQrCode\\Encoder\\Encoder',
        'currentClassName' => 'BaconQrCode\\Encoder\\Encoder',
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