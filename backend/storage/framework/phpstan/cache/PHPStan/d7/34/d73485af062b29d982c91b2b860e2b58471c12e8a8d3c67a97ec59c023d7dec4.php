<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-strrpos
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'strrpos',
    'parameters' => 
    array (
      'haystack' => 
      array (
        'name' => 'haystack',
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
        'startColumn' => 22,
        'endColumn' => 37,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'needle' => 
      array (
        'name' => 'needle',
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
        'startColumn' => 40,
        'endColumn' => 53,
        'parameterIndex' => 1,
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
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 32,
            'startFilePos' => 1265,
            'endTokenPos' => 32,
            'endFilePos' => 1265,
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
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 56,
        'endColumn' => 70,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => 
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
              'name' => 'int',
              'isIdentifier' => true,
            ),
          ),
          1 => 
          array (
            'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
            'data' => 
            array (
              'name' => 'false',
              'isIdentifier' => true,
            ),
          ),
        ),
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
 * Find the position of the last occurrence of a substring in a string
 * @link https://php.net/manual/en/function.strrpos.php
 * @param string $haystack <p>
 * The string to search in.
 * </p>
 * @param string $needle <p>
 * If <b>needle</b> is not a string, it is converted to an integer and applied as the ordinal value of a character.
 * </p>
 * @param int $offset [optional] <p>
 * If specified, search will start this number of characters counted from the beginning of the string. If the value is negative, search will instead start from that many characters from the end of the string, searching backwards.
 * </p>
 * @return int|false <p>
 * Returns the position where the needle exists relative to the beginning of
 * the <b>haystack</b> string (independent of search direction
 * or offset).
 * Also note that string positions start at 0, and not 1.
 * </p>
 * <p>
 * Returns <b>FALSE</b> if the needle was not found.
 * </p>
 * @throws \\ValueError If offset is greater than the length of haystack, a ValueError will be
 * thrown.
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
        'name' => 'strrpos',
        'filename' => 'phpstorm-stubs:standard/standard_1.stub',
        'extensionName' => 'standard',
        'aliasName' => NULL,
      ),
    ),
  ),
));