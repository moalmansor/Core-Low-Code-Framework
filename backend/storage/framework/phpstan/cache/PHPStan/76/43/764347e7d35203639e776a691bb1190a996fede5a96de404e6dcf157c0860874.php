<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-imagedestroy
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'imagedestroy',
    'parameters' => 
    array (
      'image' => 
      array (
        'name' => 'image',
        'default' => NULL,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'GdImage',
            'isIdentifier' => false,
          ),
        ),
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 27,
        'endColumn' => 41,
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
        'name' => 'JetBrains\\PhpStorm\\Deprecated',
        'isRepeated' => false,
        'arguments' => 
        array (
          0 => 
          array (
            'code' => '\'Deprecated: it has no effect\'',
            'attributes' => 
            array (
              'startLine' => 11,
              'endLine' => 11,
              'startTokenPos' => 11,
              'startFilePos' => 360,
              'endTokenPos' => 11,
              'endFilePos' => 389,
            ),
          ),
          'since' => 
          array (
            'code' => '\'8.5\'',
            'attributes' => 
            array (
              'startLine' => 11,
              'endLine' => 11,
              'startTokenPos' => 17,
              'startFilePos' => 399,
              'endTokenPos' => 17,
              'endFilePos' => 403,
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
            'code' => '[\'8.5\' => \'true\']',
            'attributes' => 
            array (
              'startLine' => 12,
              'endLine' => 12,
              'startTokenPos' => 24,
              'startFilePos' => 465,
              'endTokenPos' => 30,
              'endFilePos' => 481,
            ),
          ),
          'default' => 
          array (
            'code' => '\'bool\'',
            'attributes' => 
            array (
              'startLine' => 12,
              'endLine' => 12,
              'startTokenPos' => 36,
              'startFilePos' => 493,
              'endTokenPos' => 36,
              'endFilePos' => 498,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Destroy an image
 * @link https://php.net/manual/en/function.imagedestroy.php
 * @param resource|GdImage $image A GdImage object, returned by one of the image creation functions,
 * such as imagecreatetruecolor.
 * @return bool true on success or false on failure.
 */',
    'startLine' => 11,
    'endLine' => 15,
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
        'name' => 'imagedestroy',
        'filename' => 'phpstorm-stubs:gd/gd.stub',
        'extensionName' => 'gd',
        'aliasName' => NULL,
      ),
    ),
  ),
));