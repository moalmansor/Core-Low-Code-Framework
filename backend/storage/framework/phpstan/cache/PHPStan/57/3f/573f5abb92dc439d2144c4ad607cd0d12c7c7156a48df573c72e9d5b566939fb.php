<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../bacon/bacon-qr-code/src/Renderer/Image/ImageBackEndInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-BaconQrCode\Renderer\Image\ImageBackEndInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-c9b026d3498ae283fd3f6c255f350835f2c017a6bacd0211b2142893dfd210fa-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../bacon/bacon-qr-code/src/Renderer/Image/ImageBackEndInterface.php',
      ),
    ),
    'namespace' => 'BaconQrCode\\Renderer\\Image',
    'name' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
    'shortName' => 'ImageBackEndInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Interface for back ends able to to produce path based images.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 14,
    'endLine' => 87,
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
      'new' => 
      array (
        'name' => 'new',
        'parameters' => 
        array (
          'size' => 
          array (
            'name' => 'size',
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
            ),
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 25,
            'endColumn' => 33,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'backgroundColor' => 
          array (
            'name' => 'backgroundColor',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Renderer\\Color\\ColorInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 36,
            'endColumn' => 66,
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
        ),
        'docComment' => '/**
 * Starts a new image.
 *
 * If a previous image was already started, previous data get erased.
 */',
        'startLine' => 21,
        'endLine' => 21,
        'startColumn' => 5,
        'endColumn' => 75,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'scale' => 
      array (
        'name' => 'scale',
        'parameters' => 
        array (
          'size' => 
          array (
            'name' => 'size',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
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
            'startColumn' => 27,
            'endColumn' => 37,
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
        ),
        'docComment' => '/**
 * Transforms all following drawing operation coordinates by scaling them by a given factor.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 46,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'translate' => 
      array (
        'name' => 'translate',
        'parameters' => 
        array (
          'x' => 
          array (
            'name' => 'x',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 31,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'y' => 
          array (
            'name' => 'y',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 35,
            'endLine' => 35,
            'startColumn' => 41,
            'endColumn' => 48,
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
        ),
        'docComment' => '/**
 * Transforms all following drawing operation coordinates by translating them by a given amount.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 35,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 57,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'rotate' => 
      array (
        'name' => 'rotate',
        'parameters' => 
        array (
          'degrees' => 
          array (
            'name' => 'degrees',
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
            ),
            'startLine' => 42,
            'endLine' => 42,
            'startColumn' => 28,
            'endColumn' => 39,
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
        ),
        'docComment' => '/**
 * Transforms all following drawing operation coordinates by rotating them by a given amount.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'push' => 
      array (
        'name' => 'push',
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
 * Pushes the current coordinate transformation onto a stack.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 49,
        'endLine' => 49,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'pop' => 
      array (
        'name' => 'pop',
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
 * Pops the last coordinate transformation from a stack.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 33,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'drawPathWithColor' => 
      array (
        'name' => 'drawPathWithColor',
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
                'name' => 'BaconQrCode\\Renderer\\Path\\Path',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 63,
            'endLine' => 63,
            'startColumn' => 39,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'color' => 
          array (
            'name' => 'color',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Renderer\\Color\\ColorInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 63,
            'endLine' => 63,
            'startColumn' => 51,
            'endColumn' => 71,
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
        ),
        'docComment' => '/**
 * Draws a path with a given color.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 63,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 80,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'drawPathWithGradient' => 
      array (
        'name' => 'drawPathWithGradient',
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
                'name' => 'BaconQrCode\\Renderer\\Path\\Path',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 9,
            'endColumn' => 18,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'gradient' => 
          array (
            'name' => 'gradient',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'BaconQrCode\\Renderer\\RendererStyle\\Gradient',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 72,
            'endLine' => 72,
            'startColumn' => 9,
            'endColumn' => 26,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'x' => 
          array (
            'name' => 'x',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 9,
            'endColumn' => 16,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'y' => 
          array (
            'name' => 'y',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 74,
            'endLine' => 74,
            'startColumn' => 9,
            'endColumn' => 16,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'width' => 
          array (
            'name' => 'width',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 75,
            'endLine' => 75,
            'startColumn' => 9,
            'endColumn' => 20,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
          'height' => 
          array (
            'name' => 'height',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'float',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 76,
            'endLine' => 76,
            'startColumn' => 9,
            'endColumn' => 21,
            'parameterIndex' => 5,
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
        ),
        'docComment' => '/**
 * Draws a path with a given gradient which spans the box described by the position and size.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 70,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 13,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'aliasName' => NULL,
      ),
      'done' => 
      array (
        'name' => 'done',
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
        ),
        'docComment' => '/**
 * Ends the image drawing operation and returns the resulting blob.
 *
 * This should reset the state of the back end and thus this method should only be callable once per image.
 *
 * @throws RuntimeException if no image was started yet.
 */',
        'startLine' => 86,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 36,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'BaconQrCode\\Renderer\\Image',
        'declaringClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'implementingClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
        'currentClassName' => 'BaconQrCode\\Renderer\\Image\\ImageBackEndInterface',
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