<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-imagejpeg
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'imagejpeg',
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
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 24,
        'endColumn' => 38,
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
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 21,
            'startFilePos' => 970,
            'endTokenPos' => 21,
            'endFilePos' => 973,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => false,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 41,
        'endColumn' => 52,
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
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 30,
            'startFilePos' => 991,
            'endTokenPos' => 31,
            'endFilePos' => 992,
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
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 55,
        'endColumn' => 71,
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
        'name' => 'bool',
        'isIdentifier' => true,
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Output image to browser or file
 * @link https://php.net/manual/en/function.imagejpeg.php
 * @param resource|GdImage $image A GdImage object, returned by one of the image creation functions,
 * such as imagecreatetruecolor.
 * @param string $file [optional] <p>
 * The path to save the file to. If not set or null, the raw image stream
 * will be outputted directly.
 * </p>
 * <p>
 * To skip this argument in order to provide the
 * quality parameter, use null.
 * </p>
 * @param int $quality [optional] <p>
 * quality is optional, and ranges from 0 (worst
 * quality, smaller file) to 100 (best quality, biggest file). The
 * default is the default IJG quality value (about 75).
 * </p>
 * @return bool true on success or false on failure.
 * @throws \\ValueError Throws a ValueError if quality is invalid.
 */',
    'startLine' => 25,
    'endLine' => 27,
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
        'name' => 'imagejpeg',
        'filename' => 'phpstorm-stubs:gd/gd.stub',
        'extensionName' => 'gd',
        'aliasName' => NULL,
      ),
    ),
  ),
));