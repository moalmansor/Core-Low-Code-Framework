<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Database/Schema/Grammars/SqlServerGrammar.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Schema\Grammars\SqlServerGrammar
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-efdb95395810db1bb0d61d7ae3073246dc8945712366d52b74dac3fb9c82eb24-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Database/Schema/Grammars/SqlServerGrammar.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
    'name' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
    'shortName' => 'SqlServerGrammar',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 9,
    'endLine' => 1045,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\Grammar',
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
      'transactions' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'name' => 'transactions',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => 'true',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 16,
            'startTokenPos' => 40,
            'startFilePos' => 355,
            'endTokenPos' => 40,
            'endFilePos' => 358,
          ),
        ),
        'docComment' => '/**
 * If this Grammar supports schema changes wrapped in a transaction.
 *
 * @var bool
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 16,
        'startColumn' => 5,
        'endColumn' => 35,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'modifiers' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'name' => 'modifiers',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'Collate\', \'Nullable\', \'Default\', \'Persisted\', \'Increment\']',
          'attributes' => 
          array (
            'startLine' => 23,
            'endLine' => 23,
            'startTokenPos' => 51,
            'startFilePos' => 471,
            'endTokenPos' => 65,
            'endFilePos' => 530,
          ),
        ),
        'docComment' => '/**
 * The possible column modifiers.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 88,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'serials' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'name' => 'serials',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'tinyInteger\', \'smallInteger\', \'mediumInteger\', \'integer\', \'bigInteger\']',
          'attributes' => 
          array (
            'startLine' => 30,
            'endLine' => 30,
            'startTokenPos' => 76,
            'startFilePos' => 644,
            'endTokenPos' => 90,
            'endFilePos' => 716,
          ),
        ),
        'docComment' => '/**
 * The columns available as serials.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 30,
        'endLine' => 30,
        'startColumn' => 5,
        'endColumn' => 99,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fluentCommands' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'name' => 'fluentCommands',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'Default\']',
          'attributes' => 
          array (
            'startLine' => 37,
            'endLine' => 37,
            'startTokenPos' => 101,
            'startFilePos' => 867,
            'endTokenPos' => 103,
            'endFilePos' => 877,
          ),
        ),
        'docComment' => '/**
 * The commands to be executed outside of create or alter command.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 37,
        'endLine' => 37,
        'startColumn' => 5,
        'endColumn' => 44,
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
      'compileSchemas' => 
      array (
        'name' => 'compileSchemas',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the query to determine the schemas.
 *
 * @return string
 */',
        'startLine' => 44,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileTableExists' => 
      array (
        'name' => 'compileTableExists',
        'parameters' => 
        array (
          'schema' => 
          array (
            'name' => 'schema',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 57,
            'endLine' => 57,
            'startColumn' => 49,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the query to determine if the given table exists.
 *
 * @param  string|null  $schema
 * @param  string  $table
 * @return string
 */',
        'startLine' => 57,
        'endLine' => 63,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileTables' => 
      array (
        'name' => 'compileTables',
        'parameters' => 
        array (
          'schema' => 
          array (
            'name' => 'schema',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 71,
            'endLine' => 71,
            'startColumn' => 35,
            'endColumn' => 41,
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
 * Compile the query to determine the tables.
 *
 * @param  string|string[]|null  $schema
 * @return string
 */',
        'startLine' => 71,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileViews' => 
      array (
        'name' => 'compileViews',
        'parameters' => 
        array (
          'schema' => 
          array (
            'name' => 'schema',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 89,
            'endLine' => 89,
            'startColumn' => 34,
            'endColumn' => 40,
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
 * Compile the query to determine the views.
 *
 * @param  string|string[]|null  $schema
 * @return string
 */',
        'startLine' => 89,
        'endLine' => 96,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileSchemaWhereClause' => 
      array (
        'name' => 'compileSchemaWhereClause',
        'parameters' => 
        array (
          'schema' => 
          array (
            'name' => 'schema',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 49,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 105,
            'endLine' => 105,
            'startColumn' => 58,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the query to compare the schema.
 *
 * @param  string|string[]|null  $schema
 * @param  string  $column
 * @return string
 */',
        'startLine' => 105,
        'endLine' => 112,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileColumns' => 
      array (
        'name' => 'compileColumns',
        'parameters' => 
        array (
          'schema' => 
          array (
            'name' => 'schema',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 121,
            'endLine' => 121,
            'startColumn' => 36,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 121,
            'endLine' => 121,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the query to determine the columns.
 *
 * @param  string|null  $schema
 * @param  string  $table
 * @return string
 */',
        'startLine' => 121,
        'endLine' => 142,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileIndexes' => 
      array (
        'name' => 'compileIndexes',
        'parameters' => 
        array (
          'schema' => 
          array (
            'name' => 'schema',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 36,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 151,
            'endLine' => 151,
            'startColumn' => 45,
            'endColumn' => 50,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the query to determine the indexes.
 *
 * @param  string|null  $schema
 * @param  string  $table
 * @return string
 */',
        'startLine' => 151,
        'endLine' => 166,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileForeignKeys' => 
      array (
        'name' => 'compileForeignKeys',
        'parameters' => 
        array (
          'schema' => 
          array (
            'name' => 'schema',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 175,
            'endLine' => 175,
            'startColumn' => 40,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'table' => 
          array (
            'name' => 'table',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 175,
            'endLine' => 175,
            'startColumn' => 49,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the query to determine the foreign keys.
 *
 * @param  string|null  $schema
 * @param  string  $table
 * @return string
 */',
        'startLine' => 175,
        'endLine' => 197,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileCreate' => 
      array (
        'name' => 'compileCreate',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 35,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 206,
            'endLine' => 206,
            'startColumn' => 57,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a create table command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 206,
        'endLine' => 212,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileAdd' => 
      array (
        'name' => 'compileAdd',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 221,
            'endLine' => 221,
            'startColumn' => 32,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 221,
            'endLine' => 221,
            'startColumn' => 54,
            'endColumn' => 68,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a column addition table command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 221,
        'endLine' => 227,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileRenameColumn' => 
      array (
        'name' => 'compileRenameColumn',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 230,
            'endLine' => 230,
            'startColumn' => 41,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 230,
            'endLine' => 230,
            'startColumn' => 63,
            'endColumn' => 77,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/** @inheritDoc */',
        'startLine' => 230,
        'endLine' => 236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileChange' => 
      array (
        'name' => 'compileChange',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 239,
            'endLine' => 239,
            'startColumn' => 35,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 239,
            'endLine' => 239,
            'startColumn' => 57,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/** @inheritDoc */',
        'startLine' => 239,
        'endLine' => 248,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compilePrimary' => 
      array (
        'name' => 'compilePrimary',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 257,
            'endLine' => 257,
            'startColumn' => 36,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 257,
            'endLine' => 257,
            'startColumn' => 58,
            'endColumn' => 72,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a primary key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 257,
        'endLine' => 264,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileUnique' => 
      array (
        'name' => 'compileUnique',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 273,
            'endLine' => 273,
            'startColumn' => 35,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 273,
            'endLine' => 273,
            'startColumn' => 57,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a unique key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 273,
        'endLine' => 281,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileIndex' => 
      array (
        'name' => 'compileIndex',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 290,
            'endLine' => 290,
            'startColumn' => 34,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 290,
            'endLine' => 290,
            'startColumn' => 56,
            'endColumn' => 70,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a plain index key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 290,
        'endLine' => 298,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileSpatialIndex' => 
      array (
        'name' => 'compileSpatialIndex',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 307,
            'endLine' => 307,
            'startColumn' => 41,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 307,
            'endLine' => 307,
            'startColumn' => 63,
            'endColumn' => 77,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a spatial index key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 307,
        'endLine' => 314,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDefault' => 
      array (
        'name' => 'compileDefault',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 323,
            'endLine' => 323,
            'startColumn' => 36,
            'endColumn' => 55,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 323,
            'endLine' => 323,
            'startColumn' => 58,
            'endColumn' => 72,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a default command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string|null
 */',
        'startLine' => 323,
        'endLine' => 332,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDrop' => 
      array (
        'name' => 'compileDrop',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 341,
            'endLine' => 341,
            'startColumn' => 33,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 341,
            'endLine' => 341,
            'startColumn' => 55,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop table command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 341,
        'endLine' => 344,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropIfExists' => 
      array (
        'name' => 'compileDropIfExists',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 353,
            'endLine' => 353,
            'startColumn' => 41,
            'endColumn' => 60,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 353,
            'endLine' => 353,
            'startColumn' => 63,
            'endColumn' => 77,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop table (if exists) command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 353,
        'endLine' => 359,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropAllTables' => 
      array (
        'name' => 'compileDropAllTables',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the SQL needed to drop all tables.
 *
 * @return string
 */',
        'startLine' => 366,
        'endLine' => 369,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropColumn' => 
      array (
        'name' => 'compileDropColumn',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 378,
            'endLine' => 378,
            'startColumn' => 39,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 378,
            'endLine' => 378,
            'startColumn' => 61,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop column command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 378,
        'endLine' => 385,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropDefaultConstraint' => 
      array (
        'name' => 'compileDropDefaultConstraint',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 394,
            'endLine' => 394,
            'startColumn' => 50,
            'endColumn' => 69,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 394,
            'endLine' => 394,
            'startColumn' => 72,
            'endColumn' => 86,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop default constraint command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 394,
        'endLine' => 410,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropPrimary' => 
      array (
        'name' => 'compileDropPrimary',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 419,
            'endLine' => 419,
            'startColumn' => 40,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 419,
            'endLine' => 419,
            'startColumn' => 62,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop primary key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 419,
        'endLine' => 424,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropUnique' => 
      array (
        'name' => 'compileDropUnique',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 433,
            'endLine' => 433,
            'startColumn' => 39,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 433,
            'endLine' => 433,
            'startColumn' => 61,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop unique key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 433,
        'endLine' => 438,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropIndex' => 
      array (
        'name' => 'compileDropIndex',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 447,
            'endLine' => 447,
            'startColumn' => 38,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 447,
            'endLine' => 447,
            'startColumn' => 60,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop index command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 447,
        'endLine' => 452,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropSpatialIndex' => 
      array (
        'name' => 'compileDropSpatialIndex',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 45,
            'endColumn' => 64,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 461,
            'endLine' => 461,
            'startColumn' => 67,
            'endColumn' => 81,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop spatial index command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 461,
        'endLine' => 464,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropForeign' => 
      array (
        'name' => 'compileDropForeign',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 473,
            'endLine' => 473,
            'startColumn' => 40,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 473,
            'endLine' => 473,
            'startColumn' => 62,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a drop foreign key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 473,
        'endLine' => 478,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileRename' => 
      array (
        'name' => 'compileRename',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 35,
            'endColumn' => 54,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 57,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a rename table command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 487,
        'endLine' => 493,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileRenameIndex' => 
      array (
        'name' => 'compileRenameIndex',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 502,
            'endLine' => 502,
            'startColumn' => 40,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 502,
            'endLine' => 502,
            'startColumn' => 62,
            'endColumn' => 76,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile a rename index command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 502,
        'endLine' => 508,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileEnableForeignKeyConstraints' => 
      array (
        'name' => 'compileEnableForeignKeyConstraints',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the command to enable foreign key constraints.
 *
 * @return string
 */',
        'startLine' => 515,
        'endLine' => 518,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDisableForeignKeyConstraints' => 
      array (
        'name' => 'compileDisableForeignKeyConstraints',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the command to disable foreign key constraints.
 *
 * @return string
 */',
        'startLine' => 525,
        'endLine' => 528,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropAllForeignKeys' => 
      array (
        'name' => 'compileDropAllForeignKeys',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the command to drop all foreign keys.
 *
 * @return string
 */',
        'startLine' => 535,
        'endLine' => 544,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'compileDropAllViews' => 
      array (
        'name' => 'compileDropAllViews',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile the command to drop all views.
 *
 * @return string
 */',
        'startLine' => 551,
        'endLine' => 558,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeChar' => 
      array (
        'name' => 'typeChar',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 566,
            'endLine' => 566,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for a char type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 566,
        'endLine' => 569,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeString' => 
      array (
        'name' => 'typeString',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 577,
            'endLine' => 577,
            'startColumn' => 35,
            'endColumn' => 48,
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
 * Create the column definition for a string type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 577,
        'endLine' => 580,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeTinyText' => 
      array (
        'name' => 'typeTinyText',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 588,
            'endLine' => 588,
            'startColumn' => 37,
            'endColumn' => 50,
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
 * Create the column definition for a tiny text type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 588,
        'endLine' => 591,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeText' => 
      array (
        'name' => 'typeText',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 599,
            'endLine' => 599,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for a text type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 599,
        'endLine' => 602,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeMediumText' => 
      array (
        'name' => 'typeMediumText',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 610,
            'endLine' => 610,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Create the column definition for a medium text type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 610,
        'endLine' => 613,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeLongText' => 
      array (
        'name' => 'typeLongText',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 621,
            'endLine' => 621,
            'startColumn' => 37,
            'endColumn' => 50,
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
 * Create the column definition for a long text type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 621,
        'endLine' => 624,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeInteger' => 
      array (
        'name' => 'typeInteger',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 632,
            'endLine' => 632,
            'startColumn' => 36,
            'endColumn' => 49,
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
 * Create the column definition for an integer type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 632,
        'endLine' => 635,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeBigInteger' => 
      array (
        'name' => 'typeBigInteger',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 643,
            'endLine' => 643,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Create the column definition for a big integer type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 643,
        'endLine' => 646,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeMediumInteger' => 
      array (
        'name' => 'typeMediumInteger',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 654,
            'endLine' => 654,
            'startColumn' => 42,
            'endColumn' => 55,
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
 * Create the column definition for a medium integer type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 654,
        'endLine' => 657,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeTinyInteger' => 
      array (
        'name' => 'typeTinyInteger',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 665,
            'endLine' => 665,
            'startColumn' => 40,
            'endColumn' => 53,
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
 * Create the column definition for a tiny integer type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 665,
        'endLine' => 668,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeSmallInteger' => 
      array (
        'name' => 'typeSmallInteger',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 676,
            'endLine' => 676,
            'startColumn' => 41,
            'endColumn' => 54,
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
 * Create the column definition for a small integer type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 676,
        'endLine' => 679,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeFloat' => 
      array (
        'name' => 'typeFloat',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 687,
            'endLine' => 687,
            'startColumn' => 34,
            'endColumn' => 47,
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
 * Create the column definition for a float type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 687,
        'endLine' => 694,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeDouble' => 
      array (
        'name' => 'typeDouble',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 702,
            'endLine' => 702,
            'startColumn' => 35,
            'endColumn' => 48,
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
 * Create the column definition for a double type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 702,
        'endLine' => 705,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeDecimal' => 
      array (
        'name' => 'typeDecimal',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 713,
            'endLine' => 713,
            'startColumn' => 36,
            'endColumn' => 49,
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
 * Create the column definition for a decimal type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 713,
        'endLine' => 716,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeBoolean' => 
      array (
        'name' => 'typeBoolean',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 724,
            'endLine' => 724,
            'startColumn' => 36,
            'endColumn' => 49,
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
 * Create the column definition for a boolean type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 724,
        'endLine' => 727,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeEnum' => 
      array (
        'name' => 'typeEnum',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 735,
            'endLine' => 735,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for an enumeration type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 735,
        'endLine' => 742,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeJson' => 
      array (
        'name' => 'typeJson',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 750,
            'endLine' => 750,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for a json type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 750,
        'endLine' => 753,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeJsonb' => 
      array (
        'name' => 'typeJsonb',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 761,
            'endLine' => 761,
            'startColumn' => 34,
            'endColumn' => 47,
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
 * Create the column definition for a jsonb type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 761,
        'endLine' => 764,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeDate' => 
      array (
        'name' => 'typeDate',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 772,
            'endLine' => 772,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for a date type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 772,
        'endLine' => 779,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeDateTime' => 
      array (
        'name' => 'typeDateTime',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 787,
            'endLine' => 787,
            'startColumn' => 37,
            'endColumn' => 50,
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
 * Create the column definition for a date-time type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 787,
        'endLine' => 790,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeDateTimeTz' => 
      array (
        'name' => 'typeDateTimeTz',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 798,
            'endLine' => 798,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Create the column definition for a date-time (with time zone) type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 798,
        'endLine' => 801,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeTime' => 
      array (
        'name' => 'typeTime',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 809,
            'endLine' => 809,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for a time type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 809,
        'endLine' => 812,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeTimeTz' => 
      array (
        'name' => 'typeTimeTz',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 820,
            'endLine' => 820,
            'startColumn' => 35,
            'endColumn' => 48,
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
 * Create the column definition for a time (with time zone) type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 820,
        'endLine' => 823,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeTimestamp' => 
      array (
        'name' => 'typeTimestamp',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 831,
            'endLine' => 831,
            'startColumn' => 38,
            'endColumn' => 51,
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
 * Create the column definition for a timestamp type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 831,
        'endLine' => 838,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeTimestampTz' => 
      array (
        'name' => 'typeTimestampTz',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 848,
            'endLine' => 848,
            'startColumn' => 40,
            'endColumn' => 53,
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
 * Create the column definition for a timestamp (with time zone) type.
 *
 * @link https://docs.microsoft.com/en-us/sql/t-sql/data-types/datetimeoffset-transact-sql?view=sql-server-ver15
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 848,
        'endLine' => 855,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeYear' => 
      array (
        'name' => 'typeYear',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 863,
            'endLine' => 863,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for a year type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 863,
        'endLine' => 870,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeBinary' => 
      array (
        'name' => 'typeBinary',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 878,
            'endLine' => 878,
            'startColumn' => 35,
            'endColumn' => 48,
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
 * Create the column definition for a binary type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 878,
        'endLine' => 885,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeUuid' => 
      array (
        'name' => 'typeUuid',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 893,
            'endLine' => 893,
            'startColumn' => 33,
            'endColumn' => 46,
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
 * Create the column definition for a uuid type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 893,
        'endLine' => 896,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeIpAddress' => 
      array (
        'name' => 'typeIpAddress',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 904,
            'endLine' => 904,
            'startColumn' => 38,
            'endColumn' => 51,
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
 * Create the column definition for an IP address type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 904,
        'endLine' => 907,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeMacAddress' => 
      array (
        'name' => 'typeMacAddress',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 915,
            'endLine' => 915,
            'startColumn' => 39,
            'endColumn' => 52,
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
 * Create the column definition for a MAC address type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 915,
        'endLine' => 918,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeGeometry' => 
      array (
        'name' => 'typeGeometry',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 926,
            'endLine' => 926,
            'startColumn' => 37,
            'endColumn' => 50,
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
 * Create the column definition for a spatial Geometry type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 926,
        'endLine' => 929,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeGeography' => 
      array (
        'name' => 'typeGeography',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 937,
            'endLine' => 937,
            'startColumn' => 38,
            'endColumn' => 51,
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
 * Create the column definition for a spatial Geography type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 937,
        'endLine' => 940,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'typeComputed' => 
      array (
        'name' => 'typeComputed',
        'parameters' => 
        array (
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 948,
            'endLine' => 948,
            'startColumn' => 37,
            'endColumn' => 50,
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
 * Create the column definition for a generated, computed column type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 948,
        'endLine' => 951,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'modifyCollate' => 
      array (
        'name' => 'modifyCollate',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 960,
            'endLine' => 960,
            'startColumn' => 38,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 960,
            'endLine' => 960,
            'startColumn' => 60,
            'endColumn' => 73,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the SQL for a collation column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 960,
        'endLine' => 965,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'modifyNullable' => 
      array (
        'name' => 'modifyNullable',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 974,
            'endLine' => 974,
            'startColumn' => 39,
            'endColumn' => 58,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 974,
            'endLine' => 974,
            'startColumn' => 61,
            'endColumn' => 74,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the SQL for a nullable column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 974,
        'endLine' => 979,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'modifyDefault' => 
      array (
        'name' => 'modifyDefault',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 988,
            'endLine' => 988,
            'startColumn' => 38,
            'endColumn' => 57,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 988,
            'endLine' => 988,
            'startColumn' => 60,
            'endColumn' => 73,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the SQL for a default column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 988,
        'endLine' => 993,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'modifyIncrement' => 
      array (
        'name' => 'modifyIncrement',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1002,
            'endLine' => 1002,
            'startColumn' => 40,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1002,
            'endLine' => 1002,
            'startColumn' => 62,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the SQL for an auto-increment column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1002,
        'endLine' => 1007,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'modifyPersisted' => 
      array (
        'name' => 'modifyPersisted',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Database\\Schema\\Blueprint',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 40,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'column' => 
          array (
            'name' => 'column',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Support\\Fluent',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 1016,
            'endLine' => 1016,
            'startColumn' => 62,
            'endColumn' => 75,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the SQL for a generated stored column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1016,
        'endLine' => 1029,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'aliasName' => NULL,
      ),
      'quoteString' => 
      array (
        'name' => 'quoteString',
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
            'startLine' => 1037,
            'endLine' => 1037,
            'startColumn' => 33,
            'endColumn' => 38,
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
 * Quote the given string literal.
 *
 * @param  string|array<string>  $value
 * @return string
 */',
        'startLine' => 1037,
        'endLine' => 1044,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\SqlServerGrammar',
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