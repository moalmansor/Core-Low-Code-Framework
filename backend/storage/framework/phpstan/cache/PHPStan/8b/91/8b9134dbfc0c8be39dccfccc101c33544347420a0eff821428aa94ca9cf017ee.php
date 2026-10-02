<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../guzzlehttp/psr7/src/UriResolver.php-PHPStan\BetterReflection\Reflection\ReflectionClass-GuzzleHttp\Psr7\UriResolver
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-b6f6ca6aee55515fe2210299ad3c1ccb7ea6c4aa4107ada8de98148e7787b8c8-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'GuzzleHttp\\Psr7\\UriResolver',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../guzzlehttp/psr7/src/UriResolver.php',
      ),
    ),
    'namespace' => 'GuzzleHttp\\Psr7',
    'name' => 'GuzzleHttp\\Psr7\\UriResolver',
    'shortName' => 'UriResolver',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Resolves a URI reference in the context of a base URI and the opposite way.
 *
 * @author Tobias Schultze
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 237,
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
      'removeDotSegments' => 
      array (
        'name' => 'removeDotSegments',
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
            'startLine' => 23,
            'endLine' => 23,
            'startColumn' => 46,
            'endColumn' => 57,
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
 * Removes dot segments from a path and returns the new path.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2.4
 */',
        'startLine' => 23,
        'endLine' => 51,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'currentClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'aliasName' => NULL,
      ),
      'guardedPath' => 
      array (
        'name' => 'guardedPath',
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
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 40,
            'endColumn' => 56,
            'parameterIndex' => 0,
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
            'startLine' => 65,
            'endLine' => 65,
            'startColumn' => 59,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns the path, prefixed with "./" when it would otherwise begin a relative-path reference with a segment
 * containing a colon.
 *
 * Such a segment would be mistaken for a scheme name (RFC 3986 Section 4.2), so a URI without a scheme and
 * authority cannot hold the path, but reference resolution and percent-encoding normalization can produce one.
 * The "./" prefix the RFC prescribes resolves back to the same path.
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
 *
 * @internal
 */',
        'startLine' => 65,
        'endLine' => 72,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'currentClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'aliasName' => NULL,
      ),
      'resolve' => 
      array (
        'name' => 'resolve',
        'parameters' => 
        array (
          'base' => 
          array (
            'name' => 'base',
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
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 36,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'rel' => 
          array (
            'name' => 'rel',
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
            'startLine' => 84,
            'endLine' => 84,
            'startColumn' => 56,
            'endColumn' => 72,
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
 * Converts the relative URI into a new URI that is resolved against the base URI.
 *
 * When the resolved path is a relative-path reference whose first segment contains a colon,
 * which would be mistaken for a scheme name (RFC 3986 Section 4.2), it is prefixed with "./",
 * e.g. "./a:b".
 *
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-5.2
 * @see https://datatracker.ietf.org/doc/html/rfc3986#section-4.2
 */',
        'startLine' => 84,
        'endLine' => 131,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'currentClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'aliasName' => NULL,
      ),
      'relativize' => 
      array (
        'name' => 'relativize',
        'parameters' => 
        array (
          'base' => 
          array (
            'name' => 'base',
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
            'startLine' => 154,
            'endLine' => 154,
            'startColumn' => 39,
            'endColumn' => 56,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'target' => 
          array (
            'name' => 'target',
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
            'startLine' => 154,
            'endLine' => 154,
            'startColumn' => 59,
            'endColumn' => 78,
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
 * Returns the target URI as a relative reference from the base URI.
 *
 * This method is the counterpart to resolve():
 *
 *    (string) $target === (string) UriResolver::resolve($base, UriResolver::relativize($base, $target))
 *
 * One use-case is to use the current request URI as base URI and then generate relative links in your documents
 * to reduce the document size or offer self-contained downloadable document archives.
 *
 *    $base = new Uri(\'http://example.com/a/b/\');
 *    echo UriResolver::relativize($base, new Uri(\'http://example.com/a/b/c\'));  // prints \'c\'.
 *    echo UriResolver::relativize($base, new Uri(\'http://example.com/a/x/y\'));  // prints \'../x/y\'.
 *    echo UriResolver::relativize($base, new Uri(\'http://example.com/a/b/?q\')); // prints \'?q\'.
 *    echo UriResolver::relativize($base, new Uri(\'http://example.org/a/b/\'));   // prints \'//example.org/a/b/\'.
 *
 * This method also accepts a target that is already relative and will try to relativize it further. Only a
 * relative-path reference will be returned as-is.
 *
 *    echo UriResolver::relativize($base, new Uri(\'/a/b/c\'));  // prints \'c\' as well
 */',
        'startLine' => 154,
        'endLine' => 198,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'currentClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'aliasName' => NULL,
      ),
      'getRelativePath' => 
      array (
        'name' => 'getRelativePath',
        'parameters' => 
        array (
          'base' => 
          array (
            'name' => 'base',
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
            'startLine' => 200,
            'endLine' => 200,
            'startColumn' => 45,
            'endColumn' => 62,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'target' => 
          array (
            'name' => 'target',
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
            'startLine' => 200,
            'endLine' => 200,
            'startColumn' => 65,
            'endColumn' => 84,
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
        'startLine' => 200,
        'endLine' => 231,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 20,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'currentClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'aliasName' => NULL,
      ),
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 233,
        'endLine' => 236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'GuzzleHttp\\Psr7',
        'declaringClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'implementingClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
        'currentClassName' => 'GuzzleHttp\\Psr7\\UriResolver',
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