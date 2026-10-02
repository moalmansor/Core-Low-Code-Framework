<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/horizon/src/RedisQueue.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Laravel\Horizon\RedisQueue
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-ed8cd26dfe40321a487cf9c348c6e4d56656f307856f6c220a338a024588d9d5-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Laravel\\Horizon\\RedisQueue',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/horizon/src/RedisQueue.php',
      ),
    ),
    'namespace' => 'Laravel\\Horizon',
    'name' => 'Laravel\\Horizon\\RedisQueue',
    'shortName' => 'RedisQueue',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 225,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Queue\\RedisQueue',
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
      'lastPushed' => 
      array (
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'name' => 'lastPushed',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The job that last pushed to queue via the "push" method.
 *
 * @var object|string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 23,
        'endLine' => 23,
        'startColumn' => 5,
        'endColumn' => 26,
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
      'readyNow' => 
      array (
        'name' => 'readyNow',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 31,
                'endLine' => 31,
                'startTokenPos' => 90,
                'startFilePos' => 786,
                'endTokenPos' => 90,
                'endFilePos' => 789,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 31,
            'endLine' => 31,
            'startColumn' => 30,
            'endColumn' => 42,
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
 * Get the number of queue jobs that are ready to process.
 *
 * @param  string|null  $queue
 * @return int
 */',
        'startLine' => 31,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'push' => 
      array (
        'name' => 'push',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 26,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 49,
                'endLine' => 49,
                'startTokenPos' => 163,
                'startFilePos' => 1242,
                'endTokenPos' => 163,
                'endFilePos' => 1243,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 32,
            'endColumn' => 41,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'queue' => 
          array (
            'name' => 'queue',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 49,
                'endLine' => 49,
                'startTokenPos' => 170,
                'startFilePos' => 1255,
                'endTokenPos' => 170,
                'endFilePos' => 1258,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 44,
            'endColumn' => 56,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Push a new job onto the queue.
 *
 * @param  object|string  $job
 * @param  mixed  $data
 * @param  string|null  $queue
 * @return mixed
 */',
        'startLine' => 48,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'pushRaw' => 
      array (
        'name' => 'pushRaw',
        'parameters' => 
        array (
          'payload' => 
          array (
            'name' => 'payload',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 29,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'queue' => 
          array (
            'name' => 'queue',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 73,
                'endLine' => 73,
                'startTokenPos' => 275,
                'startFilePos' => 1864,
                'endTokenPos' => 275,
                'endFilePos' => 1867,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 39,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 73,
                'endLine' => 73,
                'startTokenPos' => 284,
                'startFilePos' => 1887,
                'endTokenPos' => 285,
                'endFilePos' => 1888,
              ),
            ),
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
            'startLine' => 73,
            'endLine' => 73,
            'startColumn' => 54,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Push a raw payload onto the queue.
 *
 * @param  string  $payload
 * @param  string|null  $queue
 * @param  array  $options
 * @return mixed
 */',
        'startLine' => 72,
        'endLine' => 84,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'createPayloadArray' => 
      array (
        'name' => 'createPayloadArray',
        'parameters' => 
        array (
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 95,
            'endLine' => 95,
            'startColumn' => 43,
            'endColumn' => 46,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 95,
            'endLine' => 95,
            'startColumn' => 49,
            'endColumn' => 54,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 95,
                'endLine' => 95,
                'startTokenPos' => 406,
                'startFilePos' => 2504,
                'endTokenPos' => 406,
                'endFilePos' => 2505,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 95,
            'endLine' => 95,
            'startColumn' => 57,
            'endColumn' => 66,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Create a payload string from the given job and data.
 *
 * @param  string  $job
 * @param  string  $queue
 * @param  mixed  $data
 * @return array
 */',
        'startLine' => 94,
        'endLine' => 102,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'later' => 
      array (
        'name' => 'later',
        'parameters' => 
        array (
          'delay' => 
          array (
            'name' => 'delay',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 114,
            'endLine' => 114,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 114,
            'endLine' => 114,
            'startColumn' => 35,
            'endColumn' => 38,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '\'\'',
              'attributes' => 
              array (
                'startLine' => 114,
                'endLine' => 114,
                'startTokenPos' => 471,
                'startFilePos' => 2971,
                'endTokenPos' => 471,
                'endFilePos' => 2972,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 114,
            'endLine' => 114,
            'startColumn' => 41,
            'endColumn' => 50,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'queue' => 
          array (
            'name' => 'queue',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 114,
                'endLine' => 114,
                'startTokenPos' => 478,
                'startFilePos' => 2984,
                'endTokenPos' => 478,
                'endFilePos' => 2987,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 114,
            'endLine' => 114,
            'startColumn' => 53,
            'endColumn' => 65,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Push a new job onto the queue after a delay.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int  $delay
 * @param  string  $job
 * @param  mixed  $data
 * @param  string|null  $queue
 * @return mixed
 */',
        'startLine' => 113,
        'endLine' => 143,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'pop' => 
      array (
        'name' => 'pop',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 153,
                'endLine' => 153,
                'startTokenPos' => 787,
                'startFilePos' => 4335,
                'endTokenPos' => 787,
                'endFilePos' => 4338,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 153,
            'endLine' => 153,
            'startColumn' => 25,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'index' => 
          array (
            'name' => 'index',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 153,
                'endLine' => 153,
                'startTokenPos' => 794,
                'startFilePos' => 4350,
                'endTokenPos' => 794,
                'endFilePos' => 4350,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 153,
            'endLine' => 153,
            'startColumn' => 40,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Pop the next job off of the queue.
 *
 * @param  string|null  $queue
 * @param  int  $index
 * @return \\Illuminate\\Contracts\\Queue\\Job|null
 */',
        'startLine' => 152,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'migrateExpiredJobs' => 
      array (
        'name' => 'migrateExpiredJobs',
        'parameters' => 
        array (
          'from' => 
          array (
            'name' => 'from',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 170,
            'endLine' => 170,
            'startColumn' => 40,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'to' => 
          array (
            'name' => 'to',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 170,
            'endLine' => 170,
            'startColumn' => 47,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Migrate the delayed jobs that are ready to the regular queue.
 *
 * @param  string  $from
 * @param  string  $to
 * @return void
 */',
        'startLine' => 169,
        'endLine' => 175,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'deleteReserved' => 
      array (
        'name' => 'deleteReserved',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 185,
            'endLine' => 185,
            'startColumn' => 36,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 185,
            'endLine' => 185,
            'startColumn' => 44,
            'endColumn' => 47,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Delete a reserved job from the queue.
 *
 * @param  string  $queue
 * @param  \\Illuminate\\Queue\\Jobs\\RedisJob  $job
 * @return void
 */',
        'startLine' => 184,
        'endLine' => 190,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'deleteAndRelease' => 
      array (
        'name' => 'deleteAndRelease',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 201,
            'endLine' => 201,
            'startColumn' => 38,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'job' => 
          array (
            'name' => 'job',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 201,
            'endLine' => 201,
            'startColumn' => 46,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'delay' => 
          array (
            'name' => 'delay',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 201,
            'endLine' => 201,
            'startColumn' => 52,
            'endColumn' => 57,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'Override',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * Delete a reserved job from the reserved queue and release it.
 *
 * @param  string  $queue
 * @param  \\Illuminate\\Queue\\Jobs\\RedisJob  $job
 * @param  int  $delay
 * @return void
 */',
        'startLine' => 200,
        'endLine' => 206,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
        'aliasName' => NULL,
      ),
      'event' => 
      array (
        'name' => 'event',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 215,
            'endLine' => 215,
            'startColumn' => 30,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'event' => 
          array (
            'name' => 'event',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 215,
            'endLine' => 215,
            'startColumn' => 38,
            'endColumn' => 43,
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
 * Fire the given event if a dispatcher is bound.
 *
 * @param  string  $queue
 * @param  mixed  $event
 * @return void
 */',
        'startLine' => 215,
        'endLine' => 224,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Laravel\\Horizon',
        'declaringClassName' => 'Laravel\\Horizon\\RedisQueue',
        'implementingClassName' => 'Laravel\\Horizon\\RedisQueue',
        'currentClassName' => 'Laravel\\Horizon\\RedisQueue',
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