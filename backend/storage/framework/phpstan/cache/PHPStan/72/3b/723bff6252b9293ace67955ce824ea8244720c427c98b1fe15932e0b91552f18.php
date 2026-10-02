<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-fsockopen
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'fsockopen',
    'parameters' => 
    array (
      'hostname' => 
      array (
        'name' => 'hostname',
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
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 9,
        'endColumn' => 24,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'port' => 
      array (
        'name' => 'port',
        'default' => 
        array (
          'code' => '-1',
          'attributes' => 
          array (
            'startLine' => 49,
            'endLine' => 49,
            'startTokenPos' => 34,
            'startFilePos' => 1732,
            'endTokenPos' => 35,
            'endFilePos' => 1733,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              'from' => 
              array (
                'code' => '\'7.1\'',
                'attributes' => 
                array (
                  'startLine' => 48,
                  'endLine' => 48,
                  'startTokenPos' => 24,
                  'startFilePos' => 1704,
                  'endTokenPos' => 24,
                  'endFilePos' => 1708,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 48,
        'endLine' => 49,
        'startColumn' => 9,
        'endColumn' => 22,
        'parameterIndex' => 1,
        'isOptional' => true,
      ),
      'error_code' => 
      array (
        'name' => 'error_code',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 43,
            'startFilePos' => 1759,
            'endTokenPos' => 43,
            'endFilePos' => 1762,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => true,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 9,
        'endColumn' => 27,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'error_message' => 
      array (
        'name' => 'error_message',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 51,
            'endLine' => 51,
            'startTokenPos' => 51,
            'startFilePos' => 1791,
            'endTokenPos' => 51,
            'endFilePos' => 1794,
          ),
        ),
        'type' => NULL,
        'isVariadic' => false,
        'byRef' => true,
        'isPromoted' => false,
        'attributes' => 
        array (
        ),
        'startLine' => 51,
        'endLine' => 51,
        'startColumn' => 9,
        'endColumn' => 30,
        'parameterIndex' => 3,
        'isOptional' => true,
      ),
      'timeout' => 
      array (
        'name' => 'timeout',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 52,
            'endLine' => 52,
            'startTokenPos' => 61,
            'startFilePos' => 1823,
            'endTokenPos' => 61,
            'endFilePos' => 1826,
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
                  'name' => 'float',
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
        ),
        'startLine' => 52,
        'endLine' => 52,
        'startColumn' => 9,
        'endColumn' => 30,
        'parameterIndex' => 4,
        'isOptional' => true,
      ),
    ),
    'returnsReference' => false,
    'returnType' => NULL,
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * Open Internet or Unix domain socket connection
 * @link https://php.net/manual/en/function.fsockopen.php
 * @param string $hostname <p>
 * If you have compiled in OpenSSL support, you may prefix the
 * hostname with either ssl://
 * or tls:// to use an SSL or TLS client connection
 * over TCP/IP to connect to the remote host.
 * </p>
 * @param int $port <p>
 * The port number.
 * </p>
 * @param int &$error_code [optional] <p>
 * If provided, holds the system level error number that occurred in the
 * system-level connect() call.
 * </p>
 * <p>
 * If the value returned in errno is
 * 0 and the function returned false, it is an
 * indication that the error occurred before the
 * connect() call. This is most likely due to a
 * problem initializing the socket.
 * </p>
 * @param string &$error_message [optional] <p>
 * The error message as a string.
 * </p>
 * @param float|null $timeout [optional] <p>
 * The connection timeout, in seconds.
 * </p>
 * <p>
 * If you need to set a timeout for reading/writing data over the
 * socket, use stream_set_timeout, as the
 * timeout parameter to
 * fsockopen only applies while connecting the
 * socket.
 * </p>
 * @return resource|false fsockopen returns a file pointer which may be used
 * together with the other file functions (such as
 * fgets, fgetss,
 * fwrite, fclose, and
 * feof). If the call fails, it will return false
 */',
    'startLine' => 46,
    'endLine' => 55,
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
        'name' => 'fsockopen',
        'filename' => 'phpstorm-stubs:standard/standard_7.stub',
        'extensionName' => 'standard',
        'aliasName' => NULL,
      ),
    ),
  ),
));