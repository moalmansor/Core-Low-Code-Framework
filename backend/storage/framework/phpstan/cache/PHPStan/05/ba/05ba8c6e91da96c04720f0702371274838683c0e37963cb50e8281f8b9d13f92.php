<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-hash_final
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'hash_final',
    'parameters' => 
    array (
      'context' => 
      array (
        'name' => 'context',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'HashContext',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '["7.2" => "HashContext"]',
                'attributes' => 
                array (
                  'startLine' => 20,
                  'endLine' => 20,
                  'startTokenPos' => 16,
                  'startFilePos' => 793,
                  'endTokenPos' => 22,
                  'endFilePos' => 816,
                ),
              ),
              'default' => 
              array (
                'code' => '"resource"',
                'attributes' => 
                array (
                  'startLine' => 20,
                  'endLine' => 20,
                  'startTokenPos' => 28,
                  'startFilePos' => 828,
                  'endTokenPos' => 28,
                  'endFilePos' => 837,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 20,
        'endLine' => 21,
        'startColumn' => 9,
        'endColumn' => 28,
        'parameterIndex' => 0,
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
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 43,
            'startFilePos' => 894,
            'endTokenPos' => 43,
            'endFilePos' => 898,
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
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 28,
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
        'name' => 'string',
        'isIdentifier' => true,
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * (PHP 5 &gt;= 5.1.2, PECL hash &gt;= 1.1)<br/>
 * Finalize an incremental hash and return resulting digest
 * @link https://php.net/manual/en/function.hash-final.php
 * @param HashContext|resource $context <p>
 * Hashing context returned by {@see hash_init}.
 * </p>
 * @param bool $binary [optional] <p>
 * When set to <b>TRUE</b>, outputs raw binary data.
 * <b>FALSE</b> outputs lowercase hexits.
 * </p>
 * @return string a string containing the calculated message digest as lowercase hexits
 * unless <i>binary</i> is set to true in which case the raw
 * binary representation of the message digest is returned.
 */',
    'startLine' => 19,
    'endLine' => 25,
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
        'name' => 'hash_final',
        'filename' => 'phpstorm-stubs:hash/hash.stub',
        'extensionName' => 'hash',
        'aliasName' => NULL,
      ),
    ),
  ),
));