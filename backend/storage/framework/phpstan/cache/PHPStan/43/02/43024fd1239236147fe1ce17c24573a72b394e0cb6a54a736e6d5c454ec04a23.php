<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-hash_hmac
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'hash_hmac',
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
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 24,
        'endColumn' => 35,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'data' => 
      array (
        'name' => 'data',
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
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 38,
        'endColumn' => 49,
        'parameterIndex' => 1,
        'isOptional' => false,
      ),
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
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 52,
        'endColumn' => 62,
        'parameterIndex' => 2,
        'isOptional' => false,
      ),
      'binary' => 
      array (
        'name' => 'binary',
        'default' => 
        array (
          'code' => '\\false',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 37,
            'startFilePos' => 1352,
            'endTokenPos' => 37,
            'endFilePos' => 1356,
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
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 65,
        'endColumn' => 84,
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
        'name' => 'string',
        'isIdentifier' => true,
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
    ),
    'docComment' => '/**
 * (PHP 5 &gt;= 5.1.2, PECL hash &gt;= 1.1)<br/>
 * Generate a keyed hash value using the HMAC method
 * @link https://php.net/manual/en/function.hash-hmac.php
 * @param string $algo <p>
 * Name of selected hashing algorithm (i.e. "md5", "sha256", "haval160,4", etc..) See <b>hash_algos</b> for a list of supported algorithms.<br/>
 * Since 7.2.0 usage of non-cryptographic hash functions (adler32, crc32, crc32b, fnv132, fnv1a32, fnv164, fnv1a64, joaat) was disabled.
 * </p>
 * @param string $data <p>
 * Message to be hashed.
 * </p>
 * @param string $key <p>
 * Shared secret key used for generating the HMAC variant of the message digest.
 * </p>
 * @param bool $binary [optional] <p>
 * When set to <b>TRUE</b>, outputs raw binary data.
 * <b>FALSE</b> outputs lowercase hexits.
 * </p>
 * @return string a string containing the calculated message digest as lowercase hexits
 * unless <i>binary</i> is set to true in which case the raw
 * binary representation of the message digest is returned.
 * @throws \\ValueError Throws a ValueError exception if algo is unknown or is a non-cryptographic
 * hash function.
 */',
    'startLine' => 28,
    'endLine' => 31,
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
        'name' => 'hash_hmac',
        'filename' => 'phpstorm-stubs:hash/hash.stub',
        'extensionName' => 'hash',
        'aliasName' => NULL,
      ),
    ),
  ),
));