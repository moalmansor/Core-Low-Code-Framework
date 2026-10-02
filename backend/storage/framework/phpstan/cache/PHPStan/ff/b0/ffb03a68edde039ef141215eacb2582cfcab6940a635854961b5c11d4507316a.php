<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Mail/Mailer.php-PHPStan\BetterReflection\Reflection\ReflectionClass-Illuminate\Mail\Mailer
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-d72907e884d4dfad5d09332ef9b9b8e2c9dab4b07aab84ba11b9bdea5d8a9d90-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'Illuminate\\Mail\\Mailer',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../laravel/framework/src/Illuminate/Mail/Mailer.php',
      ),
    ),
    'namespace' => 'Illuminate\\Mail',
    'name' => 'Illuminate\\Mail\\Mailer',
    'shortName' => 'Mailer',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => NULL,
    'attributes' => 
    array (
    ),
    'startLine' => 24,
    'endLine' => 665,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'Illuminate\\Contracts\\Mail\\Mailer',
      1 => 'Illuminate\\Contracts\\Mail\\MailQueue',
    ),
    'traitClassNames' => 
    array (
      0 => 'Illuminate\\Support\\Traits\\Macroable',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'name' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'name',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The name that is configured for the mailer.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 33,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'views' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'views',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The view factory instance.
 *
 * @var \\Illuminate\\Contracts\\View\\Factory
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 40,
        'endLine' => 40,
        'startColumn' => 5,
        'endColumn' => 21,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'transport' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'transport',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The Symfony Transport instance.
 *
 * @var \\Symfony\\Component\\Mailer\\Transport\\TransportInterface
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 47,
        'endLine' => 47,
        'startColumn' => 5,
        'endColumn' => 25,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'events' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'events',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The event dispatcher instance.
 *
 * @var \\Illuminate\\Contracts\\Events\\Dispatcher|null
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 54,
        'endLine' => 54,
        'startColumn' => 5,
        'endColumn' => 22,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'from' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'from',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The global from address and name.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 61,
        'endLine' => 61,
        'startColumn' => 5,
        'endColumn' => 20,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'replyTo' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'replyTo',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The global reply-to address and name.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 68,
        'endLine' => 68,
        'startColumn' => 5,
        'endColumn' => 23,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'returnPath' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'returnPath',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The global return path address.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 75,
        'endLine' => 75,
        'startColumn' => 5,
        'endColumn' => 26,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'to' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'to',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The global to address and name.
 *
 * @var array
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 82,
        'endLine' => 82,
        'startColumn' => 5,
        'endColumn' => 18,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'queue' => 
      array (
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'name' => 'queue',
        'modifiers' => 2,
        'type' => NULL,
        'default' => NULL,
        'docComment' => '/**
 * The queue factory implementation.
 *
 * @var \\Illuminate\\Contracts\\Queue\\Factory
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 89,
        'endLine' => 89,
        'startColumn' => 5,
        'endColumn' => 21,
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
          'name' => 
          array (
            'name' => 'name',
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
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 33,
            'endColumn' => 44,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'views' => 
          array (
            'name' => 'views',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\View\\Factory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 47,
            'endColumn' => 60,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'transport' => 
          array (
            'name' => 'transport',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\Mailer\\Transport\\TransportInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 63,
            'endColumn' => 91,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'events' => 
          array (
            'name' => 'events',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 99,
                'endLine' => 99,
                'startTokenPos' => 224,
                'startFilePos' => 2405,
                'endTokenPos' => 224,
                'endFilePos' => 2408,
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
                      'name' => 'Illuminate\\Contracts\\Events\\Dispatcher',
                      'isIdentifier' => false,
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
            'startLine' => 99,
            'endLine' => 99,
            'startColumn' => 94,
            'endColumn' => 119,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new Mailer instance.
 *
 * @param  string  $name
 * @param  \\Illuminate\\Contracts\\View\\Factory  $views
 * @param  \\Symfony\\Component\\Mailer\\Transport\\TransportInterface  $transport
 * @param  \\Illuminate\\Contracts\\Events\\Dispatcher|null  $events
 */',
        'startLine' => 99,
        'endLine' => 105,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'alwaysFrom' => 
      array (
        'name' => 'alwaysFrom',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
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
            'startColumn' => 32,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 114,
                'endLine' => 114,
                'startTokenPos' => 282,
                'startFilePos' => 2759,
                'endTokenPos' => 282,
                'endFilePos' => 2762,
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
            'startColumn' => 42,
            'endColumn' => 53,
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
 * Set the global from address and name.
 *
 * @param  string  $address
 * @param  string|null  $name
 * @return void
 */',
        'startLine' => 114,
        'endLine' => 117,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'alwaysReplyTo' => 
      array (
        'name' => 'alwaysReplyTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 126,
            'endLine' => 126,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 126,
                'endLine' => 126,
                'startTokenPos' => 319,
                'startFilePos' => 3038,
                'endTokenPos' => 319,
                'endFilePos' => 3041,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 126,
            'endLine' => 126,
            'startColumn' => 45,
            'endColumn' => 56,
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
 * Set the global reply-to address and name.
 *
 * @param  string  $address
 * @param  string|null  $name
 * @return void
 */',
        'startLine' => 126,
        'endLine' => 129,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'alwaysReturnPath' => 
      array (
        'name' => 'alwaysReturnPath',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 137,
            'endLine' => 137,
            'startColumn' => 38,
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
 * Set the global return path address.
 *
 * @param  string  $address
 * @return void
 */',
        'startLine' => 137,
        'endLine' => 140,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'alwaysTo' => 
      array (
        'name' => 'alwaysTo',
        'parameters' => 
        array (
          'address' => 
          array (
            'name' => 'address',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 30,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 149,
                'endLine' => 149,
                'startTokenPos' => 383,
                'startFilePos' => 3535,
                'endTokenPos' => 383,
                'endFilePos' => 3538,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 149,
            'endLine' => 149,
            'startColumn' => 40,
            'endColumn' => 51,
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
 * Set the global to address and name.
 *
 * @param  string  $address
 * @param  string|null  $name
 * @return void
 */',
        'startLine' => 149,
        'endLine' => 152,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'to' => 
      array (
        'name' => 'to',
        'parameters' => 
        array (
          'users' => 
          array (
            'name' => 'users',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 161,
            'endLine' => 161,
            'startColumn' => 24,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 161,
                'endLine' => 161,
                'startTokenPos' => 420,
                'startFilePos' => 3834,
                'endTokenPos' => 420,
                'endFilePos' => 3837,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 161,
            'endLine' => 161,
            'startColumn' => 32,
            'endColumn' => 43,
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
 * Begin the process of mailing a mailable class instance.
 *
 * @param  mixed  $users
 * @param  string|null  $name
 * @return \\Illuminate\\Mail\\PendingMail
 */',
        'startLine' => 161,
        'endLine' => 168,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'cc' => 
      array (
        'name' => 'cc',
        'parameters' => 
        array (
          'users' => 
          array (
            'name' => 'users',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 177,
            'endLine' => 177,
            'startColumn' => 24,
            'endColumn' => 29,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 177,
                'endLine' => 177,
                'startTokenPos' => 496,
                'startFilePos' => 4251,
                'endTokenPos' => 496,
                'endFilePos' => 4254,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 177,
            'endLine' => 177,
            'startColumn' => 32,
            'endColumn' => 43,
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
 * Begin the process of mailing a mailable class instance.
 *
 * @param  mixed  $users
 * @param  string|null  $name
 * @return \\Illuminate\\Mail\\PendingMail
 */',
        'startLine' => 177,
        'endLine' => 184,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'bcc' => 
      array (
        'name' => 'bcc',
        'parameters' => 
        array (
          'users' => 
          array (
            'name' => 'users',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 193,
            'endLine' => 193,
            'startColumn' => 25,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'name' => 
          array (
            'name' => 'name',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 193,
                'endLine' => 193,
                'startTokenPos' => 572,
                'startFilePos' => 4669,
                'endTokenPos' => 572,
                'endFilePos' => 4672,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 193,
            'endLine' => 193,
            'startColumn' => 33,
            'endColumn' => 44,
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
 * Begin the process of mailing a mailable class instance.
 *
 * @param  mixed  $users
 * @param  string|null  $name
 * @return \\Illuminate\\Mail\\PendingMail
 */',
        'startLine' => 193,
        'endLine' => 200,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'html' => 
      array (
        'name' => 'html',
        'parameters' => 
        array (
          'html' => 
          array (
            'name' => 'html',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 26,
            'endColumn' => 30,
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
            'startLine' => 209,
            'endLine' => 209,
            'startColumn' => 33,
            'endColumn' => 41,
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
 * Send a new message with only an HTML part.
 *
 * @param  string  $html
 * @param  mixed  $callback
 * @return \\Illuminate\\Mail\\SentMessage|null
 */',
        'startLine' => 209,
        'endLine' => 212,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'raw' => 
      array (
        'name' => 'raw',
        'parameters' => 
        array (
          'text' => 
          array (
            'name' => 'text',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 221,
            'endLine' => 221,
            'startColumn' => 25,
            'endColumn' => 29,
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
            'startLine' => 221,
            'endLine' => 221,
            'startColumn' => 32,
            'endColumn' => 40,
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
 * Send a new message with only a raw text part.
 *
 * @param  string  $text
 * @param  mixed  $callback
 * @return \\Illuminate\\Mail\\SentMessage|null
 */',
        'startLine' => 221,
        'endLine' => 224,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'plain' => 
      array (
        'name' => 'plain',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 234,
            'endLine' => 234,
            'startColumn' => 27,
            'endColumn' => 31,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
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
            'startLine' => 234,
            'endLine' => 234,
            'startColumn' => 34,
            'endColumn' => 44,
            'parameterIndex' => 1,
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
            'startLine' => 234,
            'endLine' => 234,
            'startColumn' => 47,
            'endColumn' => 55,
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
 * Send a new message with only a plain part.
 *
 * @param  string  $view
 * @param  array  $data
 * @param  mixed  $callback
 * @return \\Illuminate\\Mail\\SentMessage|null
 */',
        'startLine' => 234,
        'endLine' => 237,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'render' => 
      array (
        'name' => 'render',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 246,
            'endLine' => 246,
            'startColumn' => 28,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 246,
                'endLine' => 246,
                'startTokenPos' => 782,
                'startFilePos' => 6019,
                'endTokenPos' => 783,
                'endFilePos' => 6020,
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
            'startLine' => 246,
            'endLine' => 246,
            'startColumn' => 35,
            'endColumn' => 50,
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
 * Render the given message as a view.
 *
 * @param  string|array  $view
 * @param  array  $data
 * @return string
 */',
        'startLine' => 246,
        'endLine' => 259,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'replaceEmbeddedAttachments' => 
      array (
        'name' => 'replaceEmbeddedAttachments',
        'parameters' => 
        array (
          'renderedView' => 
          array (
            'name' => 'renderedView',
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
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 51,
            'endColumn' => 70,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attachments' => 
          array (
            'name' => 'attachments',
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
            'startLine' => 268,
            'endLine' => 268,
            'startColumn' => 73,
            'endColumn' => 90,
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
 * Replace the embedded image attachments with raw, inline image data for browser rendering.
 *
 * @param  string  $renderedView
 * @param  array  $attachments
 * @return string
 */',
        'startLine' => 268,
        'endLine' => 287,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'send' => 
      array (
        'name' => 'send',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 297,
            'endLine' => 297,
            'startColumn' => 26,
            'endColumn' => 30,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 297,
                'endLine' => 297,
                'startTokenPos' => 1030,
                'startFilePos' => 7922,
                'endTokenPos' => 1031,
                'endFilePos' => 7923,
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
            'startLine' => 297,
            'endLine' => 297,
            'startColumn' => 33,
            'endColumn' => 48,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 297,
                'endLine' => 297,
                'startTokenPos' => 1038,
                'startFilePos' => 7938,
                'endTokenPos' => 1038,
                'endFilePos' => 7941,
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
            'startColumn' => 51,
            'endColumn' => 66,
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
 * Send a new message using a view.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable|string|array  $view
 * @param  array  $data
 * @param  \\Closure|string|null  $callback
 * @return \\Illuminate\\Mail\\SentMessage|null
 */',
        'startLine' => 297,
        'endLine' => 341,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'sendMailable' => 
      array (
        'name' => 'sendMailable',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Mail\\Mailable',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 349,
            'endLine' => 349,
            'startColumn' => 37,
            'endColumn' => 62,
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
 * Send the given mailable.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $mailable
 * @return \\Illuminate\\Mail\\SentMessage|null
 */',
        'startLine' => 349,
        'endLine' => 354,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'sendNow' => 
      array (
        'name' => 'sendNow',
        'parameters' => 
        array (
          'mailable' => 
          array (
            'name' => 'mailable',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 364,
            'endLine' => 364,
            'startColumn' => 29,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 364,
                'endLine' => 364,
                'startTokenPos' => 1360,
                'startFilePos' => 10423,
                'endTokenPos' => 1361,
                'endFilePos' => 10424,
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
            'startLine' => 364,
            'endLine' => 364,
            'startColumn' => 40,
            'endColumn' => 55,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'callback' => 
          array (
            'name' => 'callback',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 364,
                'endLine' => 364,
                'startTokenPos' => 1368,
                'startFilePos' => 10439,
                'endTokenPos' => 1368,
                'endFilePos' => 10442,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 364,
            'endLine' => 364,
            'startColumn' => 58,
            'endColumn' => 73,
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
 * Send a new message synchronously using a view.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable|string|array  $mailable
 * @param  array  $data
 * @param  \\Closure|string|null  $callback
 * @return \\Illuminate\\Mail\\SentMessage|null
 */',
        'startLine' => 364,
        'endLine' => 369,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'parseView' => 
      array (
        'name' => 'parseView',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 379,
            'endLine' => 379,
            'startColumn' => 34,
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
 * Parse the given view name or array.
 *
 * @param  \\Closure|array|string  $view
 * @return array
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 379,
        'endLine' => 404,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'addContent' => 
      array (
        'name' => 'addContent',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 35,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 45,
            'endColumn' => 49,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'plain' => 
          array (
            'name' => 'plain',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 52,
            'endColumn' => 57,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'raw' => 
          array (
            'name' => 'raw',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 60,
            'endColumn' => 63,
            'parameterIndex' => 3,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 66,
            'endColumn' => 70,
            'parameterIndex' => 4,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Add the content to a given message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @param  string|null  $view
 * @param  string|null  $plain
 * @param  string|null  $raw
 * @param  array  $data
 * @return void
 */',
        'startLine' => 416,
        'endLine' => 429,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'renderView' => 
      array (
        'name' => 'renderView',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 438,
            'endLine' => 438,
            'startColumn' => 35,
            'endColumn' => 39,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 438,
            'endLine' => 438,
            'startColumn' => 42,
            'endColumn' => 46,
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
 * Render the given view.
 *
 * @param  \\Closure|string  $view
 * @param  array  $data
 * @return string
 */',
        'startLine' => 438,
        'endLine' => 445,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'setGlobalToAndRemoveCcAndBcc' => 
      array (
        'name' => 'setGlobalToAndRemoveCcAndBcc',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 453,
            'endLine' => 453,
            'startColumn' => 53,
            'endColumn' => 60,
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
 * Set the global "to" address on the given message.
 *
 * @param  \\Illuminate\\Mail\\Message  $message
 * @return void
 */',
        'startLine' => 453,
        'endLine' => 461,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'queue' => 
      array (
        'name' => 'queue',
        'parameters' => 
        array (
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 472,
            'endLine' => 472,
            'startColumn' => 27,
            'endColumn' => 31,
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
                'startLine' => 472,
                'endLine' => 472,
                'startTokenPos' => 1835,
                'startFilePos' => 13503,
                'endTokenPos' => 1835,
                'endFilePos' => 13506,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 472,
            'endLine' => 472,
            'startColumn' => 34,
            'endColumn' => 46,
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
 * Queue a new mail message for sending.
 *
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @param  \\BackedEnum|string|null  $queue
 * @return mixed
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 472,
        'endLine' => 483,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'onQueue' => 
      array (
        'name' => 'onQueue',
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
            'startLine' => 492,
            'endLine' => 492,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 492,
            'endLine' => 492,
            'startColumn' => 37,
            'endColumn' => 41,
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
 * Queue a new mail message for sending on the given queue.
 *
 * @param  \\BackedEnum|string|null  $queue
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @return mixed
 */',
        'startLine' => 492,
        'endLine' => 495,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'queueOn' => 
      array (
        'name' => 'queueOn',
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
            'startLine' => 506,
            'endLine' => 506,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 506,
            'endLine' => 506,
            'startColumn' => 37,
            'endColumn' => 41,
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
 * Queue a new mail message for sending on the given queue.
 *
 * This method didn\'t match rest of framework\'s "onQueue" phrasing. Added "onQueue".
 *
 * @param  string  $queue
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @return mixed
 */',
        'startLine' => 506,
        'endLine' => 509,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
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
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 27,
            'endColumn' => 32,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 35,
            'endColumn' => 39,
            'parameterIndex' => 1,
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
                'startLine' => 521,
                'endLine' => 521,
                'startTokenPos' => 1988,
                'startFilePos' => 14874,
                'endTokenPos' => 1988,
                'endFilePos' => 14877,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 521,
            'endLine' => 521,
            'startColumn' => 42,
            'endColumn' => 54,
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
 * Queue a new mail message for sending after (n) seconds.
 *
 * @param  \\DateTimeInterface|\\DateInterval|int  $delay
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @param  string|null  $queue
 * @return mixed
 *
 * @throws \\InvalidArgumentException
 */',
        'startLine' => 521,
        'endLine' => 530,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'laterOn' => 
      array (
        'name' => 'laterOn',
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
            'startLine' => 540,
            'endLine' => 540,
            'startColumn' => 29,
            'endColumn' => 34,
            'parameterIndex' => 0,
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
            'startLine' => 540,
            'endLine' => 540,
            'startColumn' => 37,
            'endColumn' => 42,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'view' => 
          array (
            'name' => 'view',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 540,
            'endLine' => 540,
            'startColumn' => 45,
            'endColumn' => 49,
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
 * Queue a new mail message for sending after (n) seconds on the given queue.
 *
 * @param  string  $queue
 * @param  \\DateTimeInterface|\\DateInterval|int  $delay
 * @param  \\Illuminate\\Contracts\\Mail\\Mailable  $view
 * @return mixed
 */',
        'startLine' => 540,
        'endLine' => 543,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'createMessage' => 
      array (
        'name' => 'createMessage',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Create a new message instance.
 *
 * @return \\Illuminate\\Mail\\Message
 */',
        'startLine' => 550,
        'endLine' => 573,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'sendSymfonyMessage' => 
      array (
        'name' => 'sendSymfonyMessage',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\Mime\\Email',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 581,
            'endLine' => 581,
            'startColumn' => 43,
            'endColumn' => 56,
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
 * Send a Symfony Email instance.
 *
 * @param  \\Symfony\\Component\\Mime\\Email  $message
 * @return \\Symfony\\Component\\Mailer\\SentMessage|null
 */',
        'startLine' => 581,
        'endLine' => 588,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'shouldSendMessage' => 
      array (
        'name' => 'shouldSendMessage',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 597,
            'endLine' => 597,
            'startColumn' => 42,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 597,
                'endLine' => 597,
                'startTokenPos' => 2321,
                'startFilePos' => 17291,
                'endTokenPos' => 2322,
                'endFilePos' => 17292,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 597,
            'endLine' => 597,
            'startColumn' => 52,
            'endColumn' => 61,
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
 * Determines if the email can be sent.
 *
 * @param  \\Symfony\\Component\\Mime\\Email  $message
 * @param  array  $data
 * @return bool
 */',
        'startLine' => 597,
        'endLine' => 606,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'dispatchSentEvent' => 
      array (
        'name' => 'dispatchSentEvent',
        'parameters' => 
        array (
          'message' => 
          array (
            'name' => 'message',
            'default' => NULL,
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 615,
            'endLine' => 615,
            'startColumn' => 42,
            'endColumn' => 49,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'data' => 
          array (
            'name' => 'data',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 615,
                'endLine' => 615,
                'startTokenPos' => 2389,
                'startFilePos' => 17705,
                'endTokenPos' => 2390,
                'endFilePos' => 17706,
              ),
            ),
            'type' => NULL,
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 615,
            'endLine' => 615,
            'startColumn' => 52,
            'endColumn' => 61,
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
 * Dispatch the message sent event.
 *
 * @param  \\Illuminate\\Mail\\SentMessage  $message
 * @param  array  $data
 * @return void
 */',
        'startLine' => 615,
        'endLine' => 620,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'getSymfonyTransport' => 
      array (
        'name' => 'getSymfonyTransport',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the Symfony Transport instance.
 *
 * @return \\Symfony\\Component\\Mailer\\Transport\\TransportInterface
 */',
        'startLine' => 627,
        'endLine' => 630,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'getViewFactory' => 
      array (
        'name' => 'getViewFactory',
        'parameters' => 
        array (
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the view factory instance.
 *
 * @return \\Illuminate\\Contracts\\View\\Factory
 */',
        'startLine' => 637,
        'endLine' => 640,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'setSymfonyTransport' => 
      array (
        'name' => 'setSymfonyTransport',
        'parameters' => 
        array (
          'transport' => 
          array (
            'name' => 'transport',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Symfony\\Component\\Mailer\\Transport\\TransportInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 648,
            'endLine' => 648,
            'startColumn' => 41,
            'endColumn' => 69,
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
 * Set the Symfony Transport instance.
 *
 * @param  \\Symfony\\Component\\Mailer\\Transport\\TransportInterface  $transport
 * @return void
 */',
        'startLine' => 648,
        'endLine' => 651,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
        'aliasName' => NULL,
      ),
      'setQueue' => 
      array (
        'name' => 'setQueue',
        'parameters' => 
        array (
          'queue' => 
          array (
            'name' => 'queue',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'Illuminate\\Contracts\\Queue\\Factory',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 659,
            'endLine' => 659,
            'startColumn' => 30,
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
 * Set the queue manager instance.
 *
 * @param  \\Illuminate\\Contracts\\Queue\\Factory  $queue
 * @return $this
 */',
        'startLine' => 659,
        'endLine' => 664,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'Illuminate\\Mail',
        'declaringClassName' => 'Illuminate\\Mail\\Mailer',
        'implementingClassName' => 'Illuminate\\Mail\\Mailer',
        'currentClassName' => 'Illuminate\\Mail\\Mailer',
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