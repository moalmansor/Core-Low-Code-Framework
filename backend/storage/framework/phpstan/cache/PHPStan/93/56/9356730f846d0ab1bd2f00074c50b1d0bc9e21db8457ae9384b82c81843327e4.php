<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Records/Models/StoredFile.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Records\Models\StoredFile
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-ac2bfce42272940d14222dec7e13cb2bf132c2bd513586b64f5870213827f693',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Records\\Models\\StoredFile',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Records/Models/StoredFile.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Records\\Models',
    'name' => 'App\\Modules\\Records\\Models\\StoredFile',
    'shortName' => 'StoredFile',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * @property int $id
 * @property string $uuid
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property string $extension
 * @property int $size_bytes
 * @property string $sha256
 * @property string $scan_status
 * @property string|null $owner_type
 * @property int|null $owner_id
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 25,
    'endLine' => 37,
    'startColumn' => 1,
    'endColumn' => 1,
    'parentClassName' => 'App\\Support\\Models\\BaseModel',
    'implementsClassNames' => 
    array (
    ),
    'traitClassNames' => 
    array (
      0 => 'App\\Support\\Models\\BelongsToOrganization',
      1 => 'App\\Support\\Models\\HasStableUuid',
    ),
    'immediateConstants' => 
    array (
    ),
    'immediateProperties' => 
    array (
      'table' => 
      array (
        'declaringClassName' => 'App\\Modules\\Records\\Models\\StoredFile',
        'implementingClassName' => 'App\\Modules\\Records\\Models\\StoredFile',
        'name' => 'table',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '\'files\'',
          'attributes' => 
          array (
            'startLine' => 29,
            'endLine' => 29,
            'startTokenPos' => 58,
            'startFilePos' => 667,
            'endTokenPos' => 58,
            'endFilePos' => 673,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 29,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 31,
        'isPromoted' => false,
        'declaredAtCompileTime' => true,
        'immediateVirtual' => false,
        'immediateHooks' => 
        array (
        ),
      ),
      'fillable' => 
      array (
        'declaringClassName' => 'App\\Modules\\Records\\Models\\StoredFile',
        'implementingClassName' => 'App\\Modules\\Records\\Models\\StoredFile',
        'name' => 'fillable',
        'modifiers' => 2,
        'type' => NULL,
        'default' => 
        array (
          'code' => '[\'disk\', \'path\', \'original_name\', \'mime_type\', \'extension\', \'size_bytes\', \'sha256\', \'scan_status\', \'scanned_at\', \'width\', \'height\', \'is_encrypted\', \'is_temporary\', \'owner_type\', \'owner_id\', \'uploaded_by\', \'deleted_at\']',
          'attributes' => 
          array (
            'startLine' => 31,
            'endLine' => 31,
            'startTokenPos' => 67,
            'startFilePos' => 703,
            'endTokenPos' => 117,
            'endFilePos' => 920,
          ),
        ),
        'docComment' => NULL,
        'attributes' => 
        array (
        ),
        'startLine' => 31,
        'endLine' => 31,
        'startColumn' => 5,
        'endColumn' => 245,
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
        'startLine' => 33,
        'endLine' => 36,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 2,
        'namespace' => 'App\\Modules\\Records\\Models',
        'declaringClassName' => 'App\\Modules\\Records\\Models\\StoredFile',
        'implementingClassName' => 'App\\Modules\\Records\\Models\\StoredFile',
        'currentClassName' => 'App\\Modules\\Records\\Models\\StoredFile',
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