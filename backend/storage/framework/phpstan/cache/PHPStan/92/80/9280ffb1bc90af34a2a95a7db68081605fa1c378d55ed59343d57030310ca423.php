<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-imagepng
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'imagepng',
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
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 23,
        'endColumn' => 37,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'file' => 
      array (
        'name' => 'file',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 21,
            'startFilePos' => 1165,
            'endTokenPos' => 21,
            'endFilePos' => 1168,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 40,
        'endColumn' => 51,
        'parameterIndex' => 1,
        'isOptional' => true,
      ),
      'quality' => 
      array (
        'name' => 'quality',
        'default' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 30,
            'startFilePos' => 1186,
            'endTokenPos' => 31,
            'endFilePos' => 1187,
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
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 54,
        'endColumn' => 70,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'filters' => 
      array (
        'name' => 'filters',
        'default' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 40,
            'startFilePos' => 1205,
            'endTokenPos' => 41,
            'endFilePos' => 1206,
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
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 73,
        'endColumn' => 89,
        'parameterIndex' => 3,
        'isOptional' => true,
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
    ),
    'docComment' => '/**
 * Output a PNG image to either the browser or a file
 * @link https://php.net/manual/en/function.imagepng.php
 * @param resource|GdImage $image A GdImage object, returned by one of the image creation functions,
 * such as imagecreatetruecolor.
 * @param string $file [optional] <p>
 * The path to save the file to. If not set or null, the raw image stream
 * will be outputted directly.
 * </p>
 * <p>
 * null is invalid if the quality and
 * filters arguments are not used.
 * </p>
 * @param int $quality [optional] <p>
 * Compression level: from 0 (no compression) to 9.
 * </p>
 * @param int $filters [optional] <p>
 * Allows reducing the PNG file size. It is a bitmask field which may be
 * set to any combination of the PNG_FILTER_XXX
 * constants. PNG_NO_FILTER or
 * PNG_ALL_FILTERS may also be used to respectively
 * disable or activate all filters.
 * </p>
 * @return bool true on success or false on failure.
 * @throws \\ValueError Throws a ValueError if quality is invalid.
 */',
    'startLine' => 30,
    'endLine' => 32,
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
        'name' => 'imagepng',
        'filename' => 'phpstorm-stubs:gd/gd.stub',
        'extensionName' => 'gd',
        'aliasName' => NULL,
      ),
    ),
  ),
));