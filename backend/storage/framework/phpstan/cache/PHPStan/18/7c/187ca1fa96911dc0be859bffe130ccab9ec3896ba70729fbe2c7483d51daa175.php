<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Audit/ChainVerifier.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Audit\ChainVerifier
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-fb5969d025e12a909a03ae619102aff89b54c7b9f58c9af90bd75ccfde5a3582',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Audit\\ChainVerifier',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Audit/ChainVerifier.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Audit',
    'name' => 'App\\Modules\\Audit\\ChainVerifier',
    'shortName' => 'ChainVerifier',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Walks each hash chain and reports breaks (specification §4.20): a missing or
 * reordered sequence number, a prev_hash that does not match the previous
 * entry, or a hash that does not match the entry\'s content.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 15,
    'endLine' => 84,
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
      'verify' => 
      array (
        'name' => 'verify',
        'parameters' => 
        array (
          'full' => 
          array (
            'name' => 'full',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 20,
                'endLine' => 20,
                'startTokenPos' => 49,
                'startFilePos' => 552,
                'endTokenPos' => 49,
                'endFilePos' => 556,
              ),
            ),
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
            'startLine' => 20,
            'endLine' => 20,
            'startColumn' => 28,
            'endColumn' => 45,
            'parameterIndex' => 0,
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
 * @return array{verified: int, breaks: list<array{chain_id: int, chain_seq: int, id: int, reason: string}>}
 */',
        'startLine' => 20,
        'endLine' => 77,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Audit',
        'declaringClassName' => 'App\\Modules\\Audit\\ChainVerifier',
        'implementingClassName' => 'App\\Modules\\Audit\\ChainVerifier',
        'currentClassName' => 'App\\Modules\\Audit\\ChainVerifier',
        'aliasName' => NULL,
      ),
      'normalizeTime' => 
      array (
        'name' => 'normalizeTime',
        'parameters' => 
        array (
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 80,
            'endLine' => 80,
            'startColumn' => 42,
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
        'docComment' => '/** Both engines return DATETIME(6) values; normalise to the writer\'s format. */',
        'startLine' => 80,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'App\\Modules\\Audit',
        'declaringClassName' => 'App\\Modules\\Audit\\ChainVerifier',
        'implementingClassName' => 'App\\Modules\\Audit\\ChainVerifier',
        'currentClassName' => 'App\\Modules\\Audit\\ChainVerifier',
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