<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-reflectionattribute
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'ReflectionAttribute',
        'filename' => 'phpstorm-stubs:Reflection/ReflectionAttribute.stub',
        'extensionName' => 'Reflection',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'ReflectionAttribute',
    'shortName' => 'ReflectionAttribute',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * The ReflectionAttribute class provides information about an Attribute.
 * @link https://php.net/manual/en/class.reflectionattribute.php
 * @since 8.0
 *
 * @template T of object
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 11,
    'endLine' => 97,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Reflector',
    ),
    'traitClassNames' => 
    array (
    ),
    'immediateConstants' => 
    array (
      'IS_INSTANCEOF' => 
      array (
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'name' => 'IS_INSTANCEOF',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 22,
            'endLine' => 22,
            'startTokenPos' => 35,
            'startFilePos' => 639,
            'endTokenPos' => 35,
            'endFilePos' => 639,
          ),
        ),
        'docComment' => '/**
 * Indicates that the search for a suitable attribute should not be by
 * strict comparison, but by the inheritance chain.
 *
 * Used for the argument of flags of the "getAttribute" method.
 *
 * @since 8.0
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 22,
        'endLine' => 22,
        'startColumn' => 9,
        'endColumn' => 39,
      ),
    ),
    'immediateProperties' => 
    array (
      'name' => 
      array (
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'name' => 'name',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'default' => NULL,
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 13,
        'endLine' => 13,
        'startColumn' => 9,
        'endColumn' => 28,
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
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * ReflectionAttribute cannot be created explicitly.
 * @link https://php.net/manual/en/reflectionattribute.construct.php
 * @since 8.0
 */',
        'startLine' => 28,
        'endLine' => 30,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
        'aliasName' => NULL,
      ),
      'getName' => 
      array (
        'name' => 'getName',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Pure',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets attribute name
 *
 * @link https://php.net/manual/en/reflectionattribute.getname.php
 * @return string The name of the attribute parameter.
 * @since 8.0
 */',
        'startLine' => 38,
        'endLine' => 41,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
        'aliasName' => NULL,
      ),
      'getTarget' => 
      array (
        'name' => 'getTarget',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Pure',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Returns the target of the attribute as a bit mask format.
 *
 * @link https://php.net/manual/en/reflectionattribute.gettarget.php
 * @return int Gets target of the attribute as bitmask of Attribute::TARGET_* constants.
 * @since 8.0
 */',
        'startLine' => 49,
        'endLine' => 52,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
        'aliasName' => NULL,
      ),
      'isRepeated' => 
      array (
        'name' => 'isRepeated',
        'parameters' => 
        array (
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
            'name' => 'JetBrains\\PhpStorm\\Pure',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Returns {@see true} if the attribute is repeated.
 *
 * @link https://php.net/manual/en/reflectionattribute.isrepeated.php
 * @return bool Returns true when attribute is used repeatedly, otherwise false.
 * @since 8.0
 */',
        'startLine' => 60,
        'endLine' => 63,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
        'aliasName' => NULL,
      ),
      'getArguments' => 
      array (
        'name' => 'getArguments',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Pure',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Gets list of passed attribute\'s arguments.
 *
 * @link https://php.net/manual/en/reflectionattribute.getarguments.php
 * @return array The arguments passed to attribute.
 * @since 8.0
 */',
        'startLine' => 71,
        'endLine' => 74,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
        'aliasName' => NULL,
      ),
      'newInstance' => 
      array (
        'name' => 'newInstance',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'object',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Creates a new instance of the attribute with passed arguments
 *
 * @link https://php.net/manual/en/reflectionattribute.newinstance.php
 * @return T New instance of the attribute.
 * @since 8.0
 */',
        'startLine' => 82,
        'endLine' => 84,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
        'aliasName' => NULL,
      ),
      '__clone' => 
      array (
        'name' => '__clone',
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
        'docComment' => '/**
 * ReflectionAttribute cannot be cloned
 *
 * @return void
 * @since 8.0
 */',
        'startLine' => 91,
        'endLine' => 93,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
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
        'startLine' => 94,
        'endLine' => 96,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'ReflectionAttribute',
        'implementingClassName' => 'ReflectionAttribute',
        'currentClassName' => 'ReflectionAttribute',
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