<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Monitoring/Masker.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Monitoring\Masker
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-b62551a12ab8dff04fee42f3b6a0d999ddb029bcf12899490e046231fa8e456f',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Monitoring\\Masker',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Monitoring/Masker.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Monitoring',
    'name' => 'App\\Modules\\Monitoring\\Masker',
    'shortName' => 'Masker',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Removes sensitive data before anything is logged (specification §5:
 * "no sensitive data in logs or in user-facing errors").
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 76,
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
      'SENSITIVE_KEYS' => 
      array (
        'declaringClassName' => 'App\\Modules\\Monitoring\\Masker',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Masker',
        'name' => 'SENSITIVE_KEYS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'/pass(word)?|secret|token|api[_-]?key|authorization|cookie|session|credential|two_factor|recovery|otp|code$|private/i\'',
          'attributes' => 
          array (
            'startLine' => 13,
            'endLine' => 13,
            'startTokenPos' => 33,
            'startFilePos' => 262,
            'endTokenPos' => 33,
            'endFilePos' => 380,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 5,
        'endColumn' => 155,
      ),
      'SENSITIVE_HEADERS' => 
      array (
        'declaringClassName' => 'App\\Modules\\Monitoring\\Masker',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Masker',
        'name' => 'SENSITIVE_HEADERS',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'authorization\', \'cookie\', \'set-cookie\', \'x-xsrf-token\', \'x-csrf-token\', \'proxy-authorization\']',
          'attributes' => 
          array (
            'startLine' => 15,
            'endLine' => 15,
            'startTokenPos' => 44,
            'startFilePos' => 422,
            'endTokenPos' => 61,
            'endFilePos' => 517,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 15,
        'endLine' => 15,
        'startColumn' => 5,
        'endColumn' => 135,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'payload' => 
      array (
        'name' => 'payload',
        'parameters' => 
        array (
          'data' => 
          array (
            'name' => 'data',
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
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 29,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'depth' => 
          array (
            'name' => 'depth',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 21,
                'endLine' => 21,
                'startTokenPos' => 83,
                'startFilePos' => 654,
                'endTokenPos' => 83,
                'endFilePos' => 654,
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
            'startLine' => 21,
            'endLine' => 21,
            'startColumn' => 42,
            'endColumn' => 55,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<mixed>  $data
 * @return array<mixed>
 */',
        'startLine' => 21,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Monitoring',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Masker',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Masker',
        'currentClassName' => 'App\\Modules\\Monitoring\\Masker',
        'aliasName' => NULL,
      ),
      'headers' => 
      array (
        'name' => 'headers',
        'parameters' => 
        array (
          'headers' => 
          array (
            'name' => 'headers',
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
            'startLine' => 46,
            'endLine' => 46,
            'startColumn' => 29,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * @param  array<string, list<string|null>>  $headers
 * @return array<string, string>
 */',
        'startLine' => 46,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Monitoring',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Masker',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Masker',
        'currentClassName' => 'App\\Modules\\Monitoring\\Masker',
        'aliasName' => NULL,
      ),
      'url' => 
      array (
        'name' => 'url',
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
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 25,
            'endColumn' => 35,
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
        'docComment' => '/** Masks query-string values, keeping keys. */',
        'startLine' => 57,
        'endLine' => 67,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Monitoring',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Masker',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Masker',
        'currentClassName' => 'App\\Modules\\Monitoring\\Masker',
        'aliasName' => NULL,
      ),
      'text' => 
      array (
        'name' => 'text',
        'parameters' => 
        array (
          'text' => 
          array (
            'name' => 'text',
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
            'startLine' => 70,
            'endLine' => 70,
            'startColumn' => 26,
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
        'docComment' => '/** Masks e-mail addresses and long digit runs in free text (messages, traces). */',
        'startLine' => 70,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Monitoring',
        'declaringClassName' => 'App\\Modules\\Monitoring\\Masker',
        'implementingClassName' => 'App\\Modules\\Monitoring\\Masker',
        'currentClassName' => 'App\\Modules\\Monitoring\\Masker',
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