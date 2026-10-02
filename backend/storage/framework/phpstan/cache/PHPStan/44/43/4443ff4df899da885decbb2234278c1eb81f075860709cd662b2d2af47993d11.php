<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-arrayiterator
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'ArrayIterator',
        'filename' => 'phpstorm-stubs:SPL/SPL.stub',
        'extensionName' => 'SPL',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'ArrayIterator',
    'shortName' => 'ArrayIterator',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * This iterator allows to unset and modify values and keys while iterating
 * over Arrays and Objects.
 * @link https://php.net/manual/en/class.arrayiterator.php
 * @template TKey of array-key
 * @template TValue
 * @template-implements SeekableIterator<TKey, TValue>
 * @template-implements ArrayAccess<TKey, TValue>
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 367,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'SeekableIterator',
      1 => 'ArrayAccess',
      2 => 'Serializable',
      3 => 'Countable',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'STD_PROP_LIST' => 
      array (
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'name' => 'STD_PROP_LIST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 35,
            'startFilePos' => 519,
            'endTokenPos' => 35,
            'endFilePos' => 519,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
      'ARRAY_AS_PROPS' => 
      array (
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'name' => 'ARRAY_AS_PROPS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 46,
            'startFilePos' => 560,
            'endTokenPos' => 46,
            'endFilePos' => 560,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 9,
        'endColumn' => 40,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'array' => 
          array (
            'name' => 'array',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 26,
                'endLine' => 26,
                'startTokenPos' => 85,
                'startFilePos' => 1092,
                'endTokenPos' => 86,
                'endFilePos' => 1093,
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
                      'name' => 'object',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'object|array\']',
                    'attributes' => 
                    array (
                      'startLine' => 25,
                      'endLine' => 25,
                      'startTokenPos' => 61,
                      'startFilePos' => 1017,
                      'endTokenPos' => 67,
                      'endFilePos' => 1041,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 25,
                      'endLine' => 25,
                      'startTokenPos' => 73,
                      'startFilePos' => 1053,
                      'endTokenPos' => 73,
                      'endFilePos' => 1054,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 25,
            'endLine' => 26,
            'startColumn' => 13,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 29,
                'endLine' => 29,
                'startTokenPos' => 124,
                'startFilePos' => 1306,
                'endTokenPos' => 124,
                'endFilePos' => 1306,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'7.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 27,
                      'endLine' => 27,
                      'startTokenPos' => 95,
                      'startFilePos' => 1175,
                      'endTokenPos' => 95,
                      'endFilePos' => 1179,
                    ),
                  ),
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
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 28,
                      'endLine' => 28,
                      'startTokenPos' => 102,
                      'startFilePos' => 1249,
                      'endTokenPos' => 108,
                      'endFilePos' => 1264,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 28,
                      'endLine' => 28,
                      'startTokenPos' => 114,
                      'startFilePos' => 1276,
                      'endTokenPos' => 114,
                      'endFilePos' => 1277,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 27,
            'endLine' => 29,
            'startColumn' => 13,
            'endColumn' => 26,
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
 * Construct an ArrayIterator
 * @link https://php.net/manual/en/arrayiterator.construct.php
 * @param array<TKey, TValue>|object $array The array or object to be iterated on.
 * @param int $flags Flags to control the behaviour of the ArrayObject object.
 * @see ArrayObject::setFlags()
 */',
        'startLine' => 24,
        'endLine' => 32,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'offsetExists' => 
      array (
        'name' => 'offsetExists',
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
                'name' => 'mixed',
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
                    'code' => '[\'8.0\' => \'mixed\']',
                    'attributes' => 
                    array (
                      'startLine' => 44,
                      'endLine' => 44,
                      'startTokenPos' => 148,
                      'startFilePos' => 1828,
                      'endTokenPos' => 154,
                      'endFilePos' => 1845,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 44,
                      'endLine' => 44,
                      'startTokenPos' => 160,
                      'startFilePos' => 1857,
                      'endTokenPos' => 160,
                      'endFilePos' => 1858,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 44,
            'endLine' => 45,
            'startColumn' => 13,
            'endColumn' => 22,
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
 * Check if offset exists
 * @link https://php.net/manual/en/arrayiterator.offsetexists.php
 * @param TKey $key <p>
 * The offset being checked.
 * </p>
 * @return bool true if the offset exists, otherwise false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 42,
        'endLine' => 48,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'offsetGet' => 
      array (
        'name' => 'offsetGet',
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
                'name' => 'mixed',
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
                    'code' => '[\'8.0\' => \'mixed\']',
                    'attributes' => 
                    array (
                      'startLine' => 60,
                      'endLine' => 60,
                      'startTokenPos' => 193,
                      'startFilePos' => 2412,
                      'endTokenPos' => 199,
                      'endFilePos' => 2429,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 60,
                      'endLine' => 60,
                      'startTokenPos' => 205,
                      'startFilePos' => 2441,
                      'endTokenPos' => 205,
                      'endFilePos' => 2442,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 60,
            'endLine' => 61,
            'startColumn' => 13,
            'endColumn' => 22,
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
            'name' => 'mixed',
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
 * Get value for an offset
 * @link https://php.net/manual/en/arrayiterator.offsetget.php
 * @param TKey $key <p>
 * The offset to get the value from.
 * </p>
 * @return TValue|null The value at offset <i>index</i>.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 58,
        'endLine' => 64,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'offsetSet' => 
      array (
        'name' => 'offsetSet',
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
                'name' => 'mixed',
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
                    'code' => '[\'8.0\' => \'mixed\']',
                    'attributes' => 
                    array (
                      'startLine' => 79,
                      'endLine' => 79,
                      'startTokenPos' => 238,
                      'startFilePos' => 3044,
                      'endTokenPos' => 244,
                      'endFilePos' => 3061,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 79,
                      'endLine' => 79,
                      'startTokenPos' => 250,
                      'startFilePos' => 3073,
                      'endTokenPos' => 250,
                      'endFilePos' => 3074,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 79,
            'endLine' => 80,
            'startColumn' => 13,
            'endColumn' => 22,
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
                'name' => 'mixed',
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
                    'code' => '[\'8.0\' => \'mixed\']',
                    'attributes' => 
                    array (
                      'startLine' => 81,
                      'endLine' => 81,
                      'startTokenPos' => 262,
                      'startFilePos' => 3168,
                      'endTokenPos' => 268,
                      'endFilePos' => 3185,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 81,
                      'endLine' => 81,
                      'startTokenPos' => 274,
                      'startFilePos' => 3197,
                      'endTokenPos' => 274,
                      'endFilePos' => 3198,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 81,
            'endLine' => 82,
            'startColumn' => 13,
            'endColumn' => 24,
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
 * Set value for an offset
 * @link https://php.net/manual/en/arrayiterator.offsetset.php
 * @param TKey $key <p>
 * The index to set for.
 * </p>
 * @param TValue $value <p>
 * The new value to store at the index.
 * </p>
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 77,
        'endLine' => 85,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'offsetUnset' => 
      array (
        'name' => 'offsetUnset',
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
                'name' => 'mixed',
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
                    'code' => '[\'8.0\' => \'mixed\']',
                    'attributes' => 
                    array (
                      'startLine' => 97,
                      'endLine' => 97,
                      'startTokenPos' => 307,
                      'startFilePos' => 3728,
                      'endTokenPos' => 313,
                      'endFilePos' => 3745,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 97,
                      'endLine' => 97,
                      'startTokenPos' => 319,
                      'startFilePos' => 3757,
                      'endTokenPos' => 319,
                      'endFilePos' => 3758,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 97,
            'endLine' => 98,
            'startColumn' => 13,
            'endColumn' => 22,
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
 * Unset value for an offset
 * @link https://php.net/manual/en/arrayiterator.offsetunset.php
 * @param TKey $key <p>
 * The offset to unset.
 * </p>
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 95,
        'endLine' => 101,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'append' => 
      array (
        'name' => 'append',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
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
                    'code' => '[\'8.0\' => \'mixed\']',
                    'attributes' => 
                    array (
                      'startLine' => 113,
                      'endLine' => 113,
                      'startTokenPos' => 352,
                      'startFilePos' => 4250,
                      'endTokenPos' => 358,
                      'endFilePos' => 4267,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 113,
                      'endLine' => 113,
                      'startTokenPos' => 364,
                      'startFilePos' => 4279,
                      'endTokenPos' => 364,
                      'endFilePos' => 4280,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 113,
            'endLine' => 114,
            'startColumn' => 13,
            'endColumn' => 24,
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
 * Append an element
 * @link https://php.net/manual/en/arrayiterator.append.php
 * @param TValue $value <p>
 * The value to append.
 * </p>
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 111,
        'endLine' => 117,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'getArrayCopy' => 
      array (
        'name' => 'getArrayCopy',
        'parameters' => 
        array (
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Get array copy
 * @link https://php.net/manual/en/arrayiterator.getarraycopy.php
 * @return array<TKey, TValue> A copy of the array, or array of public properties
 * if ArrayIterator refers to an object.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 125,
        'endLine' => 128,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'count' => 
      array (
        'name' => 'count',
        'parameters' => 
        array (
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
 * Count elements
 * @link https://php.net/manual/en/arrayiterator.count.php
 * @return int<0,max> The number of elements or public properties in the associated
 * array or object, respectively.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 136,
        'endLine' => 139,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'getFlags' => 
      array (
        'name' => 'getFlags',
        'parameters' => 
        array (
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
 * Get flags
 * @link https://php.net/manual/en/arrayiterator.getflags.php
 * @return int The current flags.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 146,
        'endLine' => 149,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'setFlags' => 
      array (
        'name' => 'setFlags',
        'parameters' => 
        array (
          'flags' => 
          array (
            'name' => 'flags',
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
                      'startLine' => 164,
                      'endLine' => 164,
                      'startTokenPos' => 460,
                      'startFilePos' => 6166,
                      'endTokenPos' => 466,
                      'endFilePos' => 6181,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 164,
                      'endLine' => 164,
                      'startTokenPos' => 472,
                      'startFilePos' => 6193,
                      'endTokenPos' => 472,
                      'endFilePos' => 6194,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 164,
            'endLine' => 165,
            'startColumn' => 13,
            'endColumn' => 22,
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
 * Set behaviour flags
 * @link https://php.net/manual/en/arrayiterator.setflags.php
 * @param int $flags <p>
 * A bitmask as follows:
 * 0 = Properties of the object have their normal functionality
 * when accessed as list (var_dump, foreach, etc.).
 * 1 = Array indices can be accessed as properties in read/write.
 * </p>
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 162,
        'endLine' => 168,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'asort' => 
      array (
        'name' => 'asort',
        'parameters' => 
        array (
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\SORT_REGULAR',
              'attributes' => 
              array (
                'startLine' => 178,
                'endLine' => 178,
                'startTokenPos' => 537,
                'startFilePos' => 6724,
                'endTokenPos' => 537,
                'endFilePos' => 6735,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'8.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 177,
                      'endLine' => 177,
                      'startTokenPos' => 527,
                      'startFilePos' => 6691,
                      'endTokenPos' => 527,
                      'endFilePos' => 6695,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 177,
            'endLine' => 178,
            'startColumn' => 13,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.3\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 175,
                  'endLine' => 175,
                  'startTokenPos' => 498,
                  'startFilePos' => 6544,
                  'endTokenPos' => 504,
                  'endFilePos' => 6560,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 175,
                  'endLine' => 175,
                  'startTokenPos' => 510,
                  'startFilePos' => 6572,
                  'endTokenPos' => 510,
                  'endFilePos' => 6577,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * Sort array by values
 * @link https://php.net/manual/en/arrayiterator.asort.php
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 174,
        'endLine' => 181,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'ksort' => 
      array (
        'name' => 'ksort',
        'parameters' => 
        array (
          'flags' => 
          array (
            'name' => 'flags',
            'default' => 
            array (
              'code' => '\\SORT_REGULAR',
              'attributes' => 
              array (
                'startLine' => 191,
                'endLine' => 191,
                'startTokenPos' => 593,
                'startFilePos' => 7232,
                'endTokenPos' => 593,
                'endFilePos' => 7243,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'8.0\'',
                    'attributes' => 
                    array (
                      'startLine' => 190,
                      'endLine' => 190,
                      'startTokenPos' => 583,
                      'startFilePos' => 7199,
                      'endTokenPos' => 583,
                      'endFilePos' => 7203,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 190,
            'endLine' => 191,
            'startColumn' => 13,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.3\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 188,
                  'endLine' => 188,
                  'startTokenPos' => 554,
                  'startFilePos' => 7052,
                  'endTokenPos' => 560,
                  'endFilePos' => 7068,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 188,
                  'endLine' => 188,
                  'startTokenPos' => 566,
                  'startFilePos' => 7080,
                  'endTokenPos' => 566,
                  'endFilePos' => 7085,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * Sort array by keys
 * @link https://php.net/manual/en/arrayiterator.ksort.php
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 187,
        'endLine' => 194,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'uasort' => 
      array (
        'name' => 'uasort',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
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
                    'code' => '[\'8.0\' => \'callable\']',
                    'attributes' => 
                    array (
                      'startLine' => 206,
                      'endLine' => 206,
                      'startTokenPos' => 636,
                      'startFilePos' => 7823,
                      'endTokenPos' => 642,
                      'endFilePos' => 7843,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 206,
                      'endLine' => 206,
                      'startTokenPos' => 648,
                      'startFilePos' => 7855,
                      'endTokenPos' => 648,
                      'endFilePos' => 7856,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 206,
            'endLine' => 207,
            'startColumn' => 13,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.3\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 204,
                  'endLine' => 204,
                  'startTokenPos' => 610,
                  'startFilePos' => 7688,
                  'endTokenPos' => 616,
                  'endFilePos' => 7704,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 204,
                  'endLine' => 204,
                  'startTokenPos' => 622,
                  'startFilePos' => 7716,
                  'endTokenPos' => 622,
                  'endFilePos' => 7721,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * User defined sort
 * @link https://php.net/manual/en/arrayiterator.uasort.php
 * @param callable(TValue, TValue):int $callback <p>
 * The compare function used for the sort.
 * </p>
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 203,
        'endLine' => 210,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'uksort' => 
      array (
        'name' => 'uksort',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
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
                    'code' => '[\'8.0\' => \'callable\']',
                    'attributes' => 
                    array (
                      'startLine' => 222,
                      'endLine' => 222,
                      'startTokenPos' => 697,
                      'startFilePos' => 8465,
                      'endTokenPos' => 703,
                      'endFilePos' => 8485,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 222,
                      'endLine' => 222,
                      'startTokenPos' => 709,
                      'startFilePos' => 8497,
                      'endTokenPos' => 709,
                      'endFilePos' => 8498,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 222,
            'endLine' => 223,
            'startColumn' => 13,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.3\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 220,
                  'endLine' => 220,
                  'startTokenPos' => 671,
                  'startFilePos' => 8330,
                  'endTokenPos' => 677,
                  'endFilePos' => 8346,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 220,
                  'endLine' => 220,
                  'startTokenPos' => 683,
                  'startFilePos' => 8358,
                  'endTokenPos' => 683,
                  'endFilePos' => 8363,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * User defined sort
 * @link https://php.net/manual/en/arrayiterator.uksort.php
 * @param callable(TKey, TKey):int $callback <p>
 * The compare function used for the sort.
 * </p>
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 219,
        'endLine' => 226,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'natsort' => 
      array (
        'name' => 'natsort',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.3\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 233,
                  'endLine' => 233,
                  'startTokenPos' => 732,
                  'startFilePos' => 8855,
                  'endTokenPos' => 738,
                  'endFilePos' => 8871,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 233,
                  'endLine' => 233,
                  'startTokenPos' => 744,
                  'startFilePos' => 8883,
                  'endTokenPos' => 744,
                  'endFilePos' => 8888,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * Sort an array naturally
 * @link https://php.net/manual/en/arrayiterator.natsort.php
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 232,
        'endLine' => 236,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'natcasesort' => 
      array (
        'name' => 'natcasesort',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
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
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.3\' => \'true\']',
                'attributes' => 
                array (
                  'startLine' => 243,
                  'endLine' => 243,
                  'startTokenPos' => 769,
                  'startFilePos' => 9260,
                  'endTokenPos' => 775,
                  'endFilePos' => 9276,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 243,
                  'endLine' => 243,
                  'startTokenPos' => 781,
                  'startFilePos' => 9288,
                  'endTokenPos' => 781,
                  'endFilePos' => 9293,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * Sort an array naturally, case insensitive
 * @link https://php.net/manual/en/arrayiterator.natcasesort.php
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 242,
        'endLine' => 246,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'unserialize' => 
      array (
        'name' => 'unserialize',
        'parameters' => 
        array (
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 258,
                      'endLine' => 258,
                      'startTokenPos' => 813,
                      'startFilePos' => 9822,
                      'endTokenPos' => 819,
                      'endFilePos' => 9840,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 258,
                      'endLine' => 258,
                      'startTokenPos' => 825,
                      'startFilePos' => 9852,
                      'endTokenPos' => 825,
                      'endFilePos' => 9853,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 258,
            'endLine' => 259,
            'startColumn' => 13,
            'endColumn' => 24,
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
 * Unserialize
 * @link https://php.net/manual/en/arrayiterator.unserialize.php
 * @param string $data <p>
 * The serialized ArrayIterator object to be unserialized.
 * </p>
 * @return void
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 256,
        'endLine' => 262,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'serialize' => 
      array (
        'name' => 'serialize',
        'parameters' => 
        array (
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Serialize
 * @link https://php.net/manual/en/arrayiterator.serialize.php
 * @return string The serialized <b>ArrayIterator</b>.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 269,
        'endLine' => 272,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'rewind' => 
      array (
        'name' => 'rewind',
        'parameters' => 
        array (
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
 * Rewind array back to the start
 * @link https://php.net/manual/en/arrayiterator.rewind.php
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 279,
        'endLine' => 282,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'current' => 
      array (
        'name' => 'current',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
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
 * Return current array entry
 * @link https://php.net/manual/en/arrayiterator.current.php
 * @return TValue The current array entry.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 289,
        'endLine' => 292,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'key' => 
      array (
        'name' => 'key',
        'parameters' => 
        array (
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
                  'name' => 'string',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
              2 => 
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
 * Return current array key
 * @link https://php.net/manual/en/arrayiterator.key.php
 * @return TKey|null The key of the current element.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 299,
        'endLine' => 302,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'next' => 
      array (
        'name' => 'next',
        'parameters' => 
        array (
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
 * Move to next entry
 * @link https://php.net/manual/en/arrayiterator.next.php
 * @return void No value is returned.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 309,
        'endLine' => 312,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      'valid' => 
      array (
        'name' => 'valid',
        'parameters' => 
        array (
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Check whether array contains more entries
 * @link https://php.net/manual/en/arrayiterator.valid.php
 * @return bool Returns true if the iterator is valid, otherwise false
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 319,
        'endLine' => 322,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
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
                      'startLine' => 335,
                      'endLine' => 335,
                      'startTokenPos' => 988,
                      'startFilePos' => 12563,
                      'endTokenPos' => 994,
                      'endFilePos' => 12578,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 335,
                      'endLine' => 335,
                      'startTokenPos' => 1000,
                      'startFilePos' => 12590,
                      'endTokenPos' => 1000,
                      'endFilePos' => 12591,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 335,
            'endLine' => 336,
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
 * Seek to position
 * @link https://php.net/manual/en/arrayiterator.seek.php
 * @param int $offset <p>
 * The position to seek to.
 * </p>
 * @return void No value is returned.
 * @throws \\OutOfBoundsException Throws an OutOfBoundsException if the offset is not seekable.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 333,
        'endLine' => 339,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      '__debugInfo' => 
      array (
        'name' => '__debugInfo',
        'parameters' => 
        array (
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * @return array
 * @since 7.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 345,
        'endLine' => 348,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      '__serialize' => 
      array (
        'name' => '__serialize',
        'parameters' => 
        array (
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * @return array
 * @since 7.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 354,
        'endLine' => 357,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
        'aliasName' => NULL,
      ),
      '__unserialize' => 
      array (
        'name' => '__unserialize',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 364,
            'endLine' => 364,
            'startColumn' => 39,
            'endColumn' => 49,
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
 * @param array $data
 * @since 7.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 363,
        'endLine' => 366,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ArrayIterator',
        'implementingClassName' => 'ArrayIterator',
        'currentClassName' => 'ArrayIterator',
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