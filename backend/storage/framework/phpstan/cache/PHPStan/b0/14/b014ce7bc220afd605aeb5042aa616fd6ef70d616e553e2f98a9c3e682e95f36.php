<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../bacon/bacon-qr-code/src/Renderer/Module/ModuleInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-BaconQrCode\Renderer\Module\ModuleInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-c643acaf6b2e5ce50194cacf61cff18a3b5d37513ed06e4816074ef989053eb3-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'BaconQrCode\\Renderer\\Module\\ModuleInterface',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../bacon/bacon-qr-code/src/Renderer/Module/ModuleInterface.php',
      ),
    ),
    'namespace' => 'BaconQrCode\\Renderer\\Module',
    'name' => 'BaconQrCode\\Renderer\\Module\\ModuleInterface',
    'shortName' => 'ModuleInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Interface describing how modules should be rendered.
 *
 * A module always receives a byte matrix (with values either being 1 or 0). It returns a path, where the origin
 * coordinate (0, 0) equals the top left corner of the first matrix value.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 18,
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
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'createPath' => 
      array (
        'name' => 'createPath',
        'parameters' => 
        array (
          'matrix' => 
          array (
            'name' => 'matrix',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Encoder\\ByteMatrix',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 17,
            'endLine' => 17,
            'startColumn' => 32,
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
            'name' => 'BaconQrCode\\Renderer\\Path\\Path',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 17,
        'endLine' => 17,
        'startColumn' => 5,
        'endColumn' => 58,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Module',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Module\\ModuleInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Module\\ModuleInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Module\\ModuleInterface',
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