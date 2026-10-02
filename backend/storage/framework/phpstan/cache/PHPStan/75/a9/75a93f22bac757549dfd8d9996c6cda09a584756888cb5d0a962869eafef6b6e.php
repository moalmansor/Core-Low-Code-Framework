<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Modules/Admin/ConsoleAreas.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Modules\Admin\ConsoleAreas
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-db3e41c0c44f39fbab8f3ff7e843d3c3bd4d0e3546558dbe274f2af254773a6d',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Modules\\Admin\\ConsoleAreas',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Modules/Admin/ConsoleAreas.php',
      ),
    ),
    'namespace' => 'App\\Modules\\Admin',
    'name' => 'App\\Modules\\Admin\\ConsoleAreas',
    'shortName' => 'ConsoleAreas',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * The Admin Console\'s areas (specification §4.1). Each area is shown only to
 * holders of its permission, and only once the phase that builds it has
 * shipped: later phases add their areas here.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 12,
    'endLine' => 30,
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
      'BUILT' => 
      array (
        'declaringClassName' => 'App\\Modules\\Admin\\ConsoleAreas',
        'implementingClassName' => 'App\\Modules\\Admin\\ConsoleAreas',
        'name' => 'BUILT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[\'system_health\' => [\'overview\', [\'system.view_errors\', \'system.manage_operations\'], \'/admin/health\'], \'users\' => [\'people\', [\'system.manage_users\'], \'/admin/users\'], \'departments\' => [\'people\', [\'system.manage_users\'], \'/admin/departments\'], \'roles_permissions\' => [\'people\', [\'system.manage_permissions\'], \'/admin/roles\'], \'appearance_branding\' => [\'configuration\', [\'system.manage_branding\'], \'/admin/settings/branding\'], \'translations\' => [\'configuration\', [\'system.manage_translations\'], \'/admin/translations\'], \'system_settings\' => [\'configuration\', [\'system.manage_settings\'], \'/admin/settings\'], \'audit_log\' => [\'compliance\', [\'system.view_audit_log\'], \'/admin/audit\'], \'error_monitoring\' => [\'operations\', [\'system.view_errors\'], \'/admin/errors\']]',
          'attributes' => 
          array (
            'startLine' => 19,
            'endLine' => 29,
            'startTokenPos' => 35,
            'startFilePos' => 500,
            'endTokenPos' => 193,
            'endFilePos' => 1334,
          ),
        ),
        'docComment' => '/**
 * key => [section, permission keys (any one grants visibility), client route]
 *
 * @var array<string, array{0: string, 1: list<string>, 2: string}>
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 19,
        'endLine' => 29,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
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