<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Database/Schema/Grammars/MySqlGrammar.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Database\Schema\Grammars\MySqlGrammar
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-1869f1075d11d2e8381eb775f20d1e144ca62d17d36d5e635a970f5e0cee791c-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Database/Schema/Grammars/MySqlGrammar.php',
      ),
    ),
    'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
    'name' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
    'shortName' => 'MySqlGrammar',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 1435,
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
      'modifiers' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'name' => 'modifiers',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'Unsigned\', \'Charset\', \'Collate\', \'VirtualAs\', \'StoredAs\', \'Nullable\', \'Default\', \'OnUpdate\', \'Invisible\', \'Increment\', \'Comment\', \'After\', \'First\']',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 22,
            'startTokenPos' => 55,
            'startFilePos' => 423,
            'endTokenPos' => 96,
            'endFilePos' => 594,
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
        'startLine' => 19,
        'endLine' => 22,
        'startColumn' => 5,
        'endColumn' => 6,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'serials' => 
      array (
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'name' => 'serials',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'bigInteger\', \'integer\', \'mediumInteger\', \'smallInteger\', \'tinyInteger\']',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 107,
            'startFilePos' => 703,
            'endTokenPos' => 121,
            'endFilePos' => 775,
          ),
        ),
        'docComment' => '/**
 * The possible column serials.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'name' => 'fluentCommands',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'AutoIncrementStartingValues\']',
          'attributes' => 
          array (
            'startLine' => 36,
            'endLine' => 36,
            'startTokenPos' => 132,
            'startFilePos' => 927,
            'endTokenPos' => 134,
            'endFilePos' => 957,
          ),
        ),
        'docComment' => '/**
 * The commands to be executed outside of create or alter commands.
 *
 * @var string[]
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 36,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 64,
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
      'compileCreateDatabase' => 
      array (
        'name' => 'compileCreateDatabase',
        'parameters' => 
        array (
          'name' => 
          array (
            'name' => 'name',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 44,
            'endLine' => 44,
            'startColumn' => 43,
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
 * Compile a create database command.
 *
 * @param  string  $name
 * @return string
 */',
        'startLine' => 44,
        'endLine' => 57,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
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
        'startLine' => 64,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 78,
            'endLine' => 78,
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
            'startLine' => 78,
            'endLine' => 78,
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
        'startLine' => 78,
        'endLine' => 86,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 94,
            'endLine' => 94,
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
        'startLine' => 94,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 112,
            'endLine' => 112,
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
        'startLine' => 112,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 127,
            'endLine' => 127,
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
            'startLine' => 127,
            'endLine' => 127,
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
        'startLine' => 127,
        'endLine' => 134,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 143,
            'endLine' => 143,
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
            'startLine' => 143,
            'endLine' => 143,
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
        'startLine' => 143,
        'endLine' => 155,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 164,
            'endLine' => 164,
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
            'startLine' => 164,
            'endLine' => 164,
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
        'startLine' => 164,
        'endLine' => 174,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 183,
            'endLine' => 183,
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
            'startLine' => 183,
            'endLine' => 183,
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
        'startLine' => 183,
        'endLine' => 200,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 209,
            'endLine' => 209,
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
            'startLine' => 209,
            'endLine' => 209,
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
        'startLine' => 209,
        'endLine' => 226,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileCreateTable' => 
      array (
        'name' => 'compileCreateTable',
        'parameters' => 
        array (
          'blueprint' => 
          array (
            'name' => 'blueprint',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 235,
            'endLine' => 235,
            'startColumn' => 43,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'command' => 
          array (
            'name' => 'command',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 235,
            'endLine' => 235,
            'startColumn' => 55,
            'endColumn' => 62,
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
 * Create the main create table clause.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 235,
        'endLine' => 254,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileCreateEncoding' => 
      array (
        'name' => 'compileCreateEncoding',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 263,
            'endLine' => 263,
            'startColumn' => 46,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 263,
            'endLine' => 263,
            'startColumn' => 52,
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
 * Append the character set specifications to a command.
 *
 * @param  string  $sql
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @return string
 */',
        'startLine' => 263,
        'endLine' => 284,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileCreateEngine' => 
      array (
        'name' => 'compileCreateEngine',
        'parameters' => 
        array (
          'sql' => 
          array (
            'name' => 'sql',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 44,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 293,
            'endLine' => 293,
            'startColumn' => 50,
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
 * Append the engine specifications to a command.
 *
 * @param  string  $sql
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @return string
 */',
        'startLine' => 293,
        'endLine' => 302,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 311,
            'endLine' => 311,
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
            'startLine' => 311,
            'endLine' => 311,
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
 * Compile an add column command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 311,
        'endLine' => 319,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileAutoIncrementStartingValues' => 
      array (
        'name' => 'compileAutoIncrementStartingValues',
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
            'startLine' => 328,
            'endLine' => 328,
            'startColumn' => 56,
            'endColumn' => 75,
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
            'startLine' => 328,
            'endLine' => 328,
            'startColumn' => 78,
            'endColumn' => 92,
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
 * Compile the auto-incrementing column starting values.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 328,
        'endLine' => 334,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 337,
            'endLine' => 337,
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
            'startLine' => 337,
            'endLine' => 337,
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
        'startLine' => 337,
        'endLine' => 348,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileLegacyRenameColumn' => 
      array (
        'name' => 'compileLegacyRenameColumn',
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
            'startLine' => 357,
            'endLine' => 357,
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
            'startLine' => 357,
            'endLine' => 357,
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
 * Compile a rename column command for legacy versions of MySQL.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 357,
        'endLine' => 393,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 396,
            'endLine' => 396,
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
            'startLine' => 396,
            'endLine' => 396,
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
        'startLine' => 396,
        'endLine' => 419,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 428,
            'endLine' => 428,
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
            'startLine' => 428,
            'endLine' => 428,
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
        'startLine' => 428,
        'endLine' => 436,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 445,
            'endLine' => 445,
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
            'startLine' => 445,
            'endLine' => 445,
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
        'startLine' => 445,
        'endLine' => 448,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 457,
            'endLine' => 457,
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
            'startLine' => 457,
            'endLine' => 457,
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
        'startLine' => 457,
        'endLine' => 460,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileFullText' => 
      array (
        'name' => 'compileFullText',
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
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 37,
            'endColumn' => 56,
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
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 59,
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
 * Compile a fulltext index key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 469,
        'endLine' => 472,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 481,
            'endLine' => 481,
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
            'startLine' => 481,
            'endLine' => 481,
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
        'startLine' => 481,
        'endLine' => 484,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileKey' => 
      array (
        'name' => 'compileKey',
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
            'startLine' => 494,
            'endLine' => 494,
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
            'startLine' => 494,
            'endLine' => 494,
            'startColumn' => 57,
            'endColumn' => 71,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'type' => 
          array (
            'name' => 'type',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 494,
            'endLine' => 494,
            'startColumn' => 74,
            'endColumn' => 78,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compile an index creation command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @param  string  $type
 * @return string
 */',
        'startLine' => 494,
        'endLine' => 504,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 513,
            'endLine' => 513,
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
            'startLine' => 513,
            'endLine' => 513,
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
        'startLine' => 513,
        'endLine' => 516,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 525,
            'endLine' => 525,
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
            'startLine' => 525,
            'endLine' => 525,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 537,
            'endLine' => 537,
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
            'startLine' => 537,
            'endLine' => 537,
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
        'startLine' => 537,
        'endLine' => 552,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 561,
            'endLine' => 561,
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
            'startLine' => 561,
            'endLine' => 561,
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
        'startLine' => 561,
        'endLine' => 564,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 573,
            'endLine' => 573,
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
            'startLine' => 573,
            'endLine' => 573,
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
        'startLine' => 573,
        'endLine' => 578,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 587,
            'endLine' => 587,
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
            'startLine' => 587,
            'endLine' => 587,
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
        'startLine' => 587,
        'endLine' => 592,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileDropFullText' => 
      array (
        'name' => 'compileDropFullText',
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
            'startLine' => 601,
            'endLine' => 601,
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
            'startLine' => 601,
            'endLine' => 601,
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
 * Compile a drop fulltext index command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 601,
        'endLine' => 604,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 613,
            'endLine' => 613,
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
            'startLine' => 613,
            'endLine' => 613,
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
        'startLine' => 613,
        'endLine' => 616,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileForeign' => 
      array (
        'name' => 'compileForeign',
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
            'startLine' => 625,
            'endLine' => 625,
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
            'startLine' => 625,
            'endLine' => 625,
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
 * Compile a foreign key command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 625,
        'endLine' => 634,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 643,
            'endLine' => 643,
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
            'startLine' => 643,
            'endLine' => 643,
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
        'startLine' => 643,
        'endLine' => 648,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 657,
            'endLine' => 657,
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
            'startLine' => 657,
            'endLine' => 657,
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
        'startLine' => 657,
        'endLine' => 662,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 671,
            'endLine' => 671,
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
            'startLine' => 671,
            'endLine' => 671,
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
        'startLine' => 671,
        'endLine' => 678,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileDropAllTables' => 
      array (
        'name' => 'compileDropAllTables',
        'parameters' => 
        array (
          'tables' => 
          array (
            'name' => 'tables',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 686,
            'endLine' => 686,
            'startColumn' => 42,
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
 * Compile the SQL needed to drop all tables.
 *
 * @param  array<string>  $tables
 * @return string
 */',
        'startLine' => 686,
        'endLine' => 689,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileDropAllViews' => 
      array (
        'name' => 'compileDropAllViews',
        'parameters' => 
        array (
          'views' => 
          array (
            'name' => 'views',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 697,
            'endLine' => 697,
            'startColumn' => 41,
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
 * Compile the SQL needed to drop all views.
 *
 * @param  array<string>  $views
 * @return string
 */',
        'startLine' => 697,
        'endLine' => 700,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
        'startLine' => 707,
        'endLine' => 710,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
        'startLine' => 717,
        'endLine' => 720,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'compileTableComment' => 
      array (
        'name' => 'compileTableComment',
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
            'startLine' => 729,
            'endLine' => 729,
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
            'startLine' => 729,
            'endLine' => 729,
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
 * Compile a table comment command.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $command
 * @return string
 */',
        'startLine' => 729,
        'endLine' => 735,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'escapeNames' => 
      array (
        'name' => 'escapeNames',
        'parameters' => 
        array (
          'names' => 
          array (
            'name' => 'names',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 743,
            'endLine' => 743,
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
 * Quote-escape the given tables, views, or types.
 *
 * @param  array<string>  $names
 * @return array<string>
 */',
        'startLine' => 743,
        'endLine' => 749,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 757,
            'endLine' => 757,
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
        'startLine' => 757,
        'endLine' => 760,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 768,
            'endLine' => 768,
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
        'startLine' => 768,
        'endLine' => 771,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 779,
            'endLine' => 779,
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
        'startLine' => 779,
        'endLine' => 782,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 790,
            'endLine' => 790,
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
        'startLine' => 790,
        'endLine' => 793,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 801,
            'endLine' => 801,
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
        'startLine' => 801,
        'endLine' => 804,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 812,
            'endLine' => 812,
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
        'startLine' => 812,
        'endLine' => 815,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 823,
            'endLine' => 823,
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
        'startLine' => 823,
        'endLine' => 826,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 834,
            'endLine' => 834,
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
        'startLine' => 834,
        'endLine' => 837,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 845,
            'endLine' => 845,
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
        'startLine' => 845,
        'endLine' => 848,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 856,
            'endLine' => 856,
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
        'startLine' => 856,
        'endLine' => 859,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 867,
            'endLine' => 867,
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
        'startLine' => 867,
        'endLine' => 870,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 878,
            'endLine' => 878,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 893,
            'endLine' => 893,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 904,
            'endLine' => 904,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 915,
            'endLine' => 915,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 926,
            'endLine' => 926,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'typeSet' => 
      array (
        'name' => 'typeSet',
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
            'startColumn' => 32,
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
 * Create the column definition for a set enumeration type.
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 948,
            'endLine' => 948,
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
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 959,
            'endLine' => 959,
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
        'startLine' => 959,
        'endLine' => 962,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 970,
            'endLine' => 970,
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
        'startLine' => 970,
        'endLine' => 983,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 991,
            'endLine' => 991,
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
        'startLine' => 991,
        'endLine' => 1004,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1012,
            'endLine' => 1012,
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
        'startLine' => 1012,
        'endLine' => 1015,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1023,
            'endLine' => 1023,
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
        'startLine' => 1023,
        'endLine' => 1026,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1034,
            'endLine' => 1034,
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
        'startLine' => 1034,
        'endLine' => 1037,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1045,
            'endLine' => 1045,
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
        'startLine' => 1045,
        'endLine' => 1058,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1066,
            'endLine' => 1066,
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
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 1066,
        'endLine' => 1069,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1077,
            'endLine' => 1077,
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
        'startLine' => 1077,
        'endLine' => 1090,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1098,
            'endLine' => 1098,
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
        'startLine' => 1098,
        'endLine' => 1105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1113,
            'endLine' => 1113,
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
        'startLine' => 1113,
        'endLine' => 1116,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1124,
            'endLine' => 1124,
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
        'startLine' => 1124,
        'endLine' => 1127,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1135,
            'endLine' => 1135,
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
        'startLine' => 1135,
        'endLine' => 1138,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1146,
            'endLine' => 1146,
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
        'startLine' => 1146,
        'endLine' => 1162,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1170,
            'endLine' => 1170,
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
        'startLine' => 1170,
        'endLine' => 1173,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1183,
            'endLine' => 1183,
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
 * @return void
 *
 * @throws \\RuntimeException
 */',
        'startLine' => 1183,
        'endLine' => 1186,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'typeVector' => 
      array (
        'name' => 'typeVector',
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
            'startLine' => 1194,
            'endLine' => 1194,
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
 * Create the column definition for a vector type.
 *
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string
 */',
        'startLine' => 1194,
        'endLine' => 1199,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyVirtualAs' => 
      array (
        'name' => 'modifyVirtualAs',
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
            'startLine' => 1208,
            'endLine' => 1208,
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
            'startLine' => 1208,
            'endLine' => 1208,
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
 * Get the SQL for a generated virtual column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1208,
        'endLine' => 1221,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyStoredAs' => 
      array (
        'name' => 'modifyStoredAs',
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
            'startLine' => 1230,
            'endLine' => 1230,
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
            'startLine' => 1230,
            'endLine' => 1230,
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
 * Get the SQL for a generated stored column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1230,
        'endLine' => 1243,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyUnsigned' => 
      array (
        'name' => 'modifyUnsigned',
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
            'startLine' => 1252,
            'endLine' => 1252,
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
            'startLine' => 1252,
            'endLine' => 1252,
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
 * Get the SQL for an unsigned column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1252,
        'endLine' => 1257,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyCharset' => 
      array (
        'name' => 'modifyCharset',
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
            'startLine' => 1266,
            'endLine' => 1266,
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
            'startLine' => 1266,
            'endLine' => 1266,
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
 * Get the SQL for a character set column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1266,
        'endLine' => 1271,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1280,
            'endLine' => 1280,
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
            'startLine' => 1280,
            'endLine' => 1280,
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
        'startLine' => 1280,
        'endLine' => 1285,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1294,
            'endLine' => 1294,
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
            'startLine' => 1294,
            'endLine' => 1294,
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
        'startLine' => 1294,
        'endLine' => 1306,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyInvisible' => 
      array (
        'name' => 'modifyInvisible',
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
            'startLine' => 1315,
            'endLine' => 1315,
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
            'startLine' => 1315,
            'endLine' => 1315,
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
 * Get the SQL for an invisible column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1315,
        'endLine' => 1320,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1329,
            'endLine' => 1329,
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
            'startLine' => 1329,
            'endLine' => 1329,
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
        'startLine' => 1329,
        'endLine' => 1334,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyOnUpdate' => 
      array (
        'name' => 'modifyOnUpdate',
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
            'startLine' => 1343,
            'endLine' => 1343,
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
            'startLine' => 1343,
            'endLine' => 1343,
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
 * Get the SQL for an "on update" column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1343,
        'endLine' => 1348,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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
            'startLine' => 1357,
            'endLine' => 1357,
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
            'startLine' => 1357,
            'endLine' => 1357,
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
        'startLine' => 1357,
        'endLine' => 1364,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyFirst' => 
      array (
        'name' => 'modifyFirst',
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
            'startLine' => 1373,
            'endLine' => 1373,
            'startColumn' => 36,
            'endColumn' => 55,
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
            'startLine' => 1373,
            'endLine' => 1373,
            'startColumn' => 58,
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
 * Get the SQL for a "first" column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1373,
        'endLine' => 1378,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyAfter' => 
      array (
        'name' => 'modifyAfter',
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
            'startLine' => 1387,
            'endLine' => 1387,
            'startColumn' => 36,
            'endColumn' => 55,
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
            'startLine' => 1387,
            'endLine' => 1387,
            'startColumn' => 58,
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
 * Get the SQL for an "after" column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1387,
        'endLine' => 1392,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'modifyComment' => 
      array (
        'name' => 'modifyComment',
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
            'startLine' => 1401,
            'endLine' => 1401,
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
            'startLine' => 1401,
            'endLine' => 1401,
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
 * Get the SQL for a "comment" column modifier.
 *
 * @param  \\Illuminate\\Database\\Schema\\Blueprint  $blueprint
 * @param  \\Illuminate\\Support\\Fluent  $column
 * @return string|null
 */',
        'startLine' => 1401,
        'endLine' => 1406,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'wrapValue' => 
      array (
        'name' => 'wrapValue',
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
            'startLine' => 1414,
            'endLine' => 1414,
            'startColumn' => 34,
            'endColumn' => 39,
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
 * Wrap a single string in keyword identifiers.
 *
 * @param  string  $value
 * @return string
 */',
        'startLine' => 1414,
        'endLine' => 1421,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'aliasName' => NULL,
      ),
      'wrapJsonSelector' => 
      array (
        'name' => 'wrapJsonSelector',
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
            'startLine' => 1429,
            'endLine' => 1429,
            'startColumn' => 41,
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
 * Wrap the given JSON selector.
 *
 * @param  string  $value
 * @return string
 */',
        'startLine' => 1429,
        'endLine' => 1434,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Database\\Schema\\Grammars',
        'declaringClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'implementingClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
        'currentClassName' => 'Illuminate\\Database\\Schema\\Grammars\\MySqlGrammar',
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