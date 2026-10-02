<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-hash_update
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'hash_update',
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
                  'startLine' => 18,
                  'endLine' => 18,
                  'startTokenPos' => 35,
                  'startFilePos' => 622,
                  'endTokenPos' => 41,
                  'endFilePos' => 645,
                ),
              ),
              'default' => 
              array (
                'code' => '"resource"',
                'attributes' => 
                array (
                  'startLine' => 18,
                  'endLine' => 18,
                  'startTokenPos' => 47,
                  'startFilePos' => 657,
                  'endTokenPos' => 47,
                  'endFilePos' => 666,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 18,
        'endLine' => 19,
        'startColumn' => 9,
        'endColumn' => 28,
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
        'startLine' => 20,
        'endLine' => 20,
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
        'name' => 'bool',
        'isIdentifier' => true,
      ),
    ),
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
            'code' => '["8.4" => "true"]',
            'attributes' => 
            array (
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 11,
              'startFilePos' => 497,
              'endTokenPos' => 17,
              'endFilePos' => 513,
            ),
          ),
          'default' => 
          array (
            'code' => '"bool"',
            'attributes' => 
            array (
              'startLine' => 16,
              'endLine' => 16,
              'startTokenPos' => 23,
              'startFilePos' => 525,
              'endTokenPos' => 23,
              'endFilePos' => 530,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * (PHP 5 &gt;= 5.1.2, PECL hash &gt;= 1.1)<br/>
 * Pump data into an active hashing context
 * @link https://php.net/manual/en/function.hash-update.php
 * @param HashContext|resource $context <p>
 * Hashing context returned by {@see hash_init}.
 * </p>
 * @param string $data <p>
 * Message to be included in the hash digest.
 * </p>
 * @return bool <b>TRUE</b>.
 */',
    'startLine' => 16,
    'endLine' => 23,
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
        'name' => 'hash_update',
        'filename' => 'phpstorm-stubs:hash/hash.stub',
        'extensionName' => 'hash',
        'aliasName' => NULL,
      ),
    ),
  ),
));