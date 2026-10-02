<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/ManagesFrequencies.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Console\Scheduling\ManagesFrequencies
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-14519669997b29b5b6461742bb9bb600d01f83b7aac4b02f789e841fef0b7172-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Console/Scheduling/ManagesFrequencies.php',
      ),
    ),
    'namespace' => 'Illuminate\\Console\\Scheduling',
    'name' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
    'shortName' => 'ManagesFrequencies',
    'isInterface' => false,
    'isTrait' => true,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 10,
    'endLine' => 690,
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
      'cron' => 
      array (
        'name' => 'cron',
        'parameters' => 
        array (
          'expression' => 
          array (
            'name' => 'expression',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 18,
            'endLine' => 18,
            'startColumn' => 26,
            'endColumn' => 36,
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
 * The Cron expression representing the event\'s frequency.
 *
 * @param  string  $expression
 * @return $this
 */',
        'startLine' => 18,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'between' => 
      array (
        'name' => 'between',
        'parameters' => 
        array (
          'startTime' => 
          array (
            'name' => 'startTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'endTime' => 
          array (
            'name' => 'endTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 32,
            'endLine' => 32,
            'startColumn' => 41,
            'endColumn' => 48,
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
 * Schedule the event to run between start and end time.
 *
 * @param  string  $startTime
 * @param  string  $endTime
 * @return $this
 */',
        'startLine' => 32,
        'endLine' => 35,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'unlessBetween' => 
      array (
        'name' => 'unlessBetween',
        'parameters' => 
        array (
          'startTime' => 
          array (
            'name' => 'startTime',
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
            'startColumn' => 35,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'endTime' => 
          array (
            'name' => 'endTime',
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
            'startColumn' => 47,
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
 * Schedule the event to not run between start and end time.
 *
 * @param  string  $startTime
 * @param  string  $endTime
 * @return $this
 */',
        'startLine' => 44,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'inTimeInterval' => 
      array (
        'name' => 'inTimeInterval',
        'parameters' => 
        array (
          'startTime' => 
          array (
            'name' => 'startTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 37,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'endTime' => 
          array (
            'name' => 'endTime',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 56,
            'endLine' => 56,
            'startColumn' => 49,
            'endColumn' => 56,
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
 * Schedule the event to run between start and end time.
 *
 * @param  string  $startTime
 * @param  string  $endTime
 * @return \\Closure
 */',
        'startLine' => 56,
        'endLine' => 73,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everySecond' => 
      array (
        'name' => 'everySecond',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every second.
 *
 * @return $this
 */',
        'startLine' => 80,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwoSeconds' => 
      array (
        'name' => 'everyTwoSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every two seconds.
 *
 * @return $this
 */',
        'startLine' => 90,
        'endLine' => 93,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFiveSeconds' => 
      array (
        'name' => 'everyFiveSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every five seconds.
 *
 * @return $this
 */',
        'startLine' => 100,
        'endLine' => 103,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTenSeconds' => 
      array (
        'name' => 'everyTenSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every ten seconds.
 *
 * @return $this
 */',
        'startLine' => 110,
        'endLine' => 113,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFifteenSeconds' => 
      array (
        'name' => 'everyFifteenSeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every fifteen seconds.
 *
 * @return $this
 */',
        'startLine' => 120,
        'endLine' => 123,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwentySeconds' => 
      array (
        'name' => 'everyTwentySeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every twenty seconds.
 *
 * @return $this
 */',
        'startLine' => 130,
        'endLine' => 133,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThirtySeconds' => 
      array (
        'name' => 'everyThirtySeconds',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every thirty seconds.
 *
 * @return $this
 */',
        'startLine' => 140,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'repeatEvery' => 
      array (
        'name' => 'repeatEvery',
        'parameters' => 
        array (
          'seconds' => 
          array (
            'name' => 'seconds',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 153,
            'endLine' => 153,
            'startColumn' => 36,
            'endColumn' => 43,
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
 * Schedule the event to run multiple times per minute.
 *
 * @param  int<1, 59>  $seconds
 * @return $this
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 153,
        'endLine' => 166,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyMinute' => 
      array (
        'name' => 'everyMinute',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every minute.
 *
 * @return $this
 */',
        'startLine' => 173,
        'endLine' => 176,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwoMinutes' => 
      array (
        'name' => 'everyTwoMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every two minutes.
 *
 * @return $this
 */',
        'startLine' => 183,
        'endLine' => 186,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThreeMinutes' => 
      array (
        'name' => 'everyThreeMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every three minutes.
 *
 * @return $this
 */',
        'startLine' => 193,
        'endLine' => 196,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFourMinutes' => 
      array (
        'name' => 'everyFourMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every four minutes.
 *
 * @return $this
 */',
        'startLine' => 203,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFiveMinutes' => 
      array (
        'name' => 'everyFiveMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every five minutes.
 *
 * @return $this
 */',
        'startLine' => 213,
        'endLine' => 216,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTenMinutes' => 
      array (
        'name' => 'everyTenMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every ten minutes.
 *
 * @return $this
 */',
        'startLine' => 223,
        'endLine' => 226,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFifteenMinutes' => 
      array (
        'name' => 'everyFifteenMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every fifteen minutes.
 *
 * @return $this
 */',
        'startLine' => 233,
        'endLine' => 236,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThirtyMinutes' => 
      array (
        'name' => 'everyThirtyMinutes',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every thirty minutes.
 *
 * @return $this
 */',
        'startLine' => 243,
        'endLine' => 246,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'hourly' => 
      array (
        'name' => 'hourly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run hourly.
 *
 * @return $this
 */',
        'startLine' => 253,
        'endLine' => 256,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'hourlyAt' => 
      array (
        'name' => 'hourlyAt',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 264,
            'endLine' => 264,
            'startColumn' => 30,
            'endColumn' => 36,
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
 * Schedule the event to run hourly at a given offset in the hour.
 *
 * @param  array|string|int<0, 59>|int<0, 59>[]  $offset
 * @return $this
 */',
        'startLine' => 264,
        'endLine' => 267,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyOddHour' => 
      array (
        'name' => 'everyOddHour',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 275,
                'endLine' => 275,
                'startTokenPos' => 826,
                'startFilePos' => 5986,
                'endTokenPos' => 826,
                'endFilePos' => 5986,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 275,
            'endLine' => 275,
            'startColumn' => 34,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every odd hour.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 275,
        'endLine' => 278,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyTwoHours' => 
      array (
        'name' => 'everyTwoHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 286,
                'endLine' => 286,
                'startTokenPos' => 858,
                'startFilePos' => 6241,
                'endTokenPos' => 858,
                'endFilePos' => 6241,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 286,
            'endLine' => 286,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every two hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 286,
        'endLine' => 289,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyThreeHours' => 
      array (
        'name' => 'everyThreeHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 297,
                'endLine' => 297,
                'startTokenPos' => 890,
                'startFilePos' => 6497,
                'endTokenPos' => 890,
                'endFilePos' => 6497,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 297,
            'endLine' => 297,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every three hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 297,
        'endLine' => 300,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everyFourHours' => 
      array (
        'name' => 'everyFourHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 308,
                'endLine' => 308,
                'startTokenPos' => 922,
                'startFilePos' => 6751,
                'endTokenPos' => 922,
                'endFilePos' => 6751,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 308,
            'endLine' => 308,
            'startColumn' => 36,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every four hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 308,
        'endLine' => 311,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'everySixHours' => 
      array (
        'name' => 'everySixHours',
        'parameters' => 
        array (
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 319,
                'endLine' => 319,
                'startTokenPos' => 954,
                'startFilePos' => 7003,
                'endTokenPos' => 954,
                'endFilePos' => 7003,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 319,
            'endLine' => 319,
            'startColumn' => 35,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run every six hours.
 *
 * @param  array|string|int  $offset
 * @return $this
 */',
        'startLine' => 319,
        'endLine' => 322,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'daily' => 
      array (
        'name' => 'daily',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run daily.
 *
 * @return $this
 */',
        'startLine' => 329,
        'endLine' => 332,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'at' => 
      array (
        'name' => 'at',
        'parameters' => 
        array (
          'time' => 
          array (
            'name' => 'time',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 340,
            'endLine' => 340,
            'startColumn' => 24,
            'endColumn' => 28,
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
 * Schedule the command at a given time.
 *
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 340,
        'endLine' => 343,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'dailyAt' => 
      array (
        'name' => 'dailyAt',
        'parameters' => 
        array (
          'time' => 
          array (
            'name' => 'time',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 351,
            'endLine' => 351,
            'startColumn' => 29,
            'endColumn' => 33,
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
 * Schedule the event to run daily at a given time (10:00, 19:30, etc).
 *
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 351,
        'endLine' => 359,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'twiceDaily' => 
      array (
        'name' => 'twiceDaily',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 368,
                'endLine' => 368,
                'startTokenPos' => 1106,
                'startFilePos' => 8030,
                'endTokenPos' => 1106,
                'endFilePos' => 8030,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 368,
            'endLine' => 368,
            'startColumn' => 32,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => '13',
              'attributes' => 
              array (
                'startLine' => 368,
                'endLine' => 368,
                'startTokenPos' => 1113,
                'startFilePos' => 8043,
                'endTokenPos' => 1113,
                'endFilePos' => 8044,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 368,
            'endLine' => 368,
            'startColumn' => 44,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run twice daily.
 *
 * @param  int<0, 23>  $first
 * @param  int<0, 23>  $second
 * @return $this
 */',
        'startLine' => 368,
        'endLine' => 371,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'twiceDailyAt' => 
      array (
        'name' => 'twiceDailyAt',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 381,
                'endLine' => 381,
                'startTokenPos' => 1148,
                'startFilePos' => 8370,
                'endTokenPos' => 1148,
                'endFilePos' => 8370,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 381,
            'endLine' => 381,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => '13',
              'attributes' => 
              array (
                'startLine' => 381,
                'endLine' => 381,
                'startTokenPos' => 1155,
                'startFilePos' => 8383,
                'endTokenPos' => 1155,
                'endFilePos' => 8384,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 381,
            'endLine' => 381,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'offset' => 
          array (
            'name' => 'offset',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 381,
                'endLine' => 381,
                'startTokenPos' => 1162,
                'startFilePos' => 8397,
                'endTokenPos' => 1162,
                'endFilePos' => 8397,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 381,
            'endLine' => 381,
            'startColumn' => 60,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run twice daily at a given offset.
 *
 * @param  int<0, 23>  $first
 * @param  int<0, 23>  $second
 * @param  int<0, 59>  $offset
 * @return $this
 */',
        'startLine' => 381,
        'endLine' => 386,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'hourBasedSchedule' => 
      array (
        'name' => 'hourBasedSchedule',
        'parameters' => 
        array (
          'minutes' => 
          array (
            'name' => 'minutes',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 395,
            'endLine' => 395,
            'startColumn' => 42,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'hours' => 
          array (
            'name' => 'hours',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 395,
            'endLine' => 395,
            'startColumn' => 52,
            'endColumn' => 57,
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
 * Schedule the event to run at the given minutes and hours.
 *
 * @param  array|string|int<0, 59>  $minutes
 * @param  array|string|int<0, 23>  $hours
 * @return $this
 */',
        'startLine' => 395,
        'endLine' => 403,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weekdays' => 
      array (
        'name' => 'weekdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on weekdays.
 *
 * @return $this
 */',
        'startLine' => 410,
        'endLine' => 413,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weekends' => 
      array (
        'name' => 'weekends',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on weekends.
 *
 * @return $this
 */',
        'startLine' => 420,
        'endLine' => 423,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'mondays' => 
      array (
        'name' => 'mondays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Mondays.
 *
 * @return $this
 */',
        'startLine' => 430,
        'endLine' => 433,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'tuesdays' => 
      array (
        'name' => 'tuesdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Tuesdays.
 *
 * @return $this
 */',
        'startLine' => 440,
        'endLine' => 443,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'wednesdays' => 
      array (
        'name' => 'wednesdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Wednesdays.
 *
 * @return $this
 */',
        'startLine' => 450,
        'endLine' => 453,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'thursdays' => 
      array (
        'name' => 'thursdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Thursdays.
 *
 * @return $this
 */',
        'startLine' => 460,
        'endLine' => 463,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'fridays' => 
      array (
        'name' => 'fridays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Fridays.
 *
 * @return $this
 */',
        'startLine' => 470,
        'endLine' => 473,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'saturdays' => 
      array (
        'name' => 'saturdays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Saturdays.
 *
 * @return $this
 */',
        'startLine' => 480,
        'endLine' => 483,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'sundays' => 
      array (
        'name' => 'sundays',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run only on Sundays.
 *
 * @return $this
 */',
        'startLine' => 490,
        'endLine' => 493,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weekly' => 
      array (
        'name' => 'weekly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run weekly.
 *
 * @return $this
 */',
        'startLine' => 500,
        'endLine' => 505,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'weeklyOn' => 
      array (
        'name' => 'weeklyOn',
        'parameters' => 
        array (
          'dayOfWeek' => 
          array (
            'name' => 'dayOfWeek',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 514,
            'endLine' => 514,
            'startColumn' => 30,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 514,
                'endLine' => 514,
                'startTokenPos' => 1587,
                'startFilePos' => 11221,
                'endTokenPos' => 1587,
                'endFilePos' => 11225,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 514,
            'endLine' => 514,
            'startColumn' => 42,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run weekly on a given day and time.
 *
 * @param  mixed  $dayOfWeek
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 514,
        'endLine' => 519,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'monthly' => 
      array (
        'name' => 'monthly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run monthly.
 *
 * @return $this
 */',
        'startLine' => 526,
        'endLine' => 531,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'monthlyOn' => 
      array (
        'name' => 'monthlyOn',
        'parameters' => 
        array (
          'dayOfMonth' => 
          array (
            'name' => 'dayOfMonth',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 540,
                'endLine' => 540,
                'startTokenPos' => 1669,
                'startFilePos' => 11790,
                'endTokenPos' => 1669,
                'endFilePos' => 11790,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 540,
            'endLine' => 540,
            'startColumn' => 31,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 540,
                'endLine' => 540,
                'startTokenPos' => 1676,
                'startFilePos' => 11801,
                'endTokenPos' => 1676,
                'endFilePos' => 11805,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 540,
            'endLine' => 540,
            'startColumn' => 48,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run monthly on a given day and time.
 *
 * @param  int<1, 31>  $dayOfMonth
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 540,
        'endLine' => 545,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'twiceMonthly' => 
      array (
        'name' => 'twiceMonthly',
        'parameters' => 
        array (
          'first' => 
          array (
            'name' => 'first',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 555,
                'endLine' => 555,
                'startTokenPos' => 1716,
                'startFilePos' => 12159,
                'endTokenPos' => 1716,
                'endFilePos' => 12159,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 555,
            'endLine' => 555,
            'startColumn' => 34,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => '16',
              'attributes' => 
              array (
                'startLine' => 555,
                'endLine' => 555,
                'startTokenPos' => 1723,
                'startFilePos' => 12172,
                'endTokenPos' => 1723,
                'endFilePos' => 12173,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 555,
            'endLine' => 555,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 555,
                'endLine' => 555,
                'startTokenPos' => 1730,
                'startFilePos' => 12184,
                'endTokenPos' => 1730,
                'endFilePos' => 12188,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 555,
            'endLine' => 555,
            'startColumn' => 60,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run twice monthly at a given time.
 *
 * @param  int<1, 31>  $first
 * @param  int<1, 31>  $second
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 555,
        'endLine' => 562,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'lastDayOfMonth' => 
      array (
        'name' => 'lastDayOfMonth',
        'parameters' => 
        array (
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 570,
                'endLine' => 570,
                'startTokenPos' => 1781,
                'startFilePos' => 12518,
                'endTokenPos' => 1781,
                'endFilePos' => 12522,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 570,
            'endLine' => 570,
            'startColumn' => 36,
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
        'docComment' => '/**
 * Schedule the event to run on the last day of the month.
 *
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 570,
        'endLine' => 575,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'daysOfMonth' => 
      array (
        'name' => 'daysOfMonth',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => true,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 583,
            'endLine' => 583,
            'startColumn' => 33,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run on specific days of the month.
 *
 * @param  array<int<1, 31>>|int<1, 31>  ...$days
 * @return $this
 */',
        'startLine' => 583,
        'endLine' => 590,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'quarterly' => 
      array (
        'name' => 'quarterly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run quarterly.
 *
 * @return $this
 */',
        'startLine' => 597,
        'endLine' => 603,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'quarterlyOn' => 
      array (
        'name' => 'quarterlyOn',
        'parameters' => 
        array (
          'dayOfQuarter' => 
          array (
            'name' => 'dayOfQuarter',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 612,
                'endLine' => 612,
                'startTokenPos' => 1963,
                'startFilePos' => 13570,
                'endTokenPos' => 1963,
                'endFilePos' => 13570,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 33,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 612,
                'endLine' => 612,
                'startTokenPos' => 1970,
                'startFilePos' => 13581,
                'endTokenPos' => 1970,
                'endFilePos' => 13585,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 612,
            'endLine' => 612,
            'startColumn' => 52,
            'endColumn' => 64,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run quarterly on a given day and time.
 *
 * @param  int  $dayOfQuarter
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 612,
        'endLine' => 618,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'yearly' => 
      array (
        'name' => 'yearly',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run yearly.
 *
 * @return $this
 */',
        'startLine' => 625,
        'endLine' => 631,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'yearlyOn' => 
      array (
        'name' => 'yearlyOn',
        'parameters' => 
        array (
          'month' => 
          array (
            'name' => 'month',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 641,
                'endLine' => 641,
                'startTokenPos' => 2073,
                'startFilePos' => 14288,
                'endTokenPos' => 2073,
                'endFilePos' => 14288,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 641,
            'endLine' => 641,
            'startColumn' => 30,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'dayOfMonth' => 
          array (
            'name' => 'dayOfMonth',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 641,
                'endLine' => 641,
                'startTokenPos' => 2080,
                'startFilePos' => 14305,
                'endTokenPos' => 2080,
                'endFilePos' => 14305,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 641,
            'endLine' => 641,
            'startColumn' => 42,
            'endColumn' => 56,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '\'0:0\'',
              'attributes' => 
              array (
                'startLine' => 641,
                'endLine' => 641,
                'startTokenPos' => 2087,
                'startFilePos' => 14316,
                'endTokenPos' => 2087,
                'endFilePos' => 14320,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 641,
            'endLine' => 641,
            'startColumn' => 59,
            'endColumn' => 71,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Schedule the event to run yearly on a given month, day, and time.
 *
 * @param  int  $month
 * @param  int<1, 31>|string  $dayOfMonth
 * @param  string  $time
 * @return $this
 */',
        'startLine' => 641,
        'endLine' => 647,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'days' => 
      array (
        'name' => 'days',
        'parameters' => 
        array (
          'days' => 
          array (
            'name' => 'days',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 655,
            'endLine' => 655,
            'startColumn' => 26,
            'endColumn' => 30,
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
 * Set the days of the week the command should run on.
 *
 * @param  mixed  $days
 * @return $this
 */',
        'startLine' => 655,
        'endLine' => 660,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => true,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'timezone' => 
      array (
        'name' => 'timezone',
        'parameters' => 
        array (
          'timezone' => 
          array (
            'name' => 'timezone',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 668,
            'endLine' => 668,
            'startColumn' => 30,
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
 * Set the timezone the date should be evaluated on.
 *
 * @param  \\UnitEnum|\\DateTimeZone|string  $timezone
 * @return $this
 */',
        'startLine' => 668,
        'endLine' => 673,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'aliasName' => NULL,
      ),
      'spliceIntoPosition' => 
      array (
        'name' => 'spliceIntoPosition',
        'parameters' => 
        array (
          'position' => 
          array (
            'name' => 'position',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 682,
            'endLine' => 682,
            'startColumn' => 43,
            'endColumn' => 51,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 682,
            'endLine' => 682,
            'startColumn' => 54,
            'endColumn' => 59,
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
 * Splice the given value into the given position of the expression.
 *
 * @param  int  $position
 * @param  string|int  $value
 * @return $this
 */',
        'startLine' => 682,
        'endLine' => 689,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Console\\Scheduling',
        'declaringClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'implementingClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
        'currentClassName' => 'Illuminate\\Console\\Scheduling\\ManagesFrequencies',
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