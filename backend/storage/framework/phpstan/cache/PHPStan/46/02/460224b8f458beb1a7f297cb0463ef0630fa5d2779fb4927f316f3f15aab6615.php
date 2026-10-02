<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Actions/DisableTwoFactorWhenAllowed.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Identity\Actions\DisableTwoFactorWhenAllowed
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-cb5a8ed60029bf82090ffb0cb6690ae3db77dde6a209111ea8d22caca5f5f861',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Identity\\Actions\\DisableTwoFactorWhenAllowed',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Identity/Actions/DisableTwoFactorWhenAllowed.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Identity\\Actions',
    'name' => 'App\\Modules\\Identity\\Actions\\DisableTwoFactorWhenAllowed',
    'shortName' => 'DisableTwoFactorWhenAllowed',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Two-factor authentication is mandatory for roles that require it
 * (specification §2, §5): their holders cannot switch it off themselves. An
 * administrator can still reset it (forcing re-enrollment at next sign-in).
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 16,
    'endLine' => 25,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'Laravel\\Fortify\\Actions\\DisableTwoFactorAuthentication',
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
      '__invoke' => 
      array (
        'name' => '__invoke',
        'parameters' => 
        array (
          'user' => 
          array (
            'name' => 'user',
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
            'startColumn' => 30,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 18,
        'endLine' => 24,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => true,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Identity\\Actions',
        'declaringClassName' => 'App\\Modules\\Identity\\Actions\\DisableTwoFactorWhenAllowed',
        'implementingClassName' => 'App\\Modules\\Identity\\Actions\\DisableTwoFactorWhenAllowed',
        'currentClassName' => 'App\\Modules\\Identity\\Actions\\DisableTwoFactorWhenAllowed',
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