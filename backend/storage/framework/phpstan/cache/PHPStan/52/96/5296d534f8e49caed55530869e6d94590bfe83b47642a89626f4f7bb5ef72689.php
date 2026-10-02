<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-imagesavealpha
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'imagesavealpha',
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
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 29,
        'endColumn' => 43,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'enable' => 
      array (
        'name' => 'enable',
        'default' => NULL,
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
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 46,
        'endColumn' => 57,
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
        'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
        'isRepeated' => false,
        'arguments' => 
        array (
          0 => 
          array (
            'code' => '[\'8.5\' => \'true\']',
            'attributes' => 
            array (
              'startLine' => 14,
              'endLine' => 14,
              'startTokenPos' => 11,
              'startFilePos' => 593,
              'endTokenPos' => 17,
              'endFilePos' => 609,
            ),
          ),
          'default' => 
          array (
            'code' => '\'bool\'',
            'attributes' => 
            array (
              'startLine' => 14,
              'endLine' => 14,
              'startTokenPos' => 23,
              'startFilePos' => 621,
              'endTokenPos' => 23,
              'endFilePos' => 626,
            ),
          ),
        ),
      ),
    ),
    'docComment' => '/**
 * Set the flag to save full alpha channel information (as opposed to single-color transparency) when saving PNG images
 * @link https://php.net/manual/en/function.imagesavealpha.php
 * @param resource|GdImage $image A GdImage object, returned by one of the image creation functions,
 * such as imagecreatetruecolor.
 * @param bool $enable <p>
 * Whether to save the alpha channel or not. Default to false.
 * </p>
 * @return bool true on success or false on failure.
 */',
    'startLine' => 14,
    'endLine' => 17,
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
        'name' => 'imagesavealpha',
        'filename' => 'phpstorm-stubs:gd/gd.stub',
        'extensionName' => 'gd',
        'aliasName' => NULL,
      ),
    ),
  ),
));