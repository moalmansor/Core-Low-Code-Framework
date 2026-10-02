<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-seekableiterator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'SeekableIterator',
        'filename' => 'phpstorm-stubs:SPL/SPL.stub',
        'extensionName' => 'SPL',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'SeekableIterator',
    'shortName' => 'SeekableIterator',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The Seekable iterator.
 * @link https://php.net/manual/en/class.seekableiterator.php
 * @template TKey
 * @template-covariant TValue
 * @template-extends Iterator<TKey, TValue>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 29,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Iterator',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'seek' => 
      array (
        'name' => 'seek',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 26,
                      'endLine' => 26,
                      'startTokenPos' => 34,
                      'startFilePos' => 880,
                      'endTokenPos' => 40,
                      'endFilePos' => 895,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 26,
                      'endLine' => 26,
                      'startTokenPos' => 46,
                      'startFilePos' => 907,
                      'endTokenPos' => 46,
                      'endFilePos' => 908,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 26,
            'endLine' => 27,
            'startColumn' => 13,
            'endColumn' => 23,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Seeks to a position
 * @link https://php.net/manual/en/seekableiterator.seek.php
 * @param int $offset <p>
 * The position to seek to.
 * </p>
 * @return void No value is returned.
 * @throws \\OutOfBoundsException Implementations should throw an OutOfBoundsException if the
 * offset is not seekable.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 24,
        'endLine' => 28,
        'startColumn' => 9,
        'endColumn' => 16,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SeekableIterator',
        'implementingClassName' => 'SeekableIterator',
        'currentClassName' => 'SeekableIterator',
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