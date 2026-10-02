<?php declare(strict_types = 1);

// ftm-/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/CoreServiceProvider.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      'b89bf8ebae21217fec8932374d194210' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Modules\\Core',
         'uses' => 
        array (
          'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
          'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
          'accesscache' => 'App\\Modules\\Access\\AccessCache',
          'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
          'permission' => 'App\\Modules\\Access\\Models\\Permission',
          'role' => 'App\\Modules\\Access\\Models\\Role',
          'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
          'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
          'translator' => 'App\\Modules\\Core\\I18n\\Translator',
          'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
          'organization' => 'App\\Modules\\Core\\Models\\Organization',
          'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
          'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
          'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
          'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
          'user' => 'App\\Modules\\Identity\\Models\\User',
          'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
          'department' => 'App\\Modules\\Organization\\Models\\Department',
          'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
          'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
          'request' => 'Illuminate\\Http\\Request',
          'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
          'event' => 'Illuminate\\Support\\Facades\\Event',
          'gate' => 'Illuminate\\Support\\Facades\\Gate',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'queue' => 'Illuminate\\Support\\Facades\\Queue',
          'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'horizon' => 'Laravel\\Horizon\\Horizon',
        ),
         'className' => 'App\\Modules\\Core\\CoreServiceProvider',
         'functionName' => NULL,
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => NULL,
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '7b71e9cb74ddab5709df855a1ba749a6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Modules\\Core',
         'uses' => 
        array (
          'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
          'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
          'accesscache' => 'App\\Modules\\Access\\AccessCache',
          'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
          'permission' => 'App\\Modules\\Access\\Models\\Permission',
          'role' => 'App\\Modules\\Access\\Models\\Role',
          'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
          'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
          'translator' => 'App\\Modules\\Core\\I18n\\Translator',
          'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
          'organization' => 'App\\Modules\\Core\\Models\\Organization',
          'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
          'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
          'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
          'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
          'user' => 'App\\Modules\\Identity\\Models\\User',
          'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
          'department' => 'App\\Modules\\Organization\\Models\\Department',
          'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
          'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
          'request' => 'Illuminate\\Http\\Request',
          'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
          'event' => 'Illuminate\\Support\\Facades\\Event',
          'gate' => 'Illuminate\\Support\\Facades\\Gate',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'queue' => 'Illuminate\\Support\\Facades\\Queue',
          'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'horizon' => 'Laravel\\Horizon\\Horizon',
        ),
         'className' => 'App\\Modules\\Core\\CoreServiceProvider',
         'functionName' => 'register',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Modules\\Core',
           'uses' => 
          array (
            'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
            'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
            'accesscache' => 'App\\Modules\\Access\\AccessCache',
            'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
            'permission' => 'App\\Modules\\Access\\Models\\Permission',
            'role' => 'App\\Modules\\Access\\Models\\Role',
            'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
            'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
            'translator' => 'App\\Modules\\Core\\I18n\\Translator',
            'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
            'organization' => 'App\\Modules\\Core\\Models\\Organization',
            'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
            'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
            'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
            'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
            'user' => 'App\\Modules\\Identity\\Models\\User',
            'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
            'department' => 'App\\Modules\\Organization\\Models\\Department',
            'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
            'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
            'request' => 'Illuminate\\Http\\Request',
            'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
            'event' => 'Illuminate\\Support\\Facades\\Event',
            'gate' => 'Illuminate\\Support\\Facades\\Gate',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'queue' => 'Illuminate\\Support\\Facades\\Queue',
            'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
            'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            'horizon' => 'Laravel\\Horizon\\Horizon',
          ),
           'className' => 'App\\Modules\\Core\\CoreServiceProvider',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'b9168a90a35acd34051753217258397d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Modules\\Core',
         'uses' => 
        array (
          'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
          'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
          'accesscache' => 'App\\Modules\\Access\\AccessCache',
          'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
          'permission' => 'App\\Modules\\Access\\Models\\Permission',
          'role' => 'App\\Modules\\Access\\Models\\Role',
          'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
          'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
          'translator' => 'App\\Modules\\Core\\I18n\\Translator',
          'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
          'organization' => 'App\\Modules\\Core\\Models\\Organization',
          'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
          'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
          'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
          'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
          'user' => 'App\\Modules\\Identity\\Models\\User',
          'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
          'department' => 'App\\Modules\\Organization\\Models\\Department',
          'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
          'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
          'request' => 'Illuminate\\Http\\Request',
          'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
          'event' => 'Illuminate\\Support\\Facades\\Event',
          'gate' => 'Illuminate\\Support\\Facades\\Gate',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'queue' => 'Illuminate\\Support\\Facades\\Queue',
          'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'horizon' => 'Laravel\\Horizon\\Horizon',
        ),
         'className' => 'App\\Modules\\Core\\CoreServiceProvider',
         'functionName' => 'boot',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Modules\\Core',
           'uses' => 
          array (
            'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
            'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
            'accesscache' => 'App\\Modules\\Access\\AccessCache',
            'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
            'permission' => 'App\\Modules\\Access\\Models\\Permission',
            'role' => 'App\\Modules\\Access\\Models\\Role',
            'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
            'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
            'translator' => 'App\\Modules\\Core\\I18n\\Translator',
            'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
            'organization' => 'App\\Modules\\Core\\Models\\Organization',
            'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
            'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
            'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
            'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
            'user' => 'App\\Modules\\Identity\\Models\\User',
            'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
            'department' => 'App\\Modules\\Organization\\Models\\Department',
            'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
            'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
            'request' => 'Illuminate\\Http\\Request',
            'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
            'event' => 'Illuminate\\Support\\Facades\\Event',
            'gate' => 'Illuminate\\Support\\Facades\\Gate',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'queue' => 'Illuminate\\Support\\Facades\\Queue',
            'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
            'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            'horizon' => 'Laravel\\Horizon\\Horizon',
          ),
           'className' => 'App\\Modules\\Core\\CoreServiceProvider',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      'd25198656323c4c0fce853609a6a6f20' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Modules\\Core',
         'uses' => 
        array (
          'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
          'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
          'accesscache' => 'App\\Modules\\Access\\AccessCache',
          'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
          'permission' => 'App\\Modules\\Access\\Models\\Permission',
          'role' => 'App\\Modules\\Access\\Models\\Role',
          'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
          'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
          'translator' => 'App\\Modules\\Core\\I18n\\Translator',
          'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
          'organization' => 'App\\Modules\\Core\\Models\\Organization',
          'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
          'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
          'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
          'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
          'user' => 'App\\Modules\\Identity\\Models\\User',
          'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
          'department' => 'App\\Modules\\Organization\\Models\\Department',
          'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
          'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
          'request' => 'Illuminate\\Http\\Request',
          'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
          'event' => 'Illuminate\\Support\\Facades\\Event',
          'gate' => 'Illuminate\\Support\\Facades\\Gate',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'queue' => 'Illuminate\\Support\\Facades\\Queue',
          'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'horizon' => 'Laravel\\Horizon\\Horizon',
        ),
         'className' => 'App\\Modules\\Core\\CoreServiceProvider',
         'functionName' => 'registerRateLimiters',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Modules\\Core',
           'uses' => 
          array (
            'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
            'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
            'accesscache' => 'App\\Modules\\Access\\AccessCache',
            'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
            'permission' => 'App\\Modules\\Access\\Models\\Permission',
            'role' => 'App\\Modules\\Access\\Models\\Role',
            'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
            'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
            'translator' => 'App\\Modules\\Core\\I18n\\Translator',
            'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
            'organization' => 'App\\Modules\\Core\\Models\\Organization',
            'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
            'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
            'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
            'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
            'user' => 'App\\Modules\\Identity\\Models\\User',
            'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
            'department' => 'App\\Modules\\Organization\\Models\\Department',
            'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
            'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
            'request' => 'Illuminate\\Http\\Request',
            'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
            'event' => 'Illuminate\\Support\\Facades\\Event',
            'gate' => 'Illuminate\\Support\\Facades\\Gate',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'queue' => 'Illuminate\\Support\\Facades\\Queue',
            'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
            'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            'horizon' => 'Laravel\\Horizon\\Horizon',
          ),
           'className' => 'App\\Modules\\Core\\CoreServiceProvider',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
      '6de06787302a2f817ec3632d90c04b1f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'App\\Modules\\Core',
         'uses' => 
        array (
          'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
          'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
          'accesscache' => 'App\\Modules\\Access\\AccessCache',
          'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
          'permission' => 'App\\Modules\\Access\\Models\\Permission',
          'role' => 'App\\Modules\\Access\\Models\\Role',
          'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
          'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
          'translator' => 'App\\Modules\\Core\\I18n\\Translator',
          'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
          'organization' => 'App\\Modules\\Core\\Models\\Organization',
          'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
          'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
          'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
          'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
          'user' => 'App\\Modules\\Identity\\Models\\User',
          'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
          'department' => 'App\\Modules\\Organization\\Models\\Department',
          'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
          'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
          'request' => 'Illuminate\\Http\\Request',
          'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
          'event' => 'Illuminate\\Support\\Facades\\Event',
          'gate' => 'Illuminate\\Support\\Facades\\Gate',
          'mail' => 'Illuminate\\Support\\Facades\\Mail',
          'queue' => 'Illuminate\\Support\\Facades\\Queue',
          'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
          'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
          'horizon' => 'Laravel\\Horizon\\Horizon',
        ),
         'className' => 'App\\Modules\\Core\\CoreServiceProvider',
         'functionName' => 'registerTranslatables',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'App\\Modules\\Core',
           'uses' => 
          array (
            'dnshostresolver' => 'App\\Infrastructure\\Egress\\DnsHostResolver',
            'hostresolver' => 'App\\Infrastructure\\Egress\\HostResolver',
            'accesscache' => 'App\\Modules\\Access\\AccessCache',
            'accessresolver' => 'App\\Modules\\Access\\AccessResolver',
            'permission' => 'App\\Modules\\Access\\Models\\Permission',
            'role' => 'App\\Modules\\Access\\Models\\Role',
            'correlationid' => 'App\\Modules\\Core\\Correlation\\CorrelationId',
            'translatableregistry' => 'App\\Modules\\Core\\I18n\\TranslatableRegistry',
            'translator' => 'App\\Modules\\Core\\I18n\\Translator',
            'settingssmtptransport' => 'App\\Modules\\Core\\Mail\\SettingsSmtpTransport',
            'organization' => 'App\\Modules\\Core\\Models\\Organization',
            'outboxrelay' => 'App\\Modules\\Core\\Outbox\\OutboxRelay',
            'settingsregistry' => 'App\\Modules\\Core\\Settings\\SettingsRegistry',
            'settingsservice' => 'App\\Modules\\Core\\Settings\\SettingsService',
            'tenantcontext' => 'App\\Modules\\Core\\Tenancy\\TenantContext',
            'user' => 'App\\Modules\\Identity\\Models\\User',
            'errorreporter' => 'App\\Modules\\Monitoring\\ErrorReporter',
            'department' => 'App\\Modules\\Organization\\Models\\Department',
            'limit' => 'Illuminate\\Cache\\RateLimiting\\Limit',
            'authenticatable' => 'Illuminate\\Contracts\\Auth\\Authenticatable',
            'request' => 'Illuminate\\Http\\Request',
            'jobprocessing' => 'Illuminate\\Queue\\Events\\JobProcessing',
            'event' => 'Illuminate\\Support\\Facades\\Event',
            'gate' => 'Illuminate\\Support\\Facades\\Gate',
            'mail' => 'Illuminate\\Support\\Facades\\Mail',
            'queue' => 'Illuminate\\Support\\Facades\\Queue',
            'ratelimiter' => 'Illuminate\\Support\\Facades\\RateLimiter',
            'serviceprovider' => 'Illuminate\\Support\\ServiceProvider',
            'horizon' => 'Laravel\\Horizon\\Horizon',
          ),
           'className' => 'App\\Modules\\Core\\CoreServiceProvider',
           'functionName' => NULL,
           'templatePhpDocNodes' => 
          array (
          ),
           'parent' => NULL,
           'typeAliasesMap' => 
          array (
          ),
           'bypassTypeAliases' => false,
           'constUses' => 
          array (
          ),
           'typeAliasClassName' => NULL,
           'traitData' => NULL,
        )),
         'typeAliasesMap' => 
        array (
        ),
         'bypassTypeAliases' => false,
         'constUses' => 
        array (
        ),
         'typeAliasClassName' => NULL,
         'traitData' => NULL,
      )),
    ),
    1 => 
    array (
      '/home/user/Core-Low-Code-Framework/backend/app/Modules/Core/CoreServiceProvider.php' => 'cac087629853196b4f145bf9363d71c083606c7bacba982e07bdb4d2534c5763',
    ),
  ),
));