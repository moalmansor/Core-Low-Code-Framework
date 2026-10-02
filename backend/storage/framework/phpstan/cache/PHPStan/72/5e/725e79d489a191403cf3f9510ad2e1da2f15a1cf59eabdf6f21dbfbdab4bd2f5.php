<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionFunction-json_decode
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'name' => 'json_decode',
    'parameters' => 
    array (
      'json' => 
      array (
        'name' => 'json',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Language',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '"JSON"',
                'attributes' => 
                array (
                  'startLine' => 38,
                  'endLine' => 38,
                  'startTokenPos' => 16,
                  'startFilePos' => 2493,
                  'endTokenPos' => 16,
                  'endFilePos' => 2498,
                ),
              ),
            ),
          ),
        ),
        'startLine' => 38,
        'endLine' => 39,
        'startColumn' => 9,
        'endColumn' => 20,
        'parameterIndex' => 0,
        'isOptional' => false,
      ),
      'associative' => 
      array (
        'name' => 'associative',
        'default' => 
        array (
          'code' => '\\null',
          'attributes' => 
          array (
            'startLine' => 40,
            'endLine' => 40,
            'startTokenPos' => 32,
            'startFilePos' => 2553,
            'endTokenPos' => 32,
            'endFilePos' => 2556,
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
                  'name' => 'bool',
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
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 9,
        'endColumn' => 33,
        'parameterIndex' => 1,
        'isOptional' => true,
      ),
      'depth' => 
      array (
        'name' => 'depth',
        'default' => 
        array (
          'code' => '512',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 41,
            'startFilePos' => 2580,
            'endTokenPos' => 41,
            'endFilePos' => 2582,
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
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 9,
        'endColumn' => 24,
        'parameterIndex' => 2,
        'isOptional' => true,
      ),
      'flags' => 
      array (
        'name' => 'flags',
        'default' => 
        array (
          'code' => '0',
          'attributes' => 
          array (
            'startLine' => 42,
            'endLine' => 42,
            'startTokenPos' => 50,
            'startFilePos' => 2606,
            'endTokenPos' => 50,
            'endFilePos' => 2606,
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
        'startLine' => 42,
        'endLine' => 42,
        'startColumn' => 9,
        'endColumn' => 22,
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
        'name' => 'mixed',
        'isIdentifier' => true,
      ),
    ),
    'attributes' => 
    array (
    ),
    'docComment' => '/**
 * (PHP 5 &gt;= 5.2.0, PECL json &gt;= 1.2.0)<br/>
 * Takes a JSON encoded string and converts it into a PHP value.
 * @link https://www.php.net/manual/en/function.json-decode.php
 * @param string $json <p>
 * The <i>json</i> string being decoded.
 * </p>
 * <p>
 * This function only works with UTF-8 encoded strings.
 * </p>
 * <p>PHP implements a superset of JSON as specified by <a href="https://datatracker.ietf.org/doc/html/rfc7159">RFC 7159</a>.
 * </p>
 * @param bool|null $associative <p>
 * When <b>TRUE</b>, JSON objects will be returned as associative arrays; when <b>FALSE</b>, JSON objects will be returned as objects.
 * When <b>NULL</b>, JSON objects will be returned as associative arrays or objects depending on whether JSON_OBJECT_AS_ARRAY is set in the flags.
 * </p>
 * @param int $depth [optional] <p>
 * Maximum nesting depth of the structure being decoded. The value must be greater than 0, and less than or equal to 2147483647.
 * </p>
 * @param int $flags [optional] <p>
 * Bitmask of JSON decode options:<br/>
 * {@see JSON_BIGINT_AS_STRING} decodes large integers as their original string value.<br/>
 * {@see JSON_INVALID_UTF8_IGNORE} ignores invalid UTF-8 characters,
 * {@see JSON_INVALID_UTF8_SUBSTITUTE} converts invalid UTF-8 characters to \\0xfffd, Available as of PHP 7.2.0.
 * {@see JSON_OBJECT_AS_ARRAY} Decodes JSON objects as PHP array. This option can be added automatically by calling json_decode() with the second parameter equal to true.
 * {@see JSON_THROW_ON_ERROR} Throws JsonException if an error occurs instead of setting the global error state that is retrieved with json_last_error() and json_last_error_msg(). JSON_PARTIAL_OUTPUT_ON_ERROR takes precedence over JSON_THROW_ON_ERROR. Available as of PHP 7.3.0.<br/>
 * </p>
 * @return mixed Returns the value encoded in <i>json</i> as an appropriate PHP type. Unquoted values true, <b>FALSE</b>
 * and <b>NULL</b> are returned as <b>TRUE</b>, <b>FALSE</b> and <b>NULL</b> respectively. <b>NULL</b> is returned
 * if the <i>json</i> cannot be decoded or if the encoded data is deeper than the nesting limit.
 * @throws \\ValueError If depth is outside the allowed range, a ValueError is thrown as of PHP
 * 8.0.0, while previously, an error of level E_WARNING was raised.
 */',
    'startLine' => 37,
    'endLine' => 45,
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
        'name' => 'json_decode',
        'filename' => 'phpstorm-stubs:json/json.stub',
        'extensionName' => 'json',
        'aliasName' => NULL,
      ),
    ),
  ),
));