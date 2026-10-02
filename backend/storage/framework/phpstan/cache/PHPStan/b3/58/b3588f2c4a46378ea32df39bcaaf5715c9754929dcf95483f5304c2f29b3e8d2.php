<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Filesystem/FilesystemManager.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Filesystem\FilesystemManager
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-e32ec81301ad27ca2ea0b1d0f9a4e4069b7d723e234648b85d553b5c75d3e579-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Filesystem\\FilesystemManager',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Filesystem/FilesystemManager.php',
      ),
    ),
    'namespace' => 'Illuminate\\Filesystem',
    'name' => 'Illuminate\\Filesystem\\FilesystemManager',
    'shortName' => 'FilesystemManager',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @mixin \\Illuminate\\Contracts\\Filesystem\\Filesystem
 * @mixin \\Illuminate\\Filesystem\\FilesystemAdapter
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 31,
    'endLine' => 489,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Filesystem\\Factory',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'app' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'name' => 'app',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The application instance.
 *
 * @var \\Illuminate\\Contracts\\Foundation\\Application
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 38,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 19,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'disks' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'name' => 'disks',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 45,
            'endLine' => 45,
            'startTokenPos' => 160,
            'startFilePos' => 1419,
            'endTokenPos' => 161,
            'endFilePos' => 1420,
          ),
        ),
        'docComment' => '/**
 * The array of resolved filesystem drivers.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 45,
        'endLine' => 45,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'customCreators' => 
      array (
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'name' => 'customCreators',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[]',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 172,
            'startFilePos' => 1543,
            'endTokenPos' => 173,
            'endFilePos' => 1544,
          ),
        ),
        'docComment' => '/**
 * The registered custom driver creators.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
    ),
    'immediateMethods' => 
    array (
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 59,
            'endLine' => 59,
            'startColumn' => 33,
            'endColumn' => 36,
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
 * Create a new filesystem manager instance.
 *
 * @param  \\Illuminate\\Contracts\\Foundation\\Application  $app
 */',
        'startLine' => 59,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'drive' => 
      array (
        'name' => 'drive',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 70,
                'endLine' => 70,
                'startTokenPos' => 212,
                'startFilePos' => 1948,
                'endTokenPos' => 212,
                'endFilePos' => 1951,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 27,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a filesystem instance.
 *
 * @param  string|null  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 70,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'disk' => 
      array (
        'name' => 'disk',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 81,
                'endLine' => 81,
                'startTokenPos' => 241,
                'startFilePos' => 2195,
                'endTokenPos' => 241,
                'endFilePos' => 2198,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 81,
            'endLine' => 81,
            'startColumn' => 26,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a filesystem instance.
 *
 * @param  \\UnitEnum|string|null  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 81,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'cloud' => 
      array (
        'name' => 'cloud',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get a default cloud filesystem instance.
 *
 * @return \\Illuminate\\Contracts\\Filesystem\\Cloud
 */',
        'startLine' => 93,
        'endLine' => 98,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'build' => 
      array (
        'name' => 'build',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 106,
            'endLine' => 106,
            'startColumn' => 27,
            'endColumn' => 33,
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
 * Build an on-demand disk.
 *
 * @param  string|array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 106,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'get' => 
      array (
        'name' => 'get',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 120,
            'endLine' => 120,
            'startColumn' => 28,
            'endColumn' => 32,
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
 * Attempt to get the disk from the local cache.
 *
 * @param  string  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 120,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'resolve' => 
      array (
        'name' => 'resolve',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 134,
            'endLine' => 134,
            'startColumn' => 32,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 134,
                'endLine' => 134,
                'startTokenPos' => 434,
                'startFilePos' => 3500,
                'endTokenPos' => 434,
                'endFilePos' => 3503,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 134,
            'endLine' => 134,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Resolve the given disk.
 *
 * @param  string  $name
 * @param  array|null  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 134,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'callCustomCreator' => 
      array (
        'name' => 'callCustomCreator',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 163,
            'endLine' => 163,
            'startColumn' => 42,
            'endColumn' => 54,
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
 * Call a custom driver creator.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 163,
        'endLine' => 166,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createLocalDriver' => 
      array (
        'name' => 'createLocalDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 175,
            'endLine' => 175,
            'startColumn' => 39,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => '\'local\'',
              'attributes' => 
              array (
                'startLine' => 175,
                'endLine' => 175,
                'startTokenPos' => 643,
                'startFilePos' => 4693,
                'endTokenPos' => 643,
                'endFilePos' => 4699,
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
            'startLine' => 175,
            'endLine' => 175,
            'startColumn' => 54,
            'endColumn' => 75,
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
 * Create an instance of the local driver.
 *
 * @param  array  $config
 * @param  string  $name
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 175,
        'endLine' => 198,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createFtpDriver' => 
      array (
        'name' => 'createFtpDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 37,
            'endColumn' => 49,
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
 * Create an instance of the ftp driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 206,
        'endLine' => 215,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createSftpDriver' => 
      array (
        'name' => 'createSftpDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 223,
            'endLine' => 223,
            'startColumn' => 38,
            'endColumn' => 50,
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
 * Create an instance of the sftp driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 */',
        'startLine' => 223,
        'endLine' => 236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createS3Driver' => 
      array (
        'name' => 'createS3Driver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 244,
            'endLine' => 244,
            'startColumn' => 36,
            'endColumn' => 48,
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
 * Create an instance of the Amazon S3 driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Cloud
 */',
        'startLine' => 244,
        'endLine' => 263,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'formatS3Config' => 
      array (
        'name' => 'formatS3Config',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 273,
            'endLine' => 273,
            'startColumn' => 39,
            'endColumn' => 51,
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
 * Format the given S3 configuration with the default options.
 *
 * @param  array  $config
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 273,
        'endLine' => 304,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createScopedDriver' => 
      array (
        'name' => 'createScopedDriver',
        'parameters' => 
        array (
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 314,
            'endLine' => 314,
            'startColumn' => 40,
            'endColumn' => 52,
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
 * Create a scoped driver.
 *
 * @param  array  $config
 * @return \\Illuminate\\Contracts\\Filesystem\\Filesystem
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 314,
        'endLine' => 345,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'createFlysystem' => 
      array (
        'name' => 'createFlysystem',
        'parameters' => 
        array (
          'adapter' => 
          array (
            'name' => 'adapter',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'League\\Flysystem\\FilesystemAdapter',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 354,
            'endLine' => 354,
            'startColumn' => 40,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'config' => 
          array (
            'name' => 'config',
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
            'startLine' => 354,
            'endLine' => 354,
            'startColumn' => 67,
            'endColumn' => 79,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a Flysystem instance with the given adapter.
 *
 * @param  \\League\\Flysystem\\FilesystemAdapter  $adapter
 * @param  array  $config
 * @return \\League\\Flysystem\\FilesystemOperator
 */',
        'startLine' => 354,
        'endLine' => 376,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'set' => 
      array (
        'name' => 'set',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 385,
            'endLine' => 385,
            'startColumn' => 25,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'disk' => 
          array (
            'name' => 'disk',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 385,
            'endLine' => 385,
            'startColumn' => 32,
            'endColumn' => 36,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the given disk instance.
 *
 * @param  string  $name
 * @param  mixed  $disk
 * @return $this
 */',
        'startLine' => 385,
        'endLine' => 390,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'getConfig' => 
      array (
        'name' => 'getConfig',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 398,
            'endLine' => 398,
            'startColumn' => 34,
            'endColumn' => 38,
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
 * Get the filesystem connection configuration.
 *
 * @param  string  $name
 * @return array
 */',
        'startLine' => 398,
        'endLine' => 401,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'getDefaultDriver' => 
      array (
        'name' => 'getDefaultDriver',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default driver name.
 *
 * @return string
 */',
        'startLine' => 408,
        'endLine' => 411,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'getDefaultCloudDriver' => 
      array (
        'name' => 'getDefaultCloudDriver',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the default cloud driver name.
 *
 * @return string
 */',
        'startLine' => 418,
        'endLine' => 421,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'forgetDisk' => 
      array (
        'name' => 'forgetDisk',
        'parameters' => 
        array (
          'disk' => 
          array (
            'name' => 'disk',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 429,
            'endLine' => 429,
            'startColumn' => 32,
            'endColumn' => 36,
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
 * Unset the given disk instances.
 *
 * @param  array|string  $disk
 * @return $this
 */',
        'startLine' => 429,
        'endLine' => 436,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'purge' => 
      array (
        'name' => 'purge',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 444,
                'endLine' => 444,
                'startTokenPos' => 2119,
                'startFilePos' => 12597,
                'endTokenPos' => 2119,
                'endFilePos' => 12600,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 444,
            'endLine' => 444,
            'startColumn' => 27,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Disconnect the given disk and remove from local cache.
 *
 * @param  string|null  $name
 * @return void
 */',
        'startLine' => 444,
        'endLine' => 449,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'extend' => 
      array (
        'name' => 'extend',
        'parameters' => 
        array (
          'driver' => 
          array (
            'name' => 'driver',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 458,
            'endLine' => 458,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Closure',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 458,
            'endLine' => 458,
            'startColumn' => 37,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Register a custom driver creator Closure.
 *
 * @param  string  $driver
 * @param  \\Closure  $callback
 * @return $this
 */',
        'startLine' => 458,
        'endLine' => 463,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      'setApplication' => 
      array (
        'name' => 'setApplication',
        'parameters' => 
        array (
          'app' => 
          array (
            'name' => 'app',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 471,
            'endLine' => 471,
            'startColumn' => 36,
            'endColumn' => 39,
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
 * Set the application instance used by the manager.
 *
 * @param  \\Illuminate\\Contracts\\Foundation\\Application  $app
 * @return $this
 */',
        'startLine' => 471,
        'endLine' => 476,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'aliasName' => NULL,
      ),
      '__call' => 
      array (
        'name' => '__call',
        'parameters' => 
        array (
          'method' => 
          array (
            'name' => 'method',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 485,
            'endLine' => 485,
            'startColumn' => 28,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'parameters' => 
          array (
            'name' => 'parameters',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 485,
            'endLine' => 485,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Dynamically call the default driver instance.
 *
 * @param  string  $method
 * @param  array  $parameters
 * @return mixed
 */',
        'startLine' => 485,
        'endLine' => 488,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Filesystem',
        'declaringClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'implementingClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
        'currentClassName' => 'Illuminate\\Filesystem\\FilesystemManager',
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