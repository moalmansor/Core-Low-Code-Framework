<?php declare(strict_types = 1);

// odsl-/home/user/Core-Low-Code-Framework/backend/app/Infrastructure/Egress/AddressPolicy.php-PHPStan\BetterReflection\Reflection\ReflectionClass-App\Infrastructure\Egress\AddressPolicy
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-6.73.0.5-8.3.6-a2c55fe7d344da6b5e473c81be9cd7454b913598182a90527f9246516934106b',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/app/Infrastructure/Egress/AddressPolicy.php',
      ),
    ),
    'namespace' => 'App\\Infrastructure\\Egress',
    'name' => 'App\\Infrastructure\\Egress\\AddressPolicy',
    'shortName' => 'AddressPolicy',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 32,
    'docComment' => '/**
 * Blocked address ranges for outbound requests (architecture §7.4,
 * specification §4.15): private, loopback, link-local, shared, documentation,
 * multicast, reserved, and cloud metadata ranges, in IPv4 and IPv6. IPv4-mapped
 * and NAT64 IPv6 addresses are checked as their embedded IPv4 address.
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 82,
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
      'BLOCKED' => 
      array (
        'declaringClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'implementingClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'name' => 'BLOCKED',
        'modifiers' => 4,
        'type' => NULL,
        'value' => 
        array (
          'code' => '[
    \'0.0.0.0/8\',
    \'10.0.0.0/8\',
    \'100.64.0.0/10\',
    \'127.0.0.0/8\',
    \'169.254.0.0/16\',
    \'172.16.0.0/12\',
    \'192.0.0.0/24\',
    \'192.0.2.0/24\',
    \'192.88.99.0/24\',
    \'192.168.0.0/16\',
    \'198.18.0.0/15\',
    \'198.51.100.0/24\',
    \'203.0.113.0/24\',
    \'224.0.0.0/4\',
    \'240.0.0.0/4\',
    \'255.255.255.255/32\',
    \'::/128\',
    \'::1/128\',
    \'64:ff9b:1::/48\',
    \'2002::/16\',
    \'100::/64\',
    \'2001::/23\',
    \'2001:db8::/32\',
    \'fc00::/7\',
    \'fe80::/10\',
    \'fec0::/10\',
    \'ff00::/8\',
    // Cloud metadata endpoints (covered above, listed for clarity)
    \'169.254.169.254/32\',
    \'fd00:ec2::254/128\',
]',
          'attributes' => 
          array (
            'startLine' => 16,
            'endLine' => 25,
            'startTokenPos' => 35,
            'startFilePos' => 466,
            'endTokenPos' => 126,
            'endFilePos' => 1055,
          ),
        ),
        'docComment' => '/** @var list<string> */',
        'attributes' => 
        array (
        ),
        'startLine' => 16,
        'endLine' => 25,
        'startColumn' => 5,
        'endColumn' => 6,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'isBlocked' => 
      array (
        'name' => 'isBlocked',
        'parameters' => 
        array (
          'ip' => 
          array (
            'name' => 'ip',
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
            'startLine' => 27,
            'endLine' => 27,
            'startColumn' => 31,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 27,
        'endLine' => 46,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'App\\Infrastructure\\Egress',
        'declaringClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'implementingClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'currentClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'aliasName' => NULL,
      ),
      'embeddedIpv4' => 
      array (
        'name' => 'embeddedIpv4',
        'parameters' => 
        array (
          'packed' => 
          array (
            'name' => 'packed',
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
            'startLine' => 49,
            'endLine' => 49,
            'startColumn' => 35,
            'endColumn' => 48,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
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
                  'name' => 'string',
                  'isIdentifier' => true,
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
        'attributes' => 
        array (
        ),
        'docComment' => '/** IPv4-mapped (::ffff:a.b.c.d), IPv4-compatible (::a.b.c.d) and NAT64 (64:ff9b::/96). */',
        'startLine' => 49,
        'endLine' => 60,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Infrastructure\\Egress',
        'declaringClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'implementingClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'currentClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'aliasName' => NULL,
      ),
      'inRange' => 
      array (
        'name' => 'inRange',
        'parameters' => 
        array (
          'packed' => 
          array (
            'name' => 'packed',
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
            'startLine' => 62,
            'endLine' => 62,
            'startColumn' => 30,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'cidr' => 
          array (
            'name' => 'cidr',
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
            'startLine' => 62,
            'endLine' => 62,
            'startColumn' => 46,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'bool',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => NULL,
        'startLine' => 62,
        'endLine' => 81,
        'startColumn' => 5,
        'endColumn' => 5,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 4,
        'namespace' => 'App\\Infrastructure\\Egress',
        'declaringClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'implementingClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
        'currentClassName' => 'App\\Infrastructure\\Egress\\AddressPolicy',
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