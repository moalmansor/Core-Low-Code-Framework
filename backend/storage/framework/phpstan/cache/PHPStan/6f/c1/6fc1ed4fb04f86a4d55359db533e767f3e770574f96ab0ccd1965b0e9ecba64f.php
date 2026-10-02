<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-str_split
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'str_split',
    'parameters' => 
    array (
      'string' => 
      array (
        'name' => 'string',
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
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 24,
        'endColumn' => 37,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'length' => 
      array (
        'name' => 'length',
        'default' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 46,
            'startFilePos' => 1060,
            'endTokenPos' => 46,
            'endFilePos' => 1060,
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
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 40,
        'endColumn' => 54,
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
            'code' => '["8.0" => "array"]',
            'attributes' => 
            array (
              'startLine' => 27,
              'endLine' => 27,
              'startTokenPos' => 15,
              'startFilePos' => 962,
              'endTokenPos' => 21,
              'endFilePos' => 979,
            ),
          ),
          'default' => 
          array (
            'code' => '"array|false"',
            'attributes' => 
            array (
              'startLine' => 27,
              'endLine' => 27,
              'startTokenPos' => 27,
              'startFilePos' => 991,
              'endTokenPos' => 27,
              'endFilePos' => 1003,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Convert a string to an array
 * @link https://php.net/manual/en/function.str-split.php
 * @param string $string <p>
 * The input string.
 * </p>
 * @param int $length [optional] <p>
 * Maximum length of the chunk.
 * </p>
 * @return string[]|false <p>If the optional split_length parameter is
 * specified, the returned array will be broken down into chunks with each
 * being split_length in length, otherwise each chunk
 * will be one character in length.
 * </p>
 * <p>
 * <b>FALSE</b> is returned if split_length is less than 1.
 * If the split_length length exceeds the length of
 * string, the entire string is returned as the first
 * (and only) array element.
 * </p>
 * @throws \\ValueError If length is less than 1, a ValueError will be thrown.
 */',
    'startLine' => 26,
    'endLine' => 30,
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
        'name' => 'str_split',
        'filename' => 'phpstorm-stubs:standard/standard_1.stub',
        'extensionName' => 'standard',
        'aliasName' => NULL,
      ),
    ),
  ),
));