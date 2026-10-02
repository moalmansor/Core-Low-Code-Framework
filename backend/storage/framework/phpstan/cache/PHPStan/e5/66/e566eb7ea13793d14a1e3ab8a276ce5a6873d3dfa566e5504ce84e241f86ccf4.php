<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-sessionhandlerinterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'SessionHandlerInterface',
        'filename' => 'phpstorm-stubs:session/SessionHandler.stub',
        'extensionName' => 'session',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'SessionHandlerInterface',
    'shortName' => 'SessionHandlerInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * <b>SessionHandlerInterface</b> is an interface which defines
 * a prototype for creating a custom session handler.
 * In order to pass a custom session handler to
 * session_set_save_handler() using its OOP invocation,
 * the class must implement this interface.
 * @link https://php.net/manual/en/class.sessionhandlerinterface.php
 * @since 5.4
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 124,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
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
      'close' => 
      array (
        'name' => 'close',
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
 * Close the session
 * @link https://php.net/manual/en/sessionhandlerinterface.close.php
 * @return bool <p>
 * The return value (usually TRUE on success, FALSE on failure).
 * Note this value is returned internally to PHP for processing.
 * </p>
 * @since 5.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 25,
        'endLine' => 26,
        'startColumn' => 9,
        'endColumn' => 38,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SessionHandlerInterface',
        'implementingClassName' => 'SessionHandlerInterface',
        'currentClassName' => 'SessionHandlerInterface',
        'aliasName' => NULL,
      ),
      'destroy' => 
      array (
        'name' => 'destroy',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
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
                      'startLine' => 40,
                      'endLine' => 40,
                      'startTokenPos' => 48,
                      'startFilePos' => 1545,
                      'endTokenPos' => 54,
                      'endFilePos' => 1563,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 40,
                      'endLine' => 40,
                      'startTokenPos' => 60,
                      'startFilePos' => 1575,
                      'endTokenPos' => 60,
                      'endFilePos' => 1576,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 40,
            'endLine' => 41,
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
 * Destroy a session
 * @link https://php.net/manual/en/sessionhandlerinterface.destroy.php
 * @param string $id The session ID being destroyed.
 * @return bool <p>
 * The return value (usually TRUE on success, FALSE on failure).
 * Note this value is returned internally to PHP for processing.
 * </p>
 * @since 5.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 38,
        'endLine' => 42,
        'startColumn' => 9,
        'endColumn' => 16,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SessionHandlerInterface',
        'implementingClassName' => 'SessionHandlerInterface',
        'currentClassName' => 'SessionHandlerInterface',
        'aliasName' => NULL,
      ),
      'gc' => 
      array (
        'name' => 'gc',
        'parameters' => 
        array (
          'max_lifetime' => 
          array (
            'name' => 'max_lifetime',
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
                      'startLine' => 60,
                      'endLine' => 60,
                      'startTokenPos' => 109,
                      'startFilePos' => 2501,
                      'endTokenPos' => 115,
                      'endFilePos' => 2516,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 60,
                      'endLine' => 60,
                      'startTokenPos' => 121,
                      'startFilePos' => 2528,
                      'endTokenPos' => 121,
                      'endFilePos' => 2529,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 60,
            'endLine' => 61,
            'startColumn' => 13,
            'endColumn' => 29,
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'7.1\' => \'int|false\']',
                'attributes' => 
                array (
                  'startLine' => 57,
                  'endLine' => 57,
                  'startTokenPos' => 79,
                  'startFilePos' => 2311,
                  'endTokenPos' => 85,
                  'endFilePos' => 2332,
                ),
              ),
              'default' => 
              array (
                'code' => '\'bool\'',
                'attributes' => 
                array (
                  'startLine' => 57,
                  'endLine' => 57,
                  'startTokenPos' => 91,
                  'startFilePos' => 2344,
                  'endTokenPos' => 91,
                  'endFilePos' => 2349,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Cleanup old sessions
 * @link https://php.net/manual/en/sessionhandlerinterface.gc.php
 * @param int $max_lifetime <p>
 * Sessions that have not updated for
 * the last maxlifetime seconds will be removed.
 * </p>
 * @return int|false <p>
 * Returns the number of deleted sessions on success, or false on failure. Prior to PHP version 7.1, the function returned true on success.
 * Note this value is returned internally to PHP for processing.
 * </p>
 * @since 5.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 57,
        'endLine' => 62,
        'startColumn' => 9,
        'endColumn' => 10,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SessionHandlerInterface',
        'implementingClassName' => 'SessionHandlerInterface',
        'currentClassName' => 'SessionHandlerInterface',
        'aliasName' => NULL,
      ),
      'open' => 
      array (
        'name' => 'open',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
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
                      'startLine' => 77,
                      'endLine' => 77,
                      'startTokenPos' => 148,
                      'startFilePos' => 3240,
                      'endTokenPos' => 154,
                      'endFilePos' => 3258,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 77,
                      'endLine' => 77,
                      'startTokenPos' => 160,
                      'startFilePos' => 3270,
                      'endTokenPos' => 160,
                      'endFilePos' => 3271,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 77,
            'endLine' => 78,
            'startColumn' => 13,
            'endColumn' => 24,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
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
                      'startLine' => 79,
                      'endLine' => 79,
                      'startTokenPos' => 172,
                      'startFilePos' => 3367,
                      'endTokenPos' => 178,
                      'endFilePos' => 3385,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 79,
                      'endLine' => 79,
                      'startTokenPos' => 184,
                      'startFilePos' => 3397,
                      'endTokenPos' => 184,
                      'endFilePos' => 3398,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 79,
            'endLine' => 80,
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
 * Initialize session
 * @link https://php.net/manual/en/sessionhandlerinterface.open.php
 * @param string $path The path where to store/retrieve the session.
 * @param string $name The session name.
 * @return bool <p>
 * The return value (usually TRUE on success, FALSE on failure).
 * Note this value is returned internally to PHP for processing.
 * </p>
 * @since 5.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 75,
        'endLine' => 81,
        'startColumn' => 9,
        'endColumn' => 16,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SessionHandlerInterface',
        'implementingClassName' => 'SessionHandlerInterface',
        'currentClassName' => 'SessionHandlerInterface',
        'aliasName' => NULL,
      ),
      'read' => 
      array (
        'name' => 'read',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
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
                      'startLine' => 96,
                      'endLine' => 96,
                      'startTokenPos' => 214,
                      'startFilePos' => 4089,
                      'endTokenPos' => 220,
                      'endFilePos' => 4107,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 96,
                      'endLine' => 96,
                      'startTokenPos' => 226,
                      'startFilePos' => 4119,
                      'endTokenPos' => 226,
                      'endFilePos' => 4120,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 96,
            'endLine' => 97,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 0,
            'isOptional' => false,
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
                  'name' => 'string',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Read session data
 * @link https://php.net/manual/en/sessionhandlerinterface.read.php
 * @param string $id The session id to read data for.
 * @return string|false <p>
 * Returns an encoded string of the read data.
 * If nothing was read, it must return false.
 * Note this value is returned internally to PHP for processing.
 * </p>
 * @since 5.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 94,
        'endLine' => 98,
        'startColumn' => 9,
        'endColumn' => 24,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SessionHandlerInterface',
        'implementingClassName' => 'SessionHandlerInterface',
        'currentClassName' => 'SessionHandlerInterface',
        'aliasName' => NULL,
      ),
      'write' => 
      array (
        'name' => 'write',
        'parameters' => 
        array (
          'id' => 
          array (
            'name' => 'id',
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
                      'startLine' => 119,
                      'endLine' => 119,
                      'startTokenPos' => 258,
                      'startFilePos' => 5091,
                      'endTokenPos' => 264,
                      'endFilePos' => 5109,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 119,
                      'endLine' => 119,
                      'startTokenPos' => 270,
                      'startFilePos' => 5121,
                      'endTokenPos' => 270,
                      'endFilePos' => 5122,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 119,
            'endLine' => 120,
            'startColumn' => 13,
            'endColumn' => 22,
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
                      'startLine' => 121,
                      'endLine' => 121,
                      'startTokenPos' => 282,
                      'startFilePos' => 5216,
                      'endTokenPos' => 288,
                      'endFilePos' => 5234,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 121,
                      'endLine' => 121,
                      'startTokenPos' => 294,
                      'startFilePos' => 5246,
                      'endTokenPos' => 294,
                      'endFilePos' => 5247,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 121,
            'endLine' => 122,
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
 * Write session data
 * @link https://php.net/manual/en/sessionhandlerinterface.write.php
 * @param string $id The session id.
 * @param string $data <p>
 * The encoded session data. This data is the
 * result of the PHP internally encoding
 * the $_SESSION superglobal to a serialized
 * string and passing it as this parameter.
 * Please note sessions use an alternative serialization method.
 * </p>
 * @return bool <p>
 * The return value (usually TRUE on success, FALSE on failure).
 * Note this value is returned internally to PHP for processing.
 * </p>
 * @since 5.4
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 117,
        'endLine' => 123,
        'startColumn' => 9,
        'endColumn' => 16,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SessionHandlerInterface',
        'implementingClassName' => 'SessionHandlerInterface',
        'currentClassName' => 'SessionHandlerInterface',
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