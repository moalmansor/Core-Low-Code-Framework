<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Access/Models/Permission.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Access\Models\Permission
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-bede22ed972f4942956654f9c5717656ae93ed0d6c461b711bb49ad9c4b906ee',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Access\\Models\\Permission',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Access/Models/Permission.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Access\\Models',
    'name' => 'App\\Modules\\Access\\Models\\Permission',
    'shortName' => 'Permission',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $key
 * @property string $scope_type
 * @property int|null $scope_id
 * @property string $ability
 * @property string $category
 * @property bool $is_system
 * @property bool $is_dangerous
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 21,
    'endLine' => 39,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Models\\BaseModel',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Support\\Models\\BelongsToOrganization',
      1 => 'App\\Modules\\Core\\I18n\\HasTranslations',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'translatable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'name' => 'translatable',
        'modifiers' => 1,
        'type' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'default' => 
        array (
          'code' => '[\'label\', \'description\']',
          'attributes' => 
          array (
            'startLine' => 26,
            'endLine' => 26,
            'startTokenPos' => 62,
            'startFilePos' => 587,
            'endTokenPos' => 67,
            'endFilePos' => 610,
          ),
        ),
        'docComment' => '/** @var list<string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 26,
        'endLine' => 26,
        'startColumn' => 5,
        'endColumn' => 58,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'key\', \'scope_type\', \'scope_id\', \'ability\', \'category\', \'is_system\', \'is_dangerous\']',
          'attributes' => 
          array (
            'startLine' => 28,
            'endLine' => 28,
            'startTokenPos' => 76,
            'startFilePos' => 640,
            'endTokenPos' => 96,
            'endFilePos' => 724,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 28,
        'endLine' => 28,
        'startColumn' => 5,
        'endColumn' => 112,
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
      'casts' => 
      array (
        'name' => 'casts',
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
        ),
        'docComment' => NULL,
        'startLine' => 30,
        'endLine' => 33,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'currentClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'aliasName' => NULL,
      ),
      'translationType' => 
      array (
        'name' => 'translationType',
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
        'startLine' => 35,
        'endLine' => 38,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Modules\\Access\\Models',
        'declaringClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'implementingClassName' => 'App\\Modules\\Access\\Models\\Permission',
        'currentClassName' => 'App\\Modules\\Access\\Models\\Permission',
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