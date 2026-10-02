<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../guzzlehttp/psr7/src/Uri.php-PHPStan\BetterReflection\Reflection\ReflectionClass-GuzzleHttp\Psr7\Uri
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-0ea2ea4edb63326ba0dd5cf98e426b01e0abf6ebd65029739423c3a1ba5ae084-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'GuzzleHttp\\Psr7\\Uri',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../guzzlehttp/psr7/src/Uri.php',
      ),
    ),
    'namespace' => 'GuzzleHttp\\Psr7',
    'name' => 'GuzzleHttp\\Psr7\\Uri',
    'shortName' => 'Uri',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * PSR-7 URI implementation.
 *
 * @author Michael Dowling
 * @author Tobias Schultze
 * @author Matthew Weier O\'Phinney
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 17,
    'endLine' => 884,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Psr\\Http\\Message\\UriInterface',
      1 => 'JsonSerializable',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'HTTP_DEFAULT_HOST' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'HTTP_DEFAULT_HOST',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'localhost\'',
          'attributes' => 
          array (
            'startLine' => 25,
            'endLine' => 25,
            'startTokenPos' => 50,
            'startFilePos' => 622,
            'endTokenPos' => 50,
            'endFilePos' => 632,
          ),
        ),
        'docComment' => '/**
 * Absolute http and https URIs require a host per RFC 7230 Section 2.7
 * but in generic URIs the host can be empty. So for http(s) URIs
 * we apply this default host when no host is given yet to form a
 * valid URI.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 25,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 50,
      ),
      'DEFAULT_PORTS' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'DEFAULT_PORTS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'http\' => 80, \'https\' => 443, \'ftp\' => 21, \'gopher\' => 70, \'nntp\' => 119, \'news\' => 119, \'telnet\' => 23, \'tn3270\' => 23, \'imap\' => 143, \'pop\' => 110, \'ldap\' => 389]',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 39,
            'startTokenPos' => 61,
            'startFilePos' => 670,
            'endTokenPos' => 140,
            'endFilePos' => 929,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 39,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
      'QUERY_SEPARATORS_REPLACEMENT' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'QUERY_SEPARATORS_REPLACEMENT',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'=\' => \'%3D\', \'&\' => \'%26\', \'+\' => \'%2B\']',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 151,
            'startFilePos' => 982,
            'endTokenPos' => 171,
            'endFilePos' => 1023,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 92,
      ),
    ),
    'immediateProperties' => 
    array (
      'scheme' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'scheme',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 44,
            'endLine' => 44,
            'startTokenPos' => 182,
            'startFilePos' => 1084,
            'endTokenPos' => 182,
            'endFilePos' => 1085,
          ),
        ),
        'docComment' => '/** @var string Uri scheme. */',
        'attributes' => 
        array (
        ),
        'startLine' => 44,
        'endLine' => 44,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'userInfo' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'userInfo',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 47,
            'endLine' => 47,
            'startTokenPos' => 193,
            'startFilePos' => 1151,
            'endTokenPos' => 193,
            'endFilePos' => 1152,
          ),
        ),
        'docComment' => '/** @var string Uri user info. */',
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 27,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'host' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'host',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 50,
            'endLine' => 50,
            'startTokenPos' => 204,
            'startFilePos' => 1209,
            'endTokenPos' => 204,
            'endFilePos' => 1210,
          ),
        ),
        'docComment' => '/** @var string Uri host. */',
        'attributes' => 
        array (
        ),
        'startLine' => 50,
        'endLine' => 50,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'port' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'port',
        'modifiers' => 4,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/** @var int|null Uri port. */',
        'attributes' => 
        array (
        ),
        'startLine' => 53,
        'endLine' => 53,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'path' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'path',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 56,
            'endLine' => 56,
            'startTokenPos' => 222,
            'startFilePos' => 1322,
            'endTokenPos' => 222,
            'endFilePos' => 1323,
          ),
        ),
        'docComment' => '/** @var string Uri path. */',
        'attributes' => 
        array (
        ),
        'startLine' => 56,
        'endLine' => 56,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'query' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'query',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 59,
            'endLine' => 59,
            'startTokenPos' => 233,
            'startFilePos' => 1389,
            'endTokenPos' => 233,
            'endFilePos' => 1390,
          ),
        ),
        'docComment' => '/** @var string Uri query string. */',
        'attributes' => 
        array (
        ),
        'startLine' => 59,
        'endLine' => 59,
        'startColumn' => 5,
        'endColumn' => 24,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fragment' => 
      array (
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'name' => 'fragment',
        'modifiers' => 4,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'\'',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 244,
            'startFilePos' => 1455,
            'endTokenPos' => 244,
            'endFilePos' => 1456,
          ),
        ),
        'docComment' => '/** @var string Uri fragment. */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 27,
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
          'uri' => 
          array (
            'name' => 'uri',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 64,
                'endLine' => 64,
                'startTokenPos' => 259,
                'startFilePos' => 1506,
                'endTokenPos' => 259,
                'endFilePos' => 1507,
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
            'startLine' => 64,
            'endLine' => 64,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 64,
        'endLine' => 79,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'parse' => 
      array (
        'name' => 'parse',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
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
            'startLine' => 96,
            'endLine' => 96,
            'startColumn' => 35,
            'endColumn' => 45,
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
 * UTF-8 aware \\parse_url() replacement.
 *
 * The internal function produces broken output for non ASCII domain names
 * (IDN) when used with locales other than "C".
 *
 * On the other hand, cURL understands IDN correctly only when UTF-8 locale
 * is configured ("C.UTF-8", "en_US.UTF-8", etc.).
 *
 * @see https://bugs.php.net/bug.php?id=52923
 * @see https://www.php.net/manual/en/function.parse-url.php#114817
 * @see https://curl.haxx.se/libcurl/c/CURLOPT_URL.html#ENCODING
 *
 * @return array|false
 */',
        'startLine' => 96,
        'endLine' => 148,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'isPathNoSchemeReference' => 
      array (
        'name' => 'isPathNoSchemeReference',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
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
            'startLine' => 150,
            'endLine' => 150,
            'startColumn' => 53,
            'endColumn' => 63,
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
        ),
        'docComment' => NULL,
        'startLine' => 150,
        'endLine' => 159,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'parsePathNoSchemeReference' => 
      array (
        'name' => 'parsePathNoSchemeReference',
        'parameters' => 
        array (
          'url' => 
          array (
            'name' => 'url',
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
            'startLine' => 164,
            'endLine' => 164,
            'startColumn' => 56,
            'endColumn' => 66,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @return array{path: string, query?: string, fragment?: string}
 */',
        'startLine' => 164,
        'endLine' => 181,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
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
        ),
        'docComment' => NULL,
        'startLine' => 183,
        'endLine' => 192,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'composeComponents' => 
      array (
        'name' => 'composeComponents',
        'parameters' => 
        array (
          'scheme' => 
          array (
            'name' => 'scheme',
            'default' => NULL,
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
            ),
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 46,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'authority' => 
          array (
            'name' => 'authority',
            'default' => NULL,
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
            ),
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 63,
            'endColumn' => 80,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
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
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 83,
            'endColumn' => 94,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
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
            ),
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 97,
            'endColumn' => 110,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'fragment' => 
          array (
            'name' => 'fragment',
            'default' => NULL,
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
            ),
            'startLine' => 212,
            'endLine' => 212,
            'startColumn' => 113,
            'endColumn' => 129,
            'parameterIndex' => 4,
            'isOptional' => false,
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
        ),
        'docComment' => '/**
 * Composes a URI reference string from its various components.
 *
 * Usually this method does not need to be called manually but instead is used indirectly via
 * `Psr\\Http\\Message\\UriInterface::__toString`.
 *
 * PSR-7 UriInterface treats an empty component the same as a missing component as
 * getQuery(), getFragment() etc. always return a string. This explains the slight
 * difference to RFC 3986 Section 5.3.
 *
 * Another adjustment is that the authority separator is added even when the authority is missing/empty
 * for the "file" scheme. This is because PHP stream functions like `file_get_contents` only work with
 * `file:///myfile` but not with `file:/myfile` although they are equivalent according to RFC 3986. But
 * `file:///` is the more common syntax for the file scheme anyway (Chrome for example redirects to
 * that format).
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.3
 */',
        'startLine' => 212,
        'endLine' => 240,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'isDefaultPort' => 
      array (
        'name' => 'isDefaultPort',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 248,
            'endLine' => 248,
            'startColumn' => 42,
            'endColumn' => 58,
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
        ),
        'docComment' => '/**
 * Whether the URI has the default port of the current scheme.
 *
 * `Psr\\Http\\Message\\UriInterface::getPort` may return null or the standard port. This method can be used
 * independently of the implementation.
 */',
        'startLine' => 248,
        'endLine' => 252,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'isAbsolute' => 
      array (
        'name' => 'isAbsolute',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 269,
            'endLine' => 269,
            'startColumn' => 39,
            'endColumn' => 55,
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
        ),
        'docComment' => '/**
 * Whether the URI is absolute, i.e. it has a scheme.
 *
 * An instance of UriInterface can either be an absolute URI or a relative reference. This method returns true
 * if it is the former. An absolute URI has a scheme. A relative reference is used to express a URI relative
 * to another URI, the base URI. Relative references can be divided into several forms:
 * - network-path references, e.g. \'//example.com/path\'
 * - absolute-path references, e.g. \'/path\'
 * - relative-path references, e.g. \'subpath\'
 *
 * @see Uri::isNetworkPathReference
 * @see Uri::isAbsolutePathReference
 * @see Uri::isRelativePathReference
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4
 */',
        'startLine' => 269,
        'endLine' => 272,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'isNetworkPathReference' => 
      array (
        'name' => 'isNetworkPathReference',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 281,
            'endLine' => 281,
            'startColumn' => 51,
            'endColumn' => 67,
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
        ),
        'docComment' => '/**
 * Whether the URI is a network-path reference.
 *
 * A relative reference that begins with two slash characters is termed an network-path reference.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
 */',
        'startLine' => 281,
        'endLine' => 284,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'isAbsolutePathReference' => 
      array (
        'name' => 'isAbsolutePathReference',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 52,
            'endColumn' => 68,
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
        ),
        'docComment' => '/**
 * Whether the URI is a absolute-path reference.
 *
 * A relative reference that begins with a single slash character is termed an absolute-path reference.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
 */',
        'startLine' => 293,
        'endLine' => 299,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'isRelativePathReference' => 
      array (
        'name' => 'isRelativePathReference',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 308,
            'endLine' => 308,
            'startColumn' => 52,
            'endColumn' => 68,
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
        ),
        'docComment' => '/**
 * Whether the URI is a relative-path reference.
 *
 * A relative reference that does not begin with a slash character is termed a relative-path reference.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
 */',
        'startLine' => 308,
        'endLine' => 313,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'isSameDocumentReference' => 
      array (
        'name' => 'isSameDocumentReference',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 52,
            'endColumn' => 68,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'base' => 
          array (
            'name' => 'base',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 327,
                'endLine' => 327,
                'startTokenPos' => 1523,
                'startFilePos' => 10678,
                'endTokenPos' => 1523,
                'endFilePos' => 10681,
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
                      'name' => 'Psr\\Http\\Message\\UriInterface',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 327,
            'endLine' => 327,
            'startColumn' => 71,
            'endColumn' => 96,
            'parameterIndex' => 1,
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
 * Whether the URI is a same-document reference.
 *
 * A same-document reference refers to a URI that is, aside from its fragment
 * component, identical to the base URI. When no base URI is given, only an empty
 * URI reference (apart from its fragment) is considered a same-document reference.
 *
 * @param UriInterface      $uri  The URI to check
 * @param UriInterface|null $base An optional base URI to compare against
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.4
 */',
        'startLine' => 327,
        'endLine' => 339,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withoutQueryValue' => 
      array (
        'name' => 'withoutQueryValue',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 350,
            'endLine' => 350,
            'startColumn' => 46,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 350,
            'endLine' => 350,
            'startColumn' => 65,
            'endColumn' => 75,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a new URI with a specific query string value removed.
 *
 * Any existing query string values that exactly match the provided key are
 * removed.
 *
 * @param UriInterface $uri URI to use as a base.
 * @param string       $key Query string key to remove.
 */',
        'startLine' => 350,
        'endLine' => 355,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withQueryValue' => 
      array (
        'name' => 'withQueryValue',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 370,
            'endLine' => 370,
            'startColumn' => 43,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 370,
            'endLine' => 370,
            'startColumn' => 62,
            'endColumn' => 72,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
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
            ),
            'startLine' => 370,
            'endLine' => 370,
            'startColumn' => 75,
            'endColumn' => 88,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a new URI with a specific query string value.
 *
 * Any existing query string values that exactly match the provided key are
 * removed and replaced with the given key value pair.
 *
 * A value of null will set the query string key without a value, e.g. "key"
 * instead of "key=value".
 *
 * @param UriInterface $uri   URI to use as a base.
 * @param string       $key   Key to set.
 * @param string|null  $value Value to set
 */',
        'startLine' => 370,
        'endLine' => 377,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withQueryValues' => 
      array (
        'name' => 'withQueryValues',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 44,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'keyValueArray' => 
          array (
            'name' => 'keyValueArray',
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
            'startLine' => 387,
            'endLine' => 387,
            'startColumn' => 63,
            'endColumn' => 82,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a new URI with multiple specific query string values.
 *
 * It has the same behavior as withQueryValue() but for an associative array of key => value.
 *
 * @param UriInterface    $uri           URI to use as a base.
 * @param (string|null)[] $keyValueArray Associative array of key and values
 */',
        'startLine' => 387,
        'endLine' => 396,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'stringifyQueryValue' => 
      array (
        'name' => 'stringifyQueryValue',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 406,
            'endLine' => 406,
            'startColumn' => 49,
            'endColumn' => 54,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Stringifies a non-null query value, deprecating non-string values that
 * guzzlehttp/psr7 3.0 will reject. Non-finite floats are normalized to the
 * strings PHP coerces them to, as implicit coercion of NAN emits a warning
 * on PHP 8.5.
 *
 * @param mixed $value
 */',
        'startLine' => 406,
        'endLine' => 422,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'fromParts' => 
      array (
        'name' => 'fromParts',
        'parameters' => 
        array (
          'parts' => 
          array (
            'name' => 'parts',
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
            'startLine' => 431,
            'endLine' => 431,
            'startColumn' => 38,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a URI from a hash of `parse_url` components.
 *
 * @see https://www.php.net/manual/en/function.parse-url.php
 *
 * @throws MalformedUriException If the components do not form a valid URI.
 */',
        'startLine' => 431,
        'endLine' => 444,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'assertValidHost' => 
      array (
        'name' => 'assertValidHost',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
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
            'startLine' => 451,
            'endLine' => 451,
            'startColumn' => 44,
            'endColumn' => 55,
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
 * @throws \\InvalidArgumentException If the host is invalid.
 *
 * @internal
 */',
        'startLine' => 451,
        'endLine' => 480,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getScheme' => 
      array (
        'name' => 'getScheme',
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
        'docComment' => NULL,
        'startLine' => 482,
        'endLine' => 485,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getAuthority' => 
      array (
        'name' => 'getAuthority',
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
        'docComment' => NULL,
        'startLine' => 487,
        'endLine' => 499,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getUserInfo' => 
      array (
        'name' => 'getUserInfo',
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
        'docComment' => NULL,
        'startLine' => 501,
        'endLine' => 504,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getHost' => 
      array (
        'name' => 'getHost',
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
        'docComment' => NULL,
        'startLine' => 506,
        'endLine' => 509,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getPort' => 
      array (
        'name' => 'getPort',
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
                  'name' => 'null',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 511,
        'endLine' => 514,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
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
        ),
        'docComment' => NULL,
        'startLine' => 516,
        'endLine' => 519,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getQuery' => 
      array (
        'name' => 'getQuery',
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
        'docComment' => NULL,
        'startLine' => 521,
        'endLine' => 524,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getFragment' => 
      array (
        'name' => 'getFragment',
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
        'docComment' => NULL,
        'startLine' => 526,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withScheme' => 
      array (
        'name' => 'withScheme',
        'parameters' => 
        array (
          'scheme' => 
          array (
            'name' => 'scheme',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 531,
            'endLine' => 531,
            'startColumn' => 32,
            'endColumn' => 38,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 531,
        'endLine' => 545,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withUserInfo' => 
      array (
        'name' => 'withUserInfo',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 547,
            'endLine' => 547,
            'startColumn' => 34,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 547,
                'endLine' => 547,
                'startTokenPos' => 2736,
                'startFilePos' => 17264,
                'endTokenPos' => 2736,
                'endFilePos' => 17267,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 547,
            'endLine' => 547,
            'startColumn' => 41,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 547,
        'endLine' => 563,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withHost' => 
      array (
        'name' => 'withHost',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 565,
            'endLine' => 565,
            'startColumn' => 30,
            'endColumn' => 34,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 565,
        'endLine' => 578,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withPort' => 
      array (
        'name' => 'withPort',
        'parameters' => 
        array (
          'port' => 
          array (
            'name' => 'port',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 580,
            'endLine' => 580,
            'startColumn' => 30,
            'endColumn' => 34,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 580,
        'endLine' => 603,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withPath' => 
      array (
        'name' => 'withPath',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 605,
            'endLine' => 605,
            'startColumn' => 30,
            'endColumn' => 34,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 605,
        'endLine' => 618,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withQuery' => 
      array (
        'name' => 'withQuery',
        'parameters' => 
        array (
          'query' => 
          array (
            'name' => 'query',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 620,
            'endLine' => 620,
            'startColumn' => 31,
            'endColumn' => 36,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 620,
        'endLine' => 632,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'withFragment' => 
      array (
        'name' => 'withFragment',
        'parameters' => 
        array (
          'fragment' => 
          array (
            'name' => 'fragment',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 634,
            'endLine' => 634,
            'startColumn' => 34,
            'endColumn' => 42,
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
            'name' => 'Psr\\Http\\Message\\UriInterface',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 634,
        'endLine' => 646,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'jsonSerialize' => 
      array (
        'name' => 'jsonSerialize',
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
        'docComment' => NULL,
        'startLine' => 648,
        'endLine' => 651,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'applyParts' => 
      array (
        'name' => 'applyParts',
        'parameters' => 
        array (
          'parts' => 
          array (
            'name' => 'parts',
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
            'startLine' => 658,
            'endLine' => 658,
            'startColumn' => 33,
            'endColumn' => 44,
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
 * Apply parse_url parts to a URI.
 *
 * @param array $parts Array of parse_url parts to apply.
 */',
        'startLine' => 658,
        'endLine' => 686,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'filterScheme' => 
      array (
        'name' => 'filterScheme',
        'parameters' => 
        array (
          'scheme' => 
          array (
            'name' => 'scheme',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 693,
            'endLine' => 693,
            'startColumn' => 35,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param mixed $scheme
 *
 * @throws \\InvalidArgumentException If the scheme is invalid.
 */',
        'startLine' => 693,
        'endLine' => 711,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'filterUserInfoComponent' => 
      array (
        'name' => 'filterUserInfoComponent',
        'parameters' => 
        array (
          'component' => 
          array (
            'name' => 'component',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 718,
            'endLine' => 718,
            'startColumn' => 46,
            'endColumn' => 55,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param mixed $component
 *
 * @throws \\InvalidArgumentException If the user info is invalid.
 */',
        'startLine' => 718,
        'endLine' => 729,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'filterHost' => 
      array (
        'name' => 'filterHost',
        'parameters' => 
        array (
          'host' => 
          array (
            'name' => 'host',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 736,
            'endLine' => 736,
            'startColumn' => 33,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param mixed $host
 *
 * @throws \\InvalidArgumentException If the host is invalid.
 */',
        'startLine' => 736,
        'endLine' => 746,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'filterPort' => 
      array (
        'name' => 'filterPort',
        'parameters' => 
        array (
          'port' => 
          array (
            'name' => 'port',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 753,
            'endLine' => 753,
            'startColumn' => 33,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
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
                  'name' => 'int',
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param mixed $port
 *
 * @throws \\InvalidArgumentException If the port is invalid.
 */',
        'startLine' => 753,
        'endLine' => 767,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'getFilteredQueryString' => 
      array (
        'name' => 'getFilteredQueryString',
        'parameters' => 
        array (
          'uri' => 
          array (
            'name' => 'uri',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Psr\\Http\\Message\\UriInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 774,
            'endLine' => 774,
            'startColumn' => 52,
            'endColumn' => 68,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'keys' => 
          array (
            'name' => 'keys',
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
            'startLine' => 774,
            'endLine' => 774,
            'startColumn' => 71,
            'endColumn' => 81,
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
        'docComment' => '/**
 * @param (string|int)[] $keys
 *
 * @return string[]
 */',
        'startLine' => 774,
        'endLine' => 789,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'generateQueryString' => 
      array (
        'name' => 'generateQueryString',
        'parameters' => 
        array (
          'key' => 
          array (
            'name' => 'key',
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
            'startLine' => 791,
            'endLine' => 791,
            'startColumn' => 49,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
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
            ),
            'startLine' => 791,
            'endLine' => 791,
            'startColumn' => 62,
            'endColumn' => 75,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 791,
        'endLine' => 804,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'removeDefaultPort' => 
      array (
        'name' => 'removeDefaultPort',
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
        'docComment' => NULL,
        'startLine' => 806,
        'endLine' => 811,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'filterPath' => 
      array (
        'name' => 'filterPath',
        'parameters' => 
        array (
          'path' => 
          array (
            'name' => 'path',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 820,
            'endLine' => 820,
            'startColumn' => 33,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Filters the path of a URI
 *
 * @param mixed $path
 *
 * @throws \\InvalidArgumentException If the path is invalid.
 */',
        'startLine' => 820,
        'endLine' => 831,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'filterQueryAndFragment' => 
      array (
        'name' => 'filterQueryAndFragment',
        'parameters' => 
        array (
          'str' => 
          array (
            'name' => 'str',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 840,
            'endLine' => 840,
            'startColumn' => 45,
            'endColumn' => 48,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Filters the query string or fragment of a URI.
 *
 * @param mixed $str
 *
 * @throws \\InvalidArgumentException If the query or fragment is invalid.
 */',
        'startLine' => 840,
        'endLine' => 851,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'filterComponent' => 
      array (
        'name' => 'filterComponent',
        'parameters' => 
        array (
          'pattern' => 
          array (
            'name' => 'pattern',
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
            'startLine' => 853,
            'endLine' => 853,
            'startColumn' => 38,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'component' => 
          array (
            'name' => 'component',
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
            'startLine' => 853,
            'endLine' => 853,
            'startColumn' => 55,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'context' => 
          array (
            'name' => 'context',
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
            'startLine' => 853,
            'endLine' => 853,
            'startColumn' => 74,
            'endColumn' => 88,
            'parameterIndex' => 2,
            'isOptional' => false,
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
        ),
        'docComment' => NULL,
        'startLine' => 853,
        'endLine' => 862,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'rawurlencodeMatchZero' => 
      array (
        'name' => 'rawurlencodeMatchZero',
        'parameters' => 
        array (
          'match' => 
          array (
            'name' => 'match',
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
            'startLine' => 864,
            'endLine' => 864,
            'startColumn' => 44,
            'endColumn' => 55,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 864,
        'endLine' => 867,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'aliasName' => NULL,
      ),
      'validateState' => 
      array (
        'name' => 'validateState',
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
        'docComment' => NULL,
        'startLine' => 869,
        'endLine' => 883,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\Uri',
        'currentClassName' => 'GuzzleHttp\\Psr7\\Uri',
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