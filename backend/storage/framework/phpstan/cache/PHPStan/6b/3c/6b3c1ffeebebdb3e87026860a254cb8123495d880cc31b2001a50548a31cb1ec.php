<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-splfileinfo
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'SplFileInfo',
        'filename' => 'phpstorm-stubs:SPL/SPL_c1.stub',
        'extensionName' => 'SPL',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'SplFileInfo',
    'shortName' => 'SplFileInfo',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The SplFileInfo class offers a high-level object oriented interface to
 * information for an individual file.
 * @link https://php.net/manual/en/class.splfileinfo.php
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 409,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Stringable',
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'filename' => 
          array (
            'name' => 'filename',
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
                      'startLine' => 18,
                      'endLine' => 18,
                      'startTokenPos' => 30,
                      'startFilePos' => 585,
                      'endTokenPos' => 36,
                      'endFilePos' => 603,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 18,
                      'endLine' => 18,
                      'startTokenPos' => 42,
                      'startFilePos' => 615,
                      'endTokenPos' => 42,
                      'endFilePos' => 616,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 18,
            'endLine' => 19,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Construct a new SplFileInfo object
 * @link https://php.net/manual/en/splfileinfo.construct.php
 * @param string $filename Path to the file.
 * @since 5.1
 */',
        'startLine' => 17,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getPath' => 
      array (
        'name' => 'getPath',
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
 * Gets the path without filename
 * @link https://php.net/manual/en/splfileinfo.getpath.php
 * @return string the path to the file.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 30,
        'endLine' => 33,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getFilename' => 
      array (
        'name' => 'getFilename',
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
 * Gets the filename
 * @link https://php.net/manual/en/splfileinfo.getfilename.php
 * @return string The filename.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 41,
        'endLine' => 44,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getExtension' => 
      array (
        'name' => 'getExtension',
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
 * Gets the file extension
 * @link https://php.net/manual/en/splfileinfo.getextension.php
 * @return string a string containing the file extension, or an
 * empty string if the file has no extension.
 * @since 5.3
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 53,
        'endLine' => 56,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getBasename' => 
      array (
        'name' => 'getBasename',
        'parameters' => 
        array (
          'suffix' => 
          array (
            'name' => 'suffix',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 70,
                'endLine' => 70,
                'startTokenPos' => 157,
                'startFilePos' => 2470,
                'endTokenPos' => 157,
                'endFilePos' => 2471,
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
                      'startLine' => 69,
                      'endLine' => 69,
                      'startTokenPos' => 135,
                      'startFilePos' => 2406,
                      'endTokenPos' => 141,
                      'endFilePos' => 2424,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 69,
                      'endLine' => 69,
                      'startTokenPos' => 147,
                      'startFilePos' => 2436,
                      'endTokenPos' => 147,
                      'endFilePos' => 2437,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 69,
            'endLine' => 70,
            'startColumn' => 13,
            'endColumn' => 31,
            'parameterIndex' => 0,
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets the base name of the file
 * @link https://php.net/manual/en/splfileinfo.getbasename.php
 * @param string $suffix [optional] <p>
 * Optional suffix to omit from the base name returned.
 * </p>
 * @return string the base name without path information.
 * @since 5.2
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 67,
        'endLine' => 73,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getPathname' => 
      array (
        'name' => 'getPathname',
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
 * Gets the path to the file
 * @link https://php.net/manual/en/splfileinfo.getpathname.php
 * @return string The path to the file.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 81,
        'endLine' => 84,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getPerms' => 
      array (
        'name' => 'getPerms',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets file permissions
 * @link https://php.net/manual/en/splfileinfo.getperms.php
 * @return int|false The file permissions on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 92,
        'endLine' => 95,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getInode' => 
      array (
        'name' => 'getInode',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets the inode for the file
 * @link https://php.net/manual/en/splfileinfo.getinode.php
 * @return int|false The inode number for the filesystem object on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 104,
        'endLine' => 107,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getSize' => 
      array (
        'name' => 'getSize',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets file size
 * @link https://php.net/manual/en/splfileinfo.getsize.php
 * @return int|false The filesize in bytes on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 116,
        'endLine' => 119,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getOwner' => 
      array (
        'name' => 'getOwner',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets the owner of the file
 * @link https://php.net/manual/en/splfileinfo.getowner.php
 * @return int|false The owner id in numerical format on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 128,
        'endLine' => 131,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getGroup' => 
      array (
        'name' => 'getGroup',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets the file group
 * @link https://php.net/manual/en/splfileinfo.getgroup.php
 * @return int|false The group id in numerical format on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 140,
        'endLine' => 143,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getATime' => 
      array (
        'name' => 'getATime',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets last access time of the file
 * @link https://php.net/manual/en/splfileinfo.getatime.php
 * @return int|false The time the file was last accessed on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 152,
        'endLine' => 155,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getMTime' => 
      array (
        'name' => 'getMTime',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets the last modified time
 * @link https://php.net/manual/en/splfileinfo.getmtime.php
 * @return int|false The last modified time for the file, in a Unix timestamp on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 163,
        'endLine' => 166,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getCTime' => 
      array (
        'name' => 'getCTime',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets the inode change time
 * @link https://php.net/manual/en/splfileinfo.getctime.php
 * @return int|false The last change time, in a Unix timestamp on success, or <b>FALSE</b> on failure.
 * @since 5.1
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 175,
        'endLine' => 178,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getType' => 
      array (
        'name' => 'getType',
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
 * Gets file type
 * @link https://php.net/manual/en/splfileinfo.gettype.php
 * @return string|false A string representing the type of the entry. May be one of file, link, dir, block, fifo, char, socket, or unknown, or <b>FALSE</b> on failure.
 * May be one of file, link,
 * or dir
 * @since 5.1
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 189,
        'endLine' => 192,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'isWritable' => 
      array (
        'name' => 'isWritable',
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
 * Tells if the entry is writable
 * @link https://php.net/manual/en/splfileinfo.iswritable.php
 * @return bool true if writable, false otherwise;
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 200,
        'endLine' => 203,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'isReadable' => 
      array (
        'name' => 'isReadable',
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
 * Tells if file is readable
 * @link https://php.net/manual/en/splfileinfo.isreadable.php
 * @return bool true if readable, false otherwise.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 211,
        'endLine' => 214,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'isExecutable' => 
      array (
        'name' => 'isExecutable',
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
 * Tells if the file is executable
 * @link https://php.net/manual/en/splfileinfo.isexecutable.php
 * @return bool true if executable, false otherwise.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 222,
        'endLine' => 225,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'isFile' => 
      array (
        'name' => 'isFile',
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
 * Tells if the object references a regular file
 * @link https://php.net/manual/en/splfileinfo.isfile.php
 * @return bool true if the file exists and is a regular file (not a link), false otherwise.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 233,
        'endLine' => 236,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'isDir' => 
      array (
        'name' => 'isDir',
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
 * Tells if the file is a directory
 * @link https://php.net/manual/en/splfileinfo.isdir.php
 * @return bool true if a directory, false otherwise.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 244,
        'endLine' => 247,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'isLink' => 
      array (
        'name' => 'isLink',
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
 * Tells if the file is a link
 * @link https://php.net/manual/en/splfileinfo.islink.php
 * @return bool true if the file is a link, false otherwise.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 255,
        'endLine' => 258,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getLinkTarget' => 
      array (
        'name' => 'getLinkTarget',
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
 * Gets the target of a link
 * @link https://php.net/manual/en/splfileinfo.getlinktarget.php
 * @return string|false The target of the filesystem link on success, or <b>FALSE</b> on failure.
 * @since 5.2
 * @throws \\RuntimeException on error.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 267,
        'endLine' => 270,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getRealPath' => 
      array (
        'name' => 'getRealPath',
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
 * Gets absolute path to file
 * @link https://php.net/manual/en/splfileinfo.getrealpath.php
 * @return string|false the path to the file, or <b>FALSE</b> if the file does not exist.
 * @since 5.2
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 278,
        'endLine' => 281,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getFileInfo' => 
      array (
        'name' => 'getFileInfo',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 296,
                'endLine' => 296,
                'startTokenPos' => 608,
                'startFilePos' => 11071,
                'endTokenPos' => 608,
                'endFilePos' => 11074,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 295,
                      'endLine' => 295,
                      'startTokenPos' => 584,
                      'startFilePos' => 10998,
                      'endTokenPos' => 590,
                      'endFilePos' => 11021,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 295,
                      'endLine' => 295,
                      'startTokenPos' => 596,
                      'startFilePos' => 11033,
                      'endTokenPos' => 596,
                      'endFilePos' => 11034,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 295,
            'endLine' => 296,
            'startColumn' => 13,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'SplFileInfo',
            'isIdentifier' => false,
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
 * Gets an SplFileInfo object for the file
 * @template T of SplFileInfo
 * @link https://php.net/manual/en/splfileinfo.getfileinfo.php
 * @param class-string<T> $class [optional] <p>
 * Name of an <b>SplFileInfo</b> derived class to use.
 * </p>
 * @return T An <b>SplFileInfo</b> object created for the file.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 293,
        'endLine' => 299,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'getPathInfo' => 
      array (
        'name' => 'getPathInfo',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 314,
                'endLine' => 314,
                'startTokenPos' => 659,
                'startFilePos' => 11864,
                'endTokenPos' => 659,
                'endFilePos' => 11867,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 313,
                      'endLine' => 313,
                      'startTokenPos' => 635,
                      'startFilePos' => 11791,
                      'endTokenPos' => 641,
                      'endFilePos' => 11814,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 313,
                      'endLine' => 313,
                      'startTokenPos' => 647,
                      'startFilePos' => 11826,
                      'endTokenPos' => 647,
                      'endFilePos' => 11827,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 313,
            'endLine' => 314,
            'startColumn' => 13,
            'endColumn' => 37,
            'parameterIndex' => 0,
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
                  'name' => 'SplFileInfo',
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
 * Gets an SplFileInfo object for the path
 * @template T of SplFileInfo
 * @link https://php.net/manual/en/splfileinfo.getpathinfo.php
 * @param class-string<T> $class [optional] <p>
 * Name of an <b>SplFileInfo</b> derived class to use.
 * </p>
 * @return T|null A <b>SplFileInfo</b> object for the parent path of the file on success, or <b>NULL</b> on failure.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 311,
        'endLine' => 317,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'openFile' => 
      array (
        'name' => 'openFile',
        'parameters' => 
        array (
          'mode' => 
          array (
            'name' => 'mode',
            'default' => 
            array (
              'code' => '\'r\'',
              'attributes' => 
              array (
                'startLine' => 338,
                'endLine' => 338,
                'startTokenPos' => 709,
                'startFilePos' => 12890,
                'endTokenPos' => 709,
                'endFilePos' => 12892,
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
                      'startLine' => 337,
                      'endLine' => 337,
                      'startTokenPos' => 687,
                      'startFilePos' => 12828,
                      'endTokenPos' => 693,
                      'endFilePos' => 12846,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 337,
                      'endLine' => 337,
                      'startTokenPos' => 699,
                      'startFilePos' => 12858,
                      'endTokenPos' => 699,
                      'endFilePos' => 12859,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 337,
            'endLine' => 338,
            'startColumn' => 13,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'useIncludePath' => 
          array (
            'name' => 'useIncludePath',
            'default' => 
            array (
              'code' => '\\false',
              'attributes' => 
              array (
                'startLine' => 340,
                'endLine' => 340,
                'startTokenPos' => 737,
                'startFilePos' => 13029,
                'endTokenPos' => 737,
                'endFilePos' => 13033,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'bool\']',
                    'attributes' => 
                    array (
                      'startLine' => 339,
                      'endLine' => 339,
                      'startTokenPos' => 715,
                      'startFilePos' => 12961,
                      'endTokenPos' => 721,
                      'endFilePos' => 12977,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 339,
                      'endLine' => 339,
                      'startTokenPos' => 727,
                      'startFilePos' => 12989,
                      'endTokenPos' => 727,
                      'endFilePos' => 12990,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 339,
            'endLine' => 340,
            'startColumn' => 13,
            'endColumn' => 40,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'context' => 
          array (
            'name' => 'context',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 341,
                'endLine' => 341,
                'startTokenPos' => 744,
                'startFilePos' => 13059,
                'endTokenPos' => 744,
                'endFilePos' => 13062,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 341,
            'endLine' => 341,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'SplFileObject',
            'isIdentifier' => false,
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
 * Gets an SplFileObject object for the file
 * @link https://php.net/manual/en/splfileinfo.openfile.php
 * @param string $mode [optional] <p>
 * The mode for opening the file. See the <b>fopen</b>
 * documentation for descriptions of possible modes. The default
 * is read only.
 * </p>
 * @param bool $useIncludePath [optional] <p>
 * </p>
 * @param resource $context [optional] <p>
 * </p>
 * @return SplFileObject The opened file as an <b>SplFileObject</b> object.
 * @since 5.1
 * @throws \\RuntimeException If the file cannot be opened (e.g. insufficient access rights).
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 335,
        'endLine' => 344,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'setFileClass' => 
      array (
        'name' => 'setFileClass',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => 
            array (
              'code' => '\\SplFileObject::class',
              'attributes' => 
              array (
                'startLine' => 359,
                'endLine' => 359,
                'startTokenPos' => 793,
                'startFilePos' => 13785,
                'endTokenPos' => 795,
                'endFilePos' => 13804,
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
                      'startLine' => 358,
                      'endLine' => 358,
                      'startTokenPos' => 771,
                      'startFilePos' => 13722,
                      'endTokenPos' => 777,
                      'endFilePos' => 13740,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 358,
                      'endLine' => 358,
                      'startTokenPos' => 783,
                      'startFilePos' => 13752,
                      'endTokenPos' => 783,
                      'endFilePos' => 13753,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 358,
            'endLine' => 359,
            'startColumn' => 13,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
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
 * Sets the class name used with <b>SplFileInfo::openFile</b>
 * @template T of SplFileObject
 * @link https://php.net/manual/en/splfileinfo.setfileclass.php
 * @param class-string<T> $class [optional] <p>
 * The class name to use when openFile() is called.
 * </p>
 * @return void No value is returned.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 356,
        'endLine' => 362,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      'setInfoClass' => 
      array (
        'name' => 'setInfoClass',
        'parameters' => 
        array (
          'class' => 
          array (
            'name' => 'class',
            'default' => 
            array (
              'code' => '\\SplFileInfo::class',
              'attributes' => 
              array (
                'startLine' => 377,
                'endLine' => 377,
                'startTokenPos' => 844,
                'startFilePos' => 14483,
                'endTokenPos' => 846,
                'endFilePos' => 14500,
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
                      'startLine' => 376,
                      'endLine' => 376,
                      'startTokenPos' => 822,
                      'startFilePos' => 14420,
                      'endTokenPos' => 828,
                      'endFilePos' => 14438,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 376,
                      'endLine' => 376,
                      'startTokenPos' => 834,
                      'startFilePos' => 14450,
                      'endTokenPos' => 834,
                      'endFilePos' => 14451,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 376,
            'endLine' => 377,
            'startColumn' => 13,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
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
 * Sets the class used with getFileInfo and getPathInfo
 * @template T of SplFileInfo
 * @link https://php.net/manual/en/splfileinfo.setinfoclass.php
 * @param class-string<T> $class [optional] <p>
 * The class name to use.
 * </p>
 * @return void No value is returned.
 * @since 5.1
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 374,
        'endLine' => 380,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      '__toString' => 
      array (
        'name' => '__toString',
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.0\' => \'string\']',
                'attributes' => 
                array (
                  'startLine' => 387,
                  'endLine' => 387,
                  'startTokenPos' => 862,
                  'startFilePos' => 14814,
                  'endTokenPos' => 868,
                  'endFilePos' => 14832,
                ),
              ),
              'default' => 
              array (
                'code' => '\'\'',
                'attributes' => 
                array (
                  'startLine' => 387,
                  'endLine' => 387,
                  'startTokenPos' => 874,
                  'startFilePos' => 14844,
                  'endTokenPos' => 874,
                  'endFilePos' => 14845,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * Returns the path to the file as a string
 * @link https://php.net/manual/en/splfileinfo.tostring.php
 * @return string the path to the file.
 * @since 5.1
 */',
        'startLine' => 387,
        'endLine' => 390,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      '_bad_state_ex' => 
      array (
        'name' => '_bad_state_ex',
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
        ),
        'docComment' => '/**
 * 
 */',
        'startLine' => 394,
        'endLine' => 396,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 33,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
        'aliasName' => NULL,
      ),
      '__wakeup' => 
      array (
        'name' => '__wakeup',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 397,
        'endLine' => 399,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
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
        'startLine' => 405,
        'endLine' => 408,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'SplFileInfo',
        'implementingClassName' => 'SplFileInfo',
        'currentClassName' => 'SplFileInfo',
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