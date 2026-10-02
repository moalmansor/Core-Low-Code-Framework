<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Support/Facades/Queue.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Support\Facades\Queue
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-f3bb1dad4efed21d8f44b7077850e18f26cf25eb6375b7b9aa3d973fa1c7c6fb-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Support\\Facades\\Queue',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Support/Facades/Queue.php',
      ),
    ),
    'namespace' => 'Illuminate\\Support\\Facades',
    'name' => 'Illuminate\\Support\\Facades\\Queue',
    'shortName' => 'Queue',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @method static void before(mixed $callback)
 * @method static void after(mixed $callback)
 * @method static void exceptionOccurred(mixed $callback)
 * @method static void looping(mixed $callback)
 * @method static void failing(mixed $callback)
 * @method static void starting(mixed $callback)
 * @method static void stopping(mixed $callback)
 * @method static bool connected(string|null $name = null)
 * @method static \\Illuminate\\Contracts\\Queue\\Queue connection(string|null $name = null)
 * @method static void pause(string $connection, string $queue)
 * @method static void pauseFor(string $connection, string $queue, \\DateTimeInterface|\\DateInterval|int $ttl)
 * @method static void resume(string $connection, string $queue)
 * @method static bool isPaused(string $connection, string $queue)
 * @method static void withoutInterruptionPolling()
 * @method static void extend(string $driver, \\Closure $resolver)
 * @method static void addConnector(string $driver, \\Closure $resolver)
 * @method static string getDefaultDriver()
 * @method static void setDefaultDriver(string $name)
 * @method static string getName(string|null $connection = null)
 * @method static \\Illuminate\\Contracts\\Foundation\\Application getApplication()
 * @method static \\Illuminate\\Queue\\QueueManager setApplication(\\Illuminate\\Contracts\\Foundation\\Application $app)
 * @method static int size(string|null $queue = null)
 * @method static mixed push(string|object $job, mixed $data = \'\', string|null $queue = null)
 * @method static mixed pushOn(string $queue, string|object $job, mixed $data = \'\')
 * @method static mixed pushRaw(string $payload, string|null $queue = null, array $options = [])
 * @method static mixed later(\\DateTimeInterface|\\DateInterval|int $delay, string|object $job, mixed $data = \'\', string|null $queue = null)
 * @method static mixed laterOn(string $queue, \\DateTimeInterface|\\DateInterval|int $delay, string|object $job, mixed $data = \'\')
 * @method static mixed bulk(array $jobs, mixed $data = \'\', string|null $queue = null)
 * @method static \\Illuminate\\Contracts\\Queue\\Job|null pop(string|null $queue = null)
 * @method static string getConnectionName()
 * @method static \\Illuminate\\Contracts\\Queue\\Queue setConnectionName(string $name)
 * @method static int pendingSize(string|null $queue = null)
 * @method static int delayedSize(string|null $queue = null)
 * @method static int reservedSize(string|null $queue = null)
 * @method static int|null creationTimeOfOldestPendingJob(string|null $queue = null)
 * @method static mixed getJobTries(mixed $job)
 * @method static mixed getJobBackoff(mixed $job)
 * @method static mixed getJobExpiration(mixed $job)
 * @method static void createPayloadUsing(callable|null $callback)
 * @method static array getConfig()
 * @method static \\Illuminate\\Queue\\Queue setConfig(array $config)
 * @method static \\Illuminate\\Container\\Container getContainer()
 * @method static void setContainer(\\Illuminate\\Container\\Container $container)
 * @method static \\Illuminate\\Support\\Testing\\Fakes\\QueueFake except(array|string $jobsToBeQueued)
 * @method static void assertPushed(string|\\Closure $job, callable|int|null $callback = null)
 * @method static void assertPushedTimes(string $job, int $times = 1)
 * @method static void assertPushedOn(string $queue, string|\\Closure $job, callable|null $callback = null)
 * @method static void assertPushedWithChain(string $job, array $expectedChain = [], callable|null $callback = null)
 * @method static void assertPushedWithoutChain(string $job, callable|null $callback = null)
 * @method static void assertClosurePushed(callable|int|null $callback = null)
 * @method static void assertClosureNotPushed(callable|null $callback = null)
 * @method static void assertNotPushed(string|\\Closure $job, callable|null $callback = null)
 * @method static void assertCount(int $expectedCount)
 * @method static void assertNothingPushed()
 * @method static \\Illuminate\\Support\\Collection pushed(string $job, callable|null $callback = null)
 * @method static \\Illuminate\\Support\\Collection pushedRaw(null|\\Closure $callback = null)
 * @method static \\Illuminate\\Support\\Collection listenersPushed(string $listenerClass, \\Closure|null $callback = null)
 * @method static bool hasPushed(string $job)
 * @method static bool shouldFakeJob(object $job)
 * @method static array pushedJobs()
 * @method static array rawPushes()
 * @method static \\Illuminate\\Support\\Testing\\Fakes\\QueueFake serializeAndRestore(bool $serializeAndRestore = true)
 * @method static void releaseUniqueJobLocks()
 *
 * @see \\Illuminate\\Queue\\QueueManager
 * @see \\Illuminate\\Queue\\Queue
 * @see \\Illuminate\\Support\\Testing\\Fakes\\QueueFake
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 77,
    'endLine' => 168,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Illuminate\\Support\\Facades\\Facade',
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
      'popUsing' => 
      array (
        'name' => 'popUsing',
        'parameters' => 
        array (
          'workerName' => 
          array (
            'name' => 'workerName',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 86,
            'endLine' => 86,
            'startColumn' => 37,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 86,
            'endLine' => 86,
            'startColumn' => 50,
            'endColumn' => 58,
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
 * Register a callback to be executed to pick jobs.
 *
 * @param  string  $workerName
 * @param  callable  $callback
 * @return void
 */',
        'startLine' => 86,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'aliasName' => NULL,
      ),
      'fake' => 
      array (
        'name' => 'fake',
        'parameters' => 
        array (
          'jobsToFake' => 
          array (
            'name' => 'jobsToFake',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 97,
                'endLine' => 97,
                'startTokenPos' => 74,
                'startFilePos' => 5352,
                'endTokenPos' => 75,
                'endFilePos' => 5353,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 97,
            'endLine' => 97,
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
        'docComment' => '/**
 * Replace the bound instance with a fake.
 *
 * @param  array|string  $jobsToFake
 * @return \\Illuminate\\Support\\Testing\\Fakes\\QueueFake
 */',
        'startLine' => 97,
        'endLine' => 106,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'aliasName' => NULL,
      ),
      'fakeExcept' => 
      array (
        'name' => 'fakeExcept',
        'parameters' => 
        array (
          'jobsToAllow' => 
          array (
            'name' => 'jobsToAllow',
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
            'startColumn' => 39,
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
 * Replace the bound instance with a fake that fakes all jobs except the given jobs.
 *
 * @param  string[]|string  $jobsToAllow
 * @return \\Illuminate\\Support\\Testing\\Fakes\\QueueFake
 */',
        'startLine' => 114,
        'endLine' => 117,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'aliasName' => NULL,
      ),
      'fakeFor' => 
      array (
        'name' => 'fakeFor',
        'parameters' => 
        array (
          'callable' => 
          array (
            'name' => 'callable',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 126,
            'endLine' => 126,
            'startColumn' => 36,
            'endColumn' => 53,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'jobsToFake' => 
          array (
            'name' => 'jobsToFake',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 126,
                'endLine' => 126,
                'startTokenPos' => 223,
                'startFilePos' => 6323,
                'endTokenPos' => 224,
                'endFilePos' => 6324,
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
            'startLine' => 126,
            'endLine' => 126,
            'startColumn' => 56,
            'endColumn' => 77,
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
 * Replace the bound instance with a fake during the given callable\'s execution.
 *
 * @param  callable  $callable
 * @param  array  $jobsToFake
 * @return mixed
 */',
        'startLine' => 126,
        'endLine' => 137,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'aliasName' => NULL,
      ),
      'fakeExceptFor' => 
      array (
        'name' => 'fakeExceptFor',
        'parameters' => 
        array (
          'callable' => 
          array (
            'name' => 'callable',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'callable',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 146,
            'endLine' => 146,
            'startColumn' => 42,
            'endColumn' => 59,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'jobsToAllow' => 
          array (
            'name' => 'jobsToAllow',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 146,
                'endLine' => 146,
                'startTokenPos' => 298,
                'startFilePos' => 6840,
                'endTokenPos' => 299,
                'endFilePos' => 6841,
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
            'startLine' => 146,
            'endLine' => 146,
            'startColumn' => 62,
            'endColumn' => 84,
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
 * Replace the bound instance with a fake during the given callable\'s execution.
 *
 * @param  callable  $callable
 * @param  array  $jobsToAllow
 * @return mixed
 */',
        'startLine' => 146,
        'endLine' => 157,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'aliasName' => NULL,
      ),
      'getFacadeAccessor' => 
      array (
        'name' => 'getFacadeAccessor',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the registered name of the component.
 *
 * @return string
 */',
        'startLine' => 164,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 18,
        'namespace' => 'Illuminate\\Support\\Facades',
        'declaringClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'implementingClassName' => 'Illuminate\\Support\\Facades\\Queue',
        'currentClassName' => 'Illuminate\\Support\\Facades\\Queue',
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