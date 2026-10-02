<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-hash_init
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'hash_init',
    'parameters' => 
    array (
      'algo' => 
      array (
        'name' => 'algo',
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
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 9,
        'endColumn' => 20,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'flags' => 
      array (
        'name' => 'flags',
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 36,
            'startTokenPos' => 47,
            'startFilePos' => 1867,
            'endTokenPos' => 47,
            'endFilePos' => 1867,
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
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 9,
        'endColumn' => 22,
        'parameterIndex' => 1,
        'isOptional' => true,
      ),
      'key' => 
      array (
        'name' => 'key',
        'default' => 
        array (
          'code' => '""',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 56,
            'startFilePos' => 1892,
            'endTokenPos' => 56,
            'endFilePos' => 1893,
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
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 9,
        'endColumn' => 24,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'options' => 
      array (
        'name' => 'options',
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 39,
            'endLine' => 39,
            'startTokenPos' => 72,
            'startFilePos' => 1998,
            'endTokenPos' => 73,
            'endFilePos' => 1999,
          ),
        ),
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '\'8.1\'',
                'attributes' => 
                array (
                  'startLine' => 38,
                  'endLine' => 38,
                  'startTokenPos' => 62,
                  'startFilePos' => 1965,
                  'endTokenPos' => 62,
                  'endFilePos' => 1969,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 38,
        'endLine' => 39,
        'startColumn' => 9,
        'endColumn' => 27,
        'parameterIndex' => 3,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
      'data' => 
      array (
        'name' => 'HashContext',
        'isIdentifier' => false,
      ),
    ),
    'attributes' => 
    array (
      0 => 
      array (
        'name' => 'JetBrains\\PhpStorm\\Pure',
        'isRepeated' => false,
        'arguments' => 
        array (
        ),
      ),
      1 => 
      array (
        'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
        'isRepeated' => false,
        'arguments' => 
        array (
          0 => 
          array (
            'code' => '["7.2" => "HashContext"]',
            'attributes' => 
            array (
              'startLine' => 33,
              'endLine' => 33,
              'startTokenPos' => 15,
              'startFilePos' => 1752,
              'endTokenPos' => 21,
              'endFilePos' => 1775,
            ),
          ),
          'default' => 
          array (
            'code' => '"resource"',
            'attributes' => 
            array (
              'startLine' => 33,
              'endLine' => 33,
              'startTokenPos' => 27,
              'startFilePos' => 1787,
              'endTokenPos' => 27,
              'endFilePos' => 1796,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * (PHP 5 &gt;= 5.1.2, PECL hash &gt;= 1.1)<br/>
 * Initialize an incremental hashing context
 * @link https://php.net/manual/en/function.hash-init.php
 * @param string $algo <p>
 * Name of selected hashing algorithm (i.e. "md5", "sha256", "haval160,4", etc..). For a list of supported algorithms see <b>hash_algos</b>.<br/>
 * Since 7.2.0 usage of non-cryptographic hash functions (adler32, crc32, crc32b, fnv132, fnv1a32, fnv164, fnv1a64, joaat) was disabled.
 * </p>
 * @param int $flags [optional] <p>
 * Optional settings for hash generation, currently supports only one option:
 * <b>HASH_HMAC</b>. When specified, the <i>key</i>
 * must be specified.
 * </p>
 * @param string $key <p>
 * When <b>HASH_HMAC</b> is specified for <i>options</i>,
 * a shared secret key to be used with the HMAC hashing method must be supplied in this
 * parameter.
 * </p>
 * @param array $options An array of options for the various hashing algorithms. Currently, only the
 * "seed" parameter is supported by the MurmurHash variants.
 * @return HashContext|resource a Hashing Context resource for use with <b>hash_update</b>,
 * <b>hash_update_stream</b>, <b>hash_update_file</b>,
 * and <b>hash_final</b>.
 * @throws \\ValueError Throws a ValueError exception if algo is unknown or is a non-cryptographic
 * hash function, or if key is empty. Passing configurations options of the wrong type in options
 * will now emit an E_DEPRECATED error because they can be interpreted incorrectly. This will become
 * a ValueError in the future.
 */',
    'startLine' => 32,
    'endLine' => 42,
    'startColumn' => 5,
    'endColumn' => 5,
    'couldThrow' => false,
    'isClosure' => false,
    'isGenerator' => false,
    'isVariadic' => false,
    'isStatic' => false,
    'namespace' => NULL,
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'hash_init',
        'filename' => 'phpstorm-stubs:hash/hash.stub',
        'extensionName' => 'hash',
        'aliasName' => NULL,
      ),
    ),
  ),
));