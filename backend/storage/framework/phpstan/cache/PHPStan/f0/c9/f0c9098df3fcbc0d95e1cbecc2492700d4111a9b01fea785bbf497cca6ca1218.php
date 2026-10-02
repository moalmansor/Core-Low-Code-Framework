<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Records/FileStore.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Records\FileStore
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-2ef4f1428525df8a9f7f0d816db3fd8033c30486048ba3002baee5979e5a505f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Records\\FileStore',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Records/FileStore.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Records',
    'name' => 'App\\Modules\\Records\\FileStore',
    'shortName' => 'FileStore',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Stores uploads outside the public root after type, size, and virus checks
 * (specification §5). Images are re-encoded to strip embedded payloads.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 22,
    'endLine' => 112,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'DISK' => 
      array (
        'declaringClassName' => 'App\\Modules\\Records\\FileStore',
        'implementingClassName' => 'App\\Modules\\Records\\FileStore',
        'name' => 'DISK',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'private\'',
          'attributes' => 
          array (
            'startLine' => 24,
            'endLine' => 24,
            'startTokenPos' => 83,
            'startFilePos' => 644,
            'endTokenPos' => 83,
            'endFilePos' => 652,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 24,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 34,
      ),
      'IMAGE_MIMES' => 
      array (
        'declaringClassName' => 'App\\Modules\\Records\\FileStore',
        'implementingClassName' => 'App\\Modules\\Records\\FileStore',
        'name' => 'IMAGE_MIMES',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'png\' => [\'image/png\'], \'jpg\' => [\'image/jpeg\'], \'jpeg\' => [\'image/jpeg\'], \'webp\' => [\'image/webp\'], \'ico\' => [\'image/vnd.microsoft.icon\', \'image/x-icon\']]',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 33,
            'startTokenPos' => 96,
            'startFilePos' => 740,
            'endTokenPos' => 146,
            'endFilePos' => 942,
          ),
        ),
        'docComment' => '/** Extension => allowed sniffed MIME types. */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
      'settings' => 
      array (
        'declaringClassName' => 'App\\Modules\\Records\\FileStore',
        'implementingClassName' => 'App\\Modules\\Records\\FileStore',
        'name' => 'settings',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Modules\\Core\\Settings\\SettingsService',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 9,
        'endColumn' => 50,
        'isPromoted' => true,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'scanner' => 
      array (
        'declaringClassName' => 'App\\Modules\\Records\\FileStore',
        'implementingClassName' => 'App\\Modules\\Records\\FileStore',
        'name' => 'scanner',
        'modifiers' => 132,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'App\\Infrastructure\\Storage\\VirusScanner',
            'isIdentifier' => false,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 9,
        'endColumn' => 46,
        'isPromoted' => true,
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
          'settings' => 
          array (
            'name' => 'settings',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Modules\\Core\\Settings\\SettingsService',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 36,
            'endLine' => 36,
            'startColumn' => 9,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'scanner' => 
          array (
            'name' => 'scanner',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'App\\Infrastructure\\Storage\\VirusScanner',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => true,
            'attributes' => 
            array (
            ),
            'startLine' => 37,
            'endLine' => 37,
            'startColumn' => 9,
            'endColumn' => 46,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 8,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Records',
        'declaringClassName' => 'App\\Modules\\Records\\FileStore',
        'implementingClassName' => 'App\\Modules\\Records\\FileStore',
        'currentClassName' => 'App\\Modules\\Records\\FileStore',
        'aliasName' => NULL,
      ),
      'storeImage' => 
      array (
        'name' => 'storeImage',
        'parameters' => 
        array (
          'upload' => 
          array (
            'name' => 'upload',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Http\\UploadedFile',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 32,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'ownerType' => 
          array (
            'name' => 'ownerType',
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
            'startLine' => 40,
            'endLine' => 40,
            'startColumn' => 54,
            'endColumn' => 70,
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
            'name' => 'App\\Modules\\Records\\Models\\StoredFile',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 40,
        'endLine' => 85,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Records',
        'declaringClassName' => 'App\\Modules\\Records\\FileStore',
        'implementingClassName' => 'App\\Modules\\Records\\FileStore',
        'currentClassName' => 'App\\Modules\\Records\\FileStore',
        'aliasName' => NULL,
      ),
      'reencode' => 
      array (
        'name' => 'reencode',
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
            ),
            'startLine' => 88,
            'endLine' => 88,
            'startColumn' => 31,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'extension' => 
          array (
            'name' => 'extension',
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
            'startLine' => 88,
            'endLine' => 88,
            'startColumn' => 45,
            'endColumn' => 61,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/** @return array{0: string, 1: int, 2: int} */',
        'startLine' => 88,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Modules\\Records',
        'declaringClassName' => 'App\\Modules\\Records\\FileStore',
        'implementingClassName' => 'App\\Modules\\Records\\FileStore',
        'currentClassName' => 'App\\Modules\\Records\\FileStore',
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