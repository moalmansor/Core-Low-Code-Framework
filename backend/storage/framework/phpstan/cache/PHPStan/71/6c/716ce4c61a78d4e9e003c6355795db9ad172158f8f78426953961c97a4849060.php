<?php declare(strict_types = 1);

// osfsl-/home/user/Core-Low-Code-Framework/backend/vendor/composer/../directorytree/ldaprecord/src/LdapInterface.php-PHPStan\BetterReflection\Reflection\ReflectionClass-LdapRecord\LdapInterface
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v2-a9e169b166c78ea92d13e0e455123cf28182c1cc4d538b3bfa44fe13c28a2222-8.3.6-6.73.0.5',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\LocatedSource',
      'data' => 
      array (
        'name' => 'LdapRecord\\LdapInterface',
        'filename' => '/home/user/Core-Low-Code-Framework/backend/vendor/composer/../directorytree/ldaprecord/src/LdapInterface.php',
      ),
    ),
    'namespace' => 'LdapRecord',
    'name' => 'LdapRecord\\LdapInterface',
    'shortName' => 'LdapInterface',
    'isInterface' => true,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * @see https://ldap.com/ldap-oid-reference-guide
 * @see http://msdn.microsoft.com/en-us/library/cc223359.aspx
 * @see https://help.univention.com/t/openldap-debug-level/19301
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 631,
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
      'PROTOCOL' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'PROTOCOL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ldap://\'',
          'attributes' => 
          array (
            'startLine' => 20,
            'endLine' => 20,
            'startTokenPos' => 35,
            'startFilePos' => 392,
            'endTokenPos' => 35,
            'endFilePos' => 400,
          ),
        ),
        'docComment' => '/**
 * The standard LDAP protocol string.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 20,
        'endLine' => 20,
        'startColumn' => 5,
        'endColumn' => 38,
      ),
      'PROTOCOL_TLS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'PROTOCOL_TLS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'ldaps://\'',
          'attributes' => 
          array (
            'startLine' => 27,
            'endLine' => 27,
            'startTokenPos' => 48,
            'startFilePos' => 515,
            'endTokenPos' => 48,
            'endFilePos' => 524,
          ),
        ),
        'docComment' => '/**
 * The TLS LDAP protocol string.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 27,
        'endLine' => 27,
        'startColumn' => 5,
        'endColumn' => 43,
      ),
      'PORT' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'PORT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '389',
          'attributes' => 
          array (
            'startLine' => 34,
            'endLine' => 34,
            'startTokenPos' => 61,
            'startFilePos' => 629,
            'endTokenPos' => 61,
            'endFilePos' => 631,
          ),
        ),
        'docComment' => '/**
 * The standard LDAP port number.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 34,
        'endLine' => 34,
        'startColumn' => 5,
        'endColumn' => 28,
      ),
      'PORT_TLS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'PORT_TLS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '636',
          'attributes' => 
          array (
            'startLine' => 41,
            'endLine' => 41,
            'startTokenPos' => 74,
            'startFilePos' => 735,
            'endTokenPos' => 74,
            'endFilePos' => 737,
          ),
        ),
        'docComment' => '/**
 * The LDAP SSL port number.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 41,
        'endLine' => 41,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'DEBUG_TRACE' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_TRACE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1',
          'attributes' => 
          array (
            'startLine' => 48,
            'endLine' => 48,
            'startTokenPos' => 87,
            'startFilePos' => 854,
            'endTokenPos' => 87,
            'endFilePos' => 854,
          ),
        ),
        'docComment' => '/**
 * Print entry and exit from routines.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 48,
        'endLine' => 48,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'DEBUG_PACKETS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_PACKETS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2',
          'attributes' => 
          array (
            'startLine' => 55,
            'endLine' => 55,
            'startTokenPos' => 100,
            'startFilePos' => 960,
            'endTokenPos' => 100,
            'endFilePos' => 960,
          ),
        ),
        'docComment' => '/**
 * Print packet activity.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 55,
        'endLine' => 55,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'DEBUG_ARGS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_ARGS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '4',
          'attributes' => 
          array (
            'startLine' => 62,
            'endLine' => 62,
            'startTokenPos' => 113,
            'startFilePos' => 1076,
            'endTokenPos' => 113,
            'endFilePos' => 1076,
          ),
        ),
        'docComment' => '/**
 * Print data arguments from requests.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 62,
        'endLine' => 62,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'DEBUG_CONNS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_CONNS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '8',
          'attributes' => 
          array (
            'startLine' => 69,
            'endLine' => 69,
            'startTokenPos' => 126,
            'startFilePos' => 1184,
            'endTokenPos' => 126,
            'endFilePos' => 1184,
          ),
        ),
        'docComment' => '/**
 * Print connection activity.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 69,
        'endLine' => 69,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'DEBUG_BER' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_BER',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16',
          'attributes' => 
          array (
            'startLine' => 76,
            'endLine' => 76,
            'startTokenPos' => 139,
            'startFilePos' => 1300,
            'endTokenPos' => 139,
            'endFilePos' => 1301,
          ),
        ),
        'docComment' => '/**
 * Print encoding and decoding of data.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 76,
        'endLine' => 76,
        'startColumn' => 5,
        'endColumn' => 32,
      ),
      'DEBUG_FILTER' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_FILTER',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '32',
          'attributes' => 
          array (
            'startLine' => 83,
            'endLine' => 83,
            'startTokenPos' => 152,
            'startFilePos' => 1405,
            'endTokenPos' => 152,
            'endFilePos' => 1406,
          ),
        ),
        'docComment' => '/**
 * Print search filters.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 83,
        'endLine' => 83,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'DEBUG_CONFIG' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_CONFIG',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '64',
          'attributes' => 
          array (
            'startLine' => 90,
            'endLine' => 90,
            'startTokenPos' => 165,
            'startFilePos' => 1525,
            'endTokenPos' => 165,
            'endFilePos' => 1526,
          ),
        ),
        'docComment' => '/**
 * Print configuration file processing.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 90,
        'endLine' => 90,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'DEBUG_ACL' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_ACL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '128',
          'attributes' => 
          array (
            'startLine' => 97,
            'endLine' => 97,
            'startTokenPos' => 178,
            'startFilePos' => 1643,
            'endTokenPos' => 178,
            'endFilePos' => 1645,
          ),
        ),
        'docComment' => '/**
 * Print Access Control List activities.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 97,
        'endLine' => 97,
        'startColumn' => 5,
        'endColumn' => 33,
      ),
      'DEBUG_STATS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_STATS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '256',
          'attributes' => 
          array (
            'startLine' => 104,
            'endLine' => 104,
            'startTokenPos' => 191,
            'startFilePos' => 1756,
            'endTokenPos' => 191,
            'endFilePos' => 1758,
          ),
        ),
        'docComment' => '/**
 * Print operational statistics.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 104,
        'endLine' => 104,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'DEBUG_STATS2' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_STATS2',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '512',
          'attributes' => 
          array (
            'startLine' => 111,
            'endLine' => 111,
            'startTokenPos' => 204,
            'startFilePos' => 1872,
            'endTokenPos' => 204,
            'endFilePos' => 1874,
          ),
        ),
        'docComment' => '/**
 * Print more detailed statistics.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 111,
        'endLine' => 111,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'DEBUG_SHELL' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_SHELL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '1024',
          'attributes' => 
          array (
            'startLine' => 118,
            'endLine' => 118,
            'startTokenPos' => 217,
            'startFilePos' => 1996,
            'endTokenPos' => 217,
            'endFilePos' => 1999,
          ),
        ),
        'docComment' => '/**
 * Print communication with shell backends.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 118,
        'endLine' => 118,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'DEBUG_PARSE' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_PARSE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '2048',
          'attributes' => 
          array (
            'startLine' => 125,
            'endLine' => 125,
            'startTokenPos' => 230,
            'startFilePos' => 2101,
            'endTokenPos' => 230,
            'endFilePos' => 2104,
          ),
        ),
        'docComment' => '/**
 * Print entry parsing.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 125,
        'endLine' => 125,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'DEBUG_SYNC' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_SYNC',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '16384',
          'attributes' => 
          array (
            'startLine' => 132,
            'endLine' => 132,
            'startTokenPos' => 243,
            'startFilePos' => 2212,
            'endTokenPos' => 243,
            'endFilePos' => 2216,
          ),
        ),
        'docComment' => '/**
 * Print LDAPSync replication.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 132,
        'endLine' => 132,
        'startColumn' => 5,
        'endColumn' => 36,
      ),
      'DEBUG_REFERRAL' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_REFERRAL',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '32768',
          'attributes' => 
          array (
            'startLine' => 139,
            'endLine' => 139,
            'startTokenPos' => 256,
            'startFilePos' => 2327,
            'endTokenPos' => 256,
            'endFilePos' => 2331,
          ),
        ),
        'docComment' => '/**
 * Print referral activities.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 139,
        'endLine' => 139,
        'startColumn' => 5,
        'endColumn' => 40,
      ),
      'DEBUG_ERROR' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_ERROR',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '32768',
          'attributes' => 
          array (
            'startLine' => 146,
            'endLine' => 146,
            'startTokenPos' => 269,
            'startFilePos' => 2436,
            'endTokenPos' => 269,
            'endFilePos' => 2440,
          ),
        ),
        'docComment' => '/**
 * Print error conditions.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 146,
        'endLine' => 146,
        'startColumn' => 5,
        'endColumn' => 37,
      ),
      'DEBUG_ANY' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'DEBUG_ANY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '65535',
          'attributes' => 
          array (
            'startLine' => 153,
            'endLine' => 153,
            'startTokenPos' => 282,
            'startFilePos' => 2546,
            'endTokenPos' => 282,
            'endFilePos' => 2550,
          ),
        ),
        'docComment' => '/**
 * Print all levels of debug.
 *
 * @var int
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 153,
        'endLine' => 153,
        'startColumn' => 5,
        'endColumn' => 35,
      ),
      'OID_SERVER_START_TLS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_START_TLS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.3.6.1.4.1.1466.20037\'',
          'attributes' => 
          array (
            'startLine' => 160,
            'endLine' => 160,
            'startTokenPos' => 295,
            'startFilePos' => 2729,
            'endTokenPos' => 295,
            'endFilePos' => 2752,
          ),
        ),
        'docComment' => '/**
 * OID for StartTLS extended operation. Signals the server to initiate a TLS connection.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 160,
        'endLine' => 160,
        'startColumn' => 5,
        'endColumn' => 65,
      ),
      'OID_SERVER_PAGED_RESULTS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_PAGED_RESULTS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.319\'',
          'attributes' => 
          array (
            'startLine' => 167,
            'endLine' => 167,
            'startTokenPos' => 308,
            'startFilePos' => 2922,
            'endTokenPos' => 308,
            'endFilePos' => 2945,
          ),
        ),
        'docComment' => '/**
 * OID for Paged Results Control. Used to retrieve search results in pages.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 167,
        'endLine' => 167,
        'startColumn' => 5,
        'endColumn' => 69,
      ),
      'OID_SERVER_SHOW_DELETED' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_SHOW_DELETED',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.417\'',
          'attributes' => 
          array (
            'startLine' => 174,
            'endLine' => 174,
            'startTokenPos' => 321,
            'startFilePos' => 3119,
            'endTokenPos' => 321,
            'endFilePos' => 3142,
          ),
        ),
        'docComment' => '/**
 * OID for Show Deleted Control. Includes deleted entries in the search results.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 174,
        'endLine' => 174,
        'startColumn' => 5,
        'endColumn' => 68,
      ),
      'OID_SERVER_SORT' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_SORT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.473\'',
          'attributes' => 
          array (
            'startLine' => 181,
            'endLine' => 181,
            'startTokenPos' => 334,
            'startFilePos' => 3312,
            'endTokenPos' => 334,
            'endFilePos' => 3335,
          ),
        ),
        'docComment' => '/**
 * OID for Server Side Sort Control. Requests the server to sort the search results.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 181,
        'endLine' => 181,
        'startColumn' => 5,
        'endColumn' => 60,
      ),
      'OID_SERVER_CROSSDOM_MOVE_TARGET' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_CROSSDOM_MOVE_TARGET',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.521\'',
          'attributes' => 
          array (
            'startLine' => 188,
            'endLine' => 188,
            'startTokenPos' => 347,
            'startFilePos' => 3519,
            'endTokenPos' => 347,
            'endFilePos' => 3542,
          ),
        ),
        'docComment' => '/**
 * OID for Cross-Domain Move Target Control. Used in cross-domain move operations.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 188,
        'endLine' => 188,
        'startColumn' => 5,
        'endColumn' => 76,
      ),
      'OID_SERVER_NOTIFICATION' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_NOTIFICATION',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.528\'',
          'attributes' => 
          array (
            'startLine' => 195,
            'endLine' => 195,
            'startTokenPos' => 360,
            'startFilePos' => 3716,
            'endTokenPos' => 360,
            'endFilePos' => 3739,
          ),
        ),
        'docComment' => '/**
 * OID for LDAP Notification Control. Used to register for change notifications.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 195,
        'endLine' => 195,
        'startColumn' => 5,
        'endColumn' => 68,
      ),
      'OID_SERVER_EXTENDED_DN' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_EXTENDED_DN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.529\'',
          'attributes' => 
          array (
            'startLine' => 202,
            'endLine' => 202,
            'startTokenPos' => 373,
            'startFilePos' => 3915,
            'endTokenPos' => 373,
            'endFilePos' => 3938,
          ),
        ),
        'docComment' => '/**
 * OID for Extended DN Control. Requests extended DN information in search results.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 202,
        'endLine' => 202,
        'startColumn' => 5,
        'endColumn' => 67,
      ),
      'OID_SERVER_LAZY_COMMIT' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_LAZY_COMMIT',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.619\'',
          'attributes' => 
          array (
            'startLine' => 209,
            'endLine' => 209,
            'startTokenPos' => 386,
            'startFilePos' => 4115,
            'endTokenPos' => 386,
            'endFilePos' => 4138,
          ),
        ),
        'docComment' => '/**
 * OID for Lazy Commit Control. Delays the actual commit of changes until requested.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 209,
        'endLine' => 209,
        'startColumn' => 5,
        'endColumn' => 67,
      ),
      'OID_SERVER_SD_FLAGS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_SD_FLAGS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.801\'',
          'attributes' => 
          array (
            'startLine' => 216,
            'endLine' => 216,
            'startTokenPos' => 399,
            'startFilePos' => 4319,
            'endTokenPos' => 399,
            'endFilePos' => 4342,
          ),
        ),
        'docComment' => '/**
 * OID for Security Descriptor Flags Control. Used to manipulate security descriptor flags.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 216,
        'endLine' => 216,
        'startColumn' => 5,
        'endColumn' => 64,
      ),
      'OID_SERVER_TREE_DELETE' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_TREE_DELETE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.805\'',
          'attributes' => 
          array (
            'startLine' => 223,
            'endLine' => 223,
            'startTokenPos' => 412,
            'startFilePos' => 4509,
            'endTokenPos' => 412,
            'endFilePos' => 4532,
          ),
        ),
        'docComment' => '/**
 * OID for Tree Delete Control. Enables the deletion of an entire subtree.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 223,
        'endLine' => 223,
        'startColumn' => 5,
        'endColumn' => 67,
      ),
      'OID_SERVER_DIRSYNC' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_DIRSYNC',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.841\'',
          'attributes' => 
          array (
            'startLine' => 230,
            'endLine' => 230,
            'startTokenPos' => 425,
            'startFilePos' => 4695,
            'endTokenPos' => 425,
            'endFilePos' => 4718,
          ),
        ),
        'docComment' => '/**
 * OID for DirSync Control. Used for directory synchronization operations.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 230,
        'endLine' => 230,
        'startColumn' => 5,
        'endColumn' => 63,
      ),
      'OID_SERVER_VERIFY_NAME' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_VERIFY_NAME',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.1338\'',
          'attributes' => 
          array (
            'startLine' => 237,
            'endLine' => 237,
            'startTokenPos' => 438,
            'startFilePos' => 4905,
            'endTokenPos' => 438,
            'endFilePos' => 4929,
          ),
        ),
        'docComment' => '/**
 * OID for Verify Name Control. Allows verification of an entry without retrieving attributes.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 237,
        'endLine' => 237,
        'startColumn' => 5,
        'endColumn' => 68,
      ),
      'OID_SERVER_DOMAIN_SCOPE' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_DOMAIN_SCOPE',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.1339\'',
          'attributes' => 
          array (
            'startLine' => 244,
            'endLine' => 244,
            'startTokenPos' => 451,
            'startFilePos' => 5094,
            'endTokenPos' => 451,
            'endFilePos' => 5118,
          ),
        ),
        'docComment' => '/**
 * OID for Domain Scope Control. Limits a search to the current domain.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 244,
        'endLine' => 244,
        'startColumn' => 5,
        'endColumn' => 69,
      ),
      'OID_SERVER_SEARCH_OPTIONS' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_SEARCH_OPTIONS',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.1340\'',
          'attributes' => 
          array (
            'startLine' => 251,
            'endLine' => 251,
            'startTokenPos' => 464,
            'startFilePos' => 5284,
            'endTokenPos' => 464,
            'endFilePos' => 5308,
          ),
        ),
        'docComment' => '/**
 * OID for Search Options Control. Used to set various search options.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 251,
        'endLine' => 251,
        'startColumn' => 5,
        'endColumn' => 71,
      ),
      'OID_SERVER_PERMISSIVE_MODIFY' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_PERMISSIVE_MODIFY',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.1413\'',
          'attributes' => 
          array (
            'startLine' => 258,
            'endLine' => 258,
            'startTokenPos' => 477,
            'startFilePos' => 5502,
            'endTokenPos' => 477,
            'endFilePos' => 5526,
          ),
        ),
        'docComment' => '/**
 * OID for Permissive Modify Control. Allows modifications even if some attributes are missing.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 258,
        'endLine' => 258,
        'startColumn' => 5,
        'endColumn' => 74,
      ),
      'OID_SERVER_ASQ' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_ASQ',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.1504\'',
          'attributes' => 
          array (
            'startLine' => 265,
            'endLine' => 265,
            'startTokenPos' => 490,
            'startFilePos' => 5699,
            'endTokenPos' => 490,
            'endFilePos' => 5723,
          ),
        ),
        'docComment' => '/**
 * OID for Authentication Service Queries (ASQ) Control. Used to perform ASQ operations.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 265,
        'endLine' => 265,
        'startColumn' => 5,
        'endColumn' => 60,
      ),
      'OID_SERVER_FAST_BIND' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_FAST_BIND',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.1781\'',
          'attributes' => 
          array (
            'startLine' => 272,
            'endLine' => 272,
            'startTokenPos' => 503,
            'startFilePos' => 5897,
            'endTokenPos' => 503,
            'endFilePos' => 5921,
          ),
        ),
        'docComment' => '/**
 * OID for Fast Bind Control. Optimizes the bind process for faster authentication.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 272,
        'endLine' => 272,
        'startColumn' => 5,
        'endColumn' => 66,
      ),
      'OID_SERVER_CONTROL_VLVREQUEST' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_SERVER_CONTROL_VLVREQUEST',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'2.16.840.1.113730.3.4.9\'',
          'attributes' => 
          array (
            'startLine' => 279,
            'endLine' => 279,
            'startTokenPos' => 516,
            'startFilePos' => 6117,
            'endTokenPos' => 516,
            'endFilePos' => 6141,
          ),
        ),
        'docComment' => '/**
 * OID for Virtual List View (VLV) Request Control. Used to request a specific range of entries.
 *
 * @var string
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 279,
        'endLine' => 279,
        'startColumn' => 5,
        'endColumn' => 75,
      ),
      'OID_MATCHING_RULE_IN_CHAIN' => 
      array (
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'name' => 'OID_MATCHING_RULE_IN_CHAIN',
        'modifiers' => 1,
        'type' => NULL,
        'value' => 
        array (
          'code' => '\'1.2.840.113556.1.4.1941\'',
          'attributes' => 
          array (
            'startLine' => 284,
            'endLine' => 284,
            'startTokenPos' => 529,
            'startFilePos' => 6319,
            'endTokenPos' => 529,
            'endFilePos' => 6343,
          ),
        ),
        'docComment' => '/**
 * OID for the \'matchingRuleInChain\' matching rule. Used for substring searches in multi-valued attributes.
 */',
        'attributes' => 
        array (
        ),
        'startLine' => 284,
        'endLine' => 284,
        'startColumn' => 5,
        'endColumn' => 72,
      ),
    ),
    'immediateProperties' => 
    array (
    ),
    'immediateMethods' => 
    array (
      'setTLS' => 
      array (
        'name' => 'setTLS',
        'parameters' => 
        array (
          'enabled' => 
          array (
            'name' => 'enabled',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 289,
                'endLine' => 289,
                'startTokenPos' => 546,
                'startFilePos' => 6472,
                'endTokenPos' => 546,
                'endFilePos' => 6475,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 289,
            'endLine' => 289,
            'startColumn' => 28,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the current connection to use TLS (ldaps:// protocol).
 */',
        'startLine' => 289,
        'endLine' => 289,
        'startColumn' => 5,
        'endColumn' => 57,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'isUsingTLS' => 
      array (
        'name' => 'isUsingTLS',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Determine if the current connection instance is using TLS (ldaps:// protocol).
 */',
        'startLine' => 294,
        'endLine' => 294,
        'startColumn' => 5,
        'endColumn' => 39,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'setStartTLS' => 
      array (
        'name' => 'setStartTLS',
        'parameters' => 
        array (
          'enabled' => 
          array (
            'name' => 'enabled',
            'default' => 
            array (
              'code' => 'true',
              'attributes' => 
              array (
                'startLine' => 299,
                'endLine' => 299,
                'startTokenPos' => 581,
                'startFilePos' => 6769,
                'endTokenPos' => 581,
                'endFilePos' => 6772,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 299,
            'endLine' => 299,
            'startColumn' => 33,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set the current connection to use STARTTLS (ldap:// with upgrade).
 */',
        'startLine' => 299,
        'endLine' => 299,
        'startColumn' => 5,
        'endColumn' => 62,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'isUsingStartTLS' => 
      array (
        'name' => 'isUsingStartTLS',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Determine if the current connection instance is using STARTTLS (ldap:// with upgrade).
 */',
        'startLine' => 304,
        'endLine' => 304,
        'startColumn' => 5,
        'endColumn' => 44,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'isBound' => 
      array (
        'name' => 'isBound',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Determine if the connection is bound.
 */',
        'startLine' => 309,
        'endLine' => 309,
        'startColumn' => 5,
        'endColumn' => 36,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'isSecure' => 
      array (
        'name' => 'isSecure',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Determine if the connection is secure over TLS or SSL.
 */',
        'startLine' => 314,
        'endLine' => 314,
        'startColumn' => 5,
        'endColumn' => 37,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'isConnected' => 
      array (
        'name' => 'isConnected',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Determine if the connection has been created.
 */',
        'startLine' => 319,
        'endLine' => 319,
        'startColumn' => 5,
        'endColumn' => 40,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'canChangePasswords' => 
      array (
        'name' => 'canChangePasswords',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Determine the connection is able to modify passwords.
 */',
        'startLine' => 324,
        'endLine' => 324,
        'startColumn' => 5,
        'endColumn' => 47,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getHost' => 
      array (
        'name' => 'getHost',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Get the full LDAP host URL.
 *
 * Ex: ldap://192.168.1.1:386
 */',
        'startLine' => 331,
        'endLine' => 331,
        'startColumn' => 5,
        'endColumn' => 39,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getConnection' => 
      array (
        'name' => 'getConnection',
        'parameters' => 
        array (
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
                  'name' => 'LDAP\\Connection',
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the underlying raw LDAP connection.
 */',
        'startLine' => 336,
        'endLine' => 336,
        'startColumn' => 5,
        'endColumn' => 49,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getEntries' => 
      array (
        'name' => 'getEntries',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 345,
            'endLine' => 345,
            'startColumn' => 32,
            'endColumn' => 44,
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
            'name' => 'array',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the entries from a search result.
 *
 * @see http://php.net/manual/en/function.ldap-get-entries.php
 *
 * @param  Result  $result
 */',
        'startLine' => 345,
        'endLine' => 345,
        'startColumn' => 5,
        'endColumn' => 53,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getFirstEntry' => 
      array (
        'name' => 'getFirstEntry',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 354,
            'endLine' => 354,
            'startColumn' => 35,
            'endColumn' => 47,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the entry identifier for first entry in the result.
 *
 * @see https://www.php.net/manual/en/function.ldap-first-entry.php
 *
 * @param  Result  $result
 */',
        'startLine' => 354,
        'endLine' => 354,
        'startColumn' => 5,
        'endColumn' => 56,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getNextEntry' => 
      array (
        'name' => 'getNextEntry',
        'parameters' => 
        array (
          'entry' => 
          array (
            'name' => 'entry',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 363,
            'endLine' => 363,
            'startColumn' => 34,
            'endColumn' => 45,
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
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Retrieve the next result entry.
 *
 * @see https://www.php.net/manual/en/function.ldap-next-entry.php
 *
 * @param  Result  $entry
 */',
        'startLine' => 363,
        'endLine' => 363,
        'startColumn' => 5,
        'endColumn' => 54,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getAttributes' => 
      array (
        'name' => 'getAttributes',
        'parameters' => 
        array (
          'entry' => 
          array (
            'name' => 'entry',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 372,
            'endLine' => 372,
            'startColumn' => 35,
            'endColumn' => 46,
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
                  'name' => 'array',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reads attributes and values from an entry in the search result.
 *
 * @see https://www.php.net/manual/en/function.ldap-get-attributes.php
 *
 * @param  Result  $entry
 */',
        'startLine' => 372,
        'endLine' => 372,
        'startColumn' => 5,
        'endColumn' => 61,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getValuesLen' => 
      array (
        'name' => 'getValuesLen',
        'parameters' => 
        array (
          'entry' => 
          array (
            'name' => 'entry',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 379,
            'endLine' => 379,
            'startColumn' => 34,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attribute' => 
          array (
            'name' => 'attribute',
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
            'startLine' => 379,
            'endLine' => 379,
            'startColumn' => 48,
            'endColumn' => 64,
            'parameterIndex' => 1,
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
                  'name' => 'array',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reads all the values of the attribute in the entry in the result.
 *
 * @param  Result  $entry
 */',
        'startLine' => 379,
        'endLine' => 379,
        'startColumn' => 5,
        'endColumn' => 79,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getLastError' => 
      array (
        'name' => 'getLastError',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Retrieve the last error on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-error.php
 */',
        'startLine' => 386,
        'endLine' => 386,
        'startColumn' => 5,
        'endColumn' => 44,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getDetailedError' => 
      array (
        'name' => 'getDetailedError',
        'parameters' => 
        array (
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
                  'name' => 'LdapRecord\\DetailedError',
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
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Return detailed information about an error.
 *
 * Returns null when there was a successful last request.
 *
 * Returns DetailedError when there was an error.
 */',
        'startLine' => 395,
        'endLine' => 395,
        'startColumn' => 5,
        'endColumn' => 55,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'countEntries' => 
      array (
        'name' => 'countEntries',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 404,
            'endLine' => 404,
            'startColumn' => 34,
            'endColumn' => 46,
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
            'name' => 'int',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Count the number of entries in a search.
 *
 * @see https://www.php.net/manual/en/function.ldap-count-entries.php
 *
 * @param  Result  $result
 */',
        'startLine' => 404,
        'endLine' => 404,
        'startColumn' => 5,
        'endColumn' => 53,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'compare' => 
      array (
        'name' => 'compare',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 29,
            'endColumn' => 38,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'attribute' => 
          array (
            'name' => 'attribute',
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
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 41,
            'endColumn' => 57,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
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
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 60,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'controls' => 
          array (
            'name' => 'controls',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 409,
                'endLine' => 409,
                'startTokenPos' => 859,
                'startFilePos' => 9687,
                'endTokenPos' => 859,
                'endFilePos' => 9690,
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
                      'name' => 'array',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 409,
            'endLine' => 409,
            'startColumn' => 75,
            'endColumn' => 97,
            'parameterIndex' => 3,
            'isOptional' => true,
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
                  'name' => 'bool',
                  'isIdentifier' => true,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'int',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Compare value of attribute found in entry specified with DN.
 */',
        'startLine' => 409,
        'endLine' => 409,
        'startColumn' => 5,
        'endColumn' => 109,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'setOption' => 
      array (
        'name' => 'setOption',
        'parameters' => 
        array (
          'option' => 
          array (
            'name' => 'option',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 31,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 416,
            'endLine' => 416,
            'startColumn' => 44,
            'endColumn' => 55,
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
        'docComment' => '/**
 * Set an option on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-set-option.php
 */',
        'startLine' => 416,
        'endLine' => 416,
        'startColumn' => 5,
        'endColumn' => 63,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'setOptions' => 
      array (
        'name' => 'setOptions',
        'parameters' => 
        array (
          'options' => 
          array (
            'name' => 'options',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 421,
                'endLine' => 421,
                'startTokenPos' => 904,
                'startFilePos' => 10026,
                'endTokenPos' => 905,
                'endFilePos' => 10027,
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
            'startLine' => 421,
            'endLine' => 421,
            'startColumn' => 32,
            'endColumn' => 50,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Set multiple options on the current connection.
 */',
        'startLine' => 421,
        'endLine' => 421,
        'startColumn' => 5,
        'endColumn' => 58,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'setRebindCallback' => 
      array (
        'name' => 'setRebindCallback',
        'parameters' => 
        array (
          'callback' => 
          array (
            'name' => 'callback',
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
            'startLine' => 428,
            'endLine' => 428,
            'startColumn' => 39,
            'endColumn' => 56,
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
        'docComment' => '/**
 * Set a callback function to do re-binds on referral chasing.
 *
 * @see https://www.php.net/manual/en/function.ldap-set-rebind-proc.php
 */',
        'startLine' => 428,
        'endLine' => 428,
        'startColumn' => 5,
        'endColumn' => 64,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getOption' => 
      array (
        'name' => 'getOption',
        'parameters' => 
        array (
          'option' => 
          array (
            'name' => 'option',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 435,
            'endLine' => 435,
            'startColumn' => 31,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'value' => 
          array (
            'name' => 'value',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 435,
                'endLine' => 435,
                'startTokenPos' => 949,
                'startFilePos' => 10465,
                'endTokenPos' => 949,
                'endFilePos' => 10468,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 435,
            'endLine' => 435,
            'startColumn' => 44,
            'endColumn' => 63,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the value for the LDAP option.
 *
 * @see https://www.php.net/manual/en/function.ldap-get-option.php
 */',
        'startLine' => 435,
        'endLine' => 435,
        'startColumn' => 5,
        'endColumn' => 72,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'startTLS' => 
      array (
        'name' => 'startTLS',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Starts a connection using TLS.
 *
 * @see http://php.net/manual/en/function.ldap-start-tls.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 444,
        'endLine' => 444,
        'startColumn' => 5,
        'endColumn' => 37,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'connect' => 
      array (
        'name' => 'connect',
        'parameters' => 
        array (
          'hosts' => 
          array (
            'name' => 'hosts',
            'default' => 
            array (
              'code' => '[]',
              'attributes' => 
              array (
                'startLine' => 451,
                'endLine' => 451,
                'startTokenPos' => 986,
                'startFilePos' => 10893,
                'endTokenPos' => 987,
                'endFilePos' => 10894,
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
                      'name' => 'string',
                      'isIdentifier' => true,
                    ),
                  ),
                  1 => 
                  array (
                    'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                    'data' => 
                    array (
                      'name' => 'array',
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
            'startLine' => 451,
            'endLine' => 451,
            'startColumn' => 29,
            'endColumn' => 52,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'port' => 
          array (
            'name' => 'port',
            'default' => 
            array (
              'code' => '389',
              'attributes' => 
              array (
                'startLine' => 451,
                'endLine' => 451,
                'startTokenPos' => 996,
                'startFilePos' => 10909,
                'endTokenPos' => 996,
                'endFilePos' => 10911,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 451,
            'endLine' => 451,
            'startColumn' => 55,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'protocol' => 
          array (
            'name' => 'protocol',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 451,
                'endLine' => 451,
                'startTokenPos' => 1006,
                'startFilePos' => 10934,
                'endTokenPos' => 1006,
                'endFilePos' => 10937,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 451,
            'endLine' => 451,
            'startColumn' => 72,
            'endColumn' => 95,
            'parameterIndex' => 2,
            'isOptional' => true,
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
        'docComment' => '/**
 * Connects to the specified hostname using the specified port.
 *
 * @see http://php.net/manual/en/function.ldap-start-tls.php
 */',
        'startLine' => 451,
        'endLine' => 451,
        'startColumn' => 5,
        'endColumn' => 103,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'close' => 
      array (
        'name' => 'close',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Closes the current connection.
 *
 * Returns false if no connection is present.
 *
 * @see http://php.net/manual/en/function.ldap-close.php
 */',
        'startLine' => 460,
        'endLine' => 460,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'search' => 
      array (
        'name' => 'search',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 28,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'filter' => 
          array (
            'name' => 'filter',
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
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 40,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
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
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 56,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'onlyAttributes' => 
          array (
            'name' => 'onlyAttributes',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 469,
                'endLine' => 469,
                'startTokenPos' => 1056,
                'startFilePos' => 11426,
                'endTokenPos' => 1056,
                'endFilePos' => 11430,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 75,
            'endColumn' => 102,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'size' => 
          array (
            'name' => 'size',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 469,
                'endLine' => 469,
                'startTokenPos' => 1065,
                'startFilePos' => 11445,
                'endTokenPos' => 1065,
                'endFilePos' => 11445,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 105,
            'endColumn' => 117,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 469,
                'endLine' => 469,
                'startTokenPos' => 1074,
                'startFilePos' => 11460,
                'endTokenPos' => 1074,
                'endFilePos' => 11460,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 120,
            'endColumn' => 132,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'deref' => 
          array (
            'name' => 'deref',
            'default' => 
            array (
              'code' => 'LDAP_DEREF_NEVER',
              'attributes' => 
              array (
                'startLine' => 469,
                'endLine' => 469,
                'startTokenPos' => 1083,
                'startFilePos' => 11476,
                'endTokenPos' => 1083,
                'endFilePos' => 11491,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 135,
            'endColumn' => 163,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
          'controls' => 
          array (
            'name' => 'controls',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 469,
                'endLine' => 469,
                'startTokenPos' => 1093,
                'startFilePos' => 11513,
                'endTokenPos' => 1093,
                'endFilePos' => 11516,
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
                      'name' => 'array',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 469,
            'endLine' => 469,
            'startColumn' => 166,
            'endColumn' => 188,
            'parameterIndex' => 7,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Performs a search on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-search.php
 *
 * @return Result
 */',
        'startLine' => 469,
        'endLine' => 469,
        'startColumn' => 5,
        'endColumn' => 197,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'list' => 
      array (
        'name' => 'list',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 26,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'filter' => 
          array (
            'name' => 'filter',
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
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
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
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 54,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'onlyAttributes' => 
          array (
            'name' => 'onlyAttributes',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 478,
                'endLine' => 478,
                'startTokenPos' => 1129,
                'startFilePos' => 11800,
                'endTokenPos' => 1129,
                'endFilePos' => 11804,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 73,
            'endColumn' => 100,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'size' => 
          array (
            'name' => 'size',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 478,
                'endLine' => 478,
                'startTokenPos' => 1138,
                'startFilePos' => 11819,
                'endTokenPos' => 1138,
                'endFilePos' => 11819,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 103,
            'endColumn' => 115,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 478,
                'endLine' => 478,
                'startTokenPos' => 1147,
                'startFilePos' => 11834,
                'endTokenPos' => 1147,
                'endFilePos' => 11834,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 118,
            'endColumn' => 130,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'deref' => 
          array (
            'name' => 'deref',
            'default' => 
            array (
              'code' => 'LDAP_DEREF_NEVER',
              'attributes' => 
              array (
                'startLine' => 478,
                'endLine' => 478,
                'startTokenPos' => 1156,
                'startFilePos' => 11850,
                'endTokenPos' => 1156,
                'endFilePos' => 11865,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 133,
            'endColumn' => 161,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
          'controls' => 
          array (
            'name' => 'controls',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 478,
                'endLine' => 478,
                'startTokenPos' => 1166,
                'startFilePos' => 11887,
                'endTokenPos' => 1166,
                'endFilePos' => 11890,
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
                      'name' => 'array',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 478,
            'endLine' => 478,
            'startColumn' => 164,
            'endColumn' => 186,
            'parameterIndex' => 7,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Performs a single level search on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-list.php
 *
 * @return Result
 */',
        'startLine' => 478,
        'endLine' => 478,
        'startColumn' => 5,
        'endColumn' => 195,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'read' => 
      array (
        'name' => 'read',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 26,
            'endColumn' => 35,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'filter' => 
          array (
            'name' => 'filter',
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
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 38,
            'endColumn' => 51,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'attributes' => 
          array (
            'name' => 'attributes',
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
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 54,
            'endColumn' => 70,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'onlyAttributes' => 
          array (
            'name' => 'onlyAttributes',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 487,
                'endLine' => 487,
                'startTokenPos' => 1202,
                'startFilePos' => 12158,
                'endTokenPos' => 1202,
                'endFilePos' => 12162,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 73,
            'endColumn' => 100,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'size' => 
          array (
            'name' => 'size',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 487,
                'endLine' => 487,
                'startTokenPos' => 1211,
                'startFilePos' => 12177,
                'endTokenPos' => 1211,
                'endFilePos' => 12177,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 103,
            'endColumn' => 115,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'time' => 
          array (
            'name' => 'time',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 487,
                'endLine' => 487,
                'startTokenPos' => 1220,
                'startFilePos' => 12192,
                'endTokenPos' => 1220,
                'endFilePos' => 12192,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 118,
            'endColumn' => 130,
            'parameterIndex' => 5,
            'isOptional' => true,
          ),
          'deref' => 
          array (
            'name' => 'deref',
            'default' => 
            array (
              'code' => 'LDAP_DEREF_NEVER',
              'attributes' => 
              array (
                'startLine' => 487,
                'endLine' => 487,
                'startTokenPos' => 1229,
                'startFilePos' => 12208,
                'endTokenPos' => 1229,
                'endFilePos' => 12223,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 133,
            'endColumn' => 161,
            'parameterIndex' => 6,
            'isOptional' => true,
          ),
          'controls' => 
          array (
            'name' => 'controls',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 487,
                'endLine' => 487,
                'startTokenPos' => 1239,
                'startFilePos' => 12245,
                'endTokenPos' => 1239,
                'endFilePos' => 12248,
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
                      'name' => 'array',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 487,
            'endLine' => 487,
            'startColumn' => 164,
            'endColumn' => 186,
            'parameterIndex' => 7,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'mixed',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Reads an entry on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-read.php
 *
 * @return Result
 */',
        'startLine' => 487,
        'endLine' => 487,
        'startColumn' => 5,
        'endColumn' => 195,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'parseResult' => 
      array (
        'name' => 'parseResult',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 33,
            'endColumn' => 45,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'errorCode' => 
          array (
            'name' => 'errorCode',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 496,
                'endLine' => 496,
                'startTokenPos' => 1266,
                'startFilePos' => 12507,
                'endTokenPos' => 1266,
                'endFilePos' => 12507,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 48,
            'endColumn' => 66,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'dn' => 
          array (
            'name' => 'dn',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 496,
                'endLine' => 496,
                'startTokenPos' => 1277,
                'startFilePos' => 12525,
                'endTokenPos' => 1277,
                'endFilePos' => 12528,
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
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 69,
            'endColumn' => 87,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'errorMessage' => 
          array (
            'name' => 'errorMessage',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 496,
                'endLine' => 496,
                'startTokenPos' => 1288,
                'startFilePos' => 12556,
                'endTokenPos' => 1288,
                'endFilePos' => 12559,
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
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 90,
            'endColumn' => 118,
            'parameterIndex' => 3,
            'isOptional' => true,
          ),
          'referrals' => 
          array (
            'name' => 'referrals',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 496,
                'endLine' => 496,
                'startTokenPos' => 1299,
                'startFilePos' => 12583,
                'endTokenPos' => 1299,
                'endFilePos' => 12586,
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
                      'name' => 'array',
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
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 121,
            'endColumn' => 145,
            'parameterIndex' => 4,
            'isOptional' => true,
          ),
          'controls' => 
          array (
            'name' => 'controls',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 496,
                'endLine' => 496,
                'startTokenPos' => 1310,
                'startFilePos' => 12609,
                'endTokenPos' => 1310,
                'endFilePos' => 12612,
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
                      'name' => 'array',
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
            'isVariadic' => false,
            'byRef' => true,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 496,
            'endLine' => 496,
            'startColumn' => 148,
            'endColumn' => 171,
            'parameterIndex' => 5,
            'isOptional' => true,
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
                  'name' => 'LdapRecord\\LdapResultResponse',
                  'isIdentifier' => false,
                ),
              ),
              1 => 
              array (
                'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
                'data' => 
                array (
                  'name' => 'false',
                  'isIdentifier' => true,
                ),
              ),
            ),
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Extract information from an LDAP result.
 *
 * @see https://www.php.net/manual/en/function.ldap-parse-result.php
 *
 * @param  Result  $result
 */',
        'startLine' => 496,
        'endLine' => 496,
        'startColumn' => 5,
        'endColumn' => 199,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'bind' => 
      array (
        'name' => 'bind',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 505,
                'endLine' => 505,
                'startTokenPos' => 1334,
                'startFilePos' => 12842,
                'endTokenPos' => 1334,
                'endFilePos' => 12845,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 505,
            'endLine' => 505,
            'startColumn' => 26,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 505,
                'endLine' => 505,
                'startTokenPos' => 1344,
                'startFilePos' => 12868,
                'endTokenPos' => 1344,
                'endFilePos' => 12871,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 505,
            'endLine' => 505,
            'startColumn' => 46,
            'endColumn' => 69,
            'parameterIndex' => 1,
            'isOptional' => true,
          ),
          'controls' => 
          array (
            'name' => 'controls',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 505,
                'endLine' => 505,
                'startTokenPos' => 1354,
                'startFilePos' => 12893,
                'endTokenPos' => 1354,
                'endFilePos' => 12896,
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
                      'name' => 'array',
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 505,
            'endLine' => 505,
            'startColumn' => 72,
            'endColumn' => 94,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'LdapRecord\\LdapResultResponse',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Bind to the LDAP directory.
 *
 * @see http://php.net/manual/en/function.ldap-bind.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 505,
        'endLine' => 505,
        'startColumn' => 5,
        'endColumn' => 116,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'saslBind' => 
      array (
        'name' => 'saslBind',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 520,
                'endLine' => 520,
                'startTokenPos' => 1376,
                'startFilePos' => 13481,
                'endTokenPos' => 1376,
                'endFilePos' => 13484,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 30,
            'endColumn' => 47,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'password' => 
          array (
            'name' => 'password',
            'default' => 
            array (
              'code' => 'null',
              'attributes' => 
              array (
                'startLine' => 520,
                'endLine' => 520,
                'startTokenPos' => 1386,
                'startFilePos' => 13507,
                'endTokenPos' => 1386,
                'endFilePos' => 13510,
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
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 50,
            'endColumn' => 73,
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
                'startLine' => 520,
                'endLine' => 520,
                'startTokenPos' => 1395,
                'startFilePos' => 13530,
                'endTokenPos' => 1396,
                'endFilePos' => 13531,
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
            'startLine' => 520,
            'endLine' => 520,
            'startColumn' => 76,
            'endColumn' => 94,
            'parameterIndex' => 2,
            'isOptional' => true,
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
        'docComment' => '/**
 * Bind to the LDAP directory using SASL.
 *
 * SASL options:
 *  - mech: Mechanism (Defaults: null)
 *  - realm: Realm (Defaults: null)
 *  - authc_id: Verification Identity (Defaults: null)
 *  - authz_id: Authorization Identity (Defaults: null)
 *  - props: Options for Authorization Identity (Defaults: null)
 *
 * @see https://php.net/manual/en/function.ldap-sasl-bind.php
 * @see https://www.iana.org/assignments/sasl-mechanisms/sasl-mechanisms.xhtml
 */',
        'startLine' => 520,
        'endLine' => 520,
        'startColumn' => 5,
        'endColumn' => 102,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'add' => 
      array (
        'name' => 'add',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 529,
            'endLine' => 529,
            'startColumn' => 25,
            'endColumn' => 34,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'entry' => 
          array (
            'name' => 'entry',
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
            'startLine' => 529,
            'endLine' => 529,
            'startColumn' => 37,
            'endColumn' => 48,
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
        'docComment' => '/**
 * Adds an entry to the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-add.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 529,
        'endLine' => 529,
        'startColumn' => 5,
        'endColumn' => 56,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'delete' => 
      array (
        'name' => 'delete',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 538,
            'endLine' => 538,
            'startColumn' => 28,
            'endColumn' => 37,
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
        'docComment' => '/**
 * Deletes an entry on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-delete.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 538,
        'endLine' => 538,
        'startColumn' => 5,
        'endColumn' => 45,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'rename' => 
      array (
        'name' => 'rename',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 547,
            'endLine' => 547,
            'startColumn' => 28,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'newRdn' => 
          array (
            'name' => 'newRdn',
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
            'startLine' => 547,
            'endLine' => 547,
            'startColumn' => 40,
            'endColumn' => 53,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'newParent' => 
          array (
            'name' => 'newParent',
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
            'startLine' => 547,
            'endLine' => 547,
            'startColumn' => 56,
            'endColumn' => 72,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
          'deleteOldRdn' => 
          array (
            'name' => 'deleteOldRdn',
            'default' => 
            array (
              'code' => 'false',
              'attributes' => 
              array (
                'startLine' => 547,
                'endLine' => 547,
                'startTokenPos' => 1471,
                'startFilePos' => 14281,
                'endTokenPos' => 1471,
                'endFilePos' => 14285,
              ),
            ),
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'bool',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 547,
            'endLine' => 547,
            'startColumn' => 75,
            'endColumn' => 100,
            'parameterIndex' => 3,
            'isOptional' => true,
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
        'docComment' => '/**
 * Modify the name of an entry on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-rename.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 547,
        'endLine' => 547,
        'startColumn' => 5,
        'endColumn' => 108,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'modify' => 
      array (
        'name' => 'modify',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 556,
            'endLine' => 556,
            'startColumn' => 28,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'entry' => 
          array (
            'name' => 'entry',
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
            'startLine' => 556,
            'endLine' => 556,
            'startColumn' => 40,
            'endColumn' => 51,
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
        'docComment' => '/**
 * Modifies an existing entry on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-modify.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 556,
        'endLine' => 556,
        'startColumn' => 5,
        'endColumn' => 59,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'modifyBatch' => 
      array (
        'name' => 'modifyBatch',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 565,
            'endLine' => 565,
            'startColumn' => 33,
            'endColumn' => 42,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'values' => 
          array (
            'name' => 'values',
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
            'startLine' => 565,
            'endLine' => 565,
            'startColumn' => 45,
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
        'docComment' => '/**
 * Batch modifies an existing entry on the current connection.
 *
 * @see http://php.net/manual/en/function.ldap-modify-batch.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 565,
        'endLine' => 565,
        'startColumn' => 5,
        'endColumn' => 65,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'modAdd' => 
      array (
        'name' => 'modAdd',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 574,
            'endLine' => 574,
            'startColumn' => 28,
            'endColumn' => 37,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'entry' => 
          array (
            'name' => 'entry',
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
            'startLine' => 574,
            'endLine' => 574,
            'startColumn' => 40,
            'endColumn' => 51,
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
        'docComment' => '/**
 * Add attribute values to current attributes.
 *
 * @see http://php.net/manual/en/function.ldap-mod-add.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 574,
        'endLine' => 574,
        'startColumn' => 5,
        'endColumn' => 59,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'modReplace' => 
      array (
        'name' => 'modReplace',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 583,
            'endLine' => 583,
            'startColumn' => 32,
            'endColumn' => 41,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'entry' => 
          array (
            'name' => 'entry',
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
            'startLine' => 583,
            'endLine' => 583,
            'startColumn' => 44,
            'endColumn' => 55,
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
        'docComment' => '/**
 * Replaces attribute values with new ones.
 *
 * @see http://php.net/manual/en/function.ldap-mod-replace.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 583,
        'endLine' => 583,
        'startColumn' => 5,
        'endColumn' => 63,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'modDelete' => 
      array (
        'name' => 'modDelete',
        'parameters' => 
        array (
          'dn' => 
          array (
            'name' => 'dn',
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
            'startLine' => 592,
            'endLine' => 592,
            'startColumn' => 31,
            'endColumn' => 40,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'entry' => 
          array (
            'name' => 'entry',
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
            'startLine' => 592,
            'endLine' => 592,
            'startColumn' => 43,
            'endColumn' => 54,
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
        'docComment' => '/**
 * Delete attribute values from current attributes.
 *
 * @see http://php.net/manual/en/function.ldap-mod-del.php
 *
 * @throws LdapRecordException
 */',
        'startLine' => 592,
        'endLine' => 592,
        'startColumn' => 5,
        'endColumn' => 62,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'freeResult' => 
      array (
        'name' => 'freeResult',
        'parameters' => 
        array (
          'result' => 
          array (
            'name' => 'result',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'mixed',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 601,
            'endLine' => 601,
            'startColumn' => 32,
            'endColumn' => 44,
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
        'docComment' => '/**
 * Frees up the memory allocated internally to store the result.
 *
 * @see https://www.php.net/manual/en/function.ldap-free-result.php
 *
 * @param  Result  $result
 */',
        'startLine' => 601,
        'endLine' => 601,
        'startColumn' => 5,
        'endColumn' => 52,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'errNo' => 
      array (
        'name' => 'errNo',
        'parameters' => 
        array (
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
                  'name' => 'int',
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
        'docComment' => '/**
 * Get the error number of the last command executed.
 *
 * @see http://php.net/manual/en/function.ldap-errno.php
 */',
        'startLine' => 608,
        'endLine' => 608,
        'startColumn' => 5,
        'endColumn' => 34,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'err2Str' => 
      array (
        'name' => 'err2Str',
        'parameters' => 
        array (
          'number' => 
          array (
            'name' => 'number',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'int',
                'isIdentifier' => true,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 615,
            'endLine' => 615,
            'startColumn' => 29,
            'endColumn' => 39,
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
            'name' => 'string',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Get the error string of the specified error number.
 *
 * @see http://php.net/manual/en/function.ldap-err2str.php
 */',
        'startLine' => 615,
        'endLine' => 615,
        'startColumn' => 5,
        'endColumn' => 49,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getProtocol' => 
      array (
        'name' => 'getProtocol',
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
        'docComment' => '/**
 * Get the LDAP protocol to utilize for the current connection.
 */',
        'startLine' => 620,
        'endLine' => 620,
        'startColumn' => 5,
        'endColumn' => 42,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getExtendedError' => 
      array (
        'name' => 'getExtendedError',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Get the extended error code of the last command.
 */',
        'startLine' => 625,
        'endLine' => 625,
        'startColumn' => 5,
        'endColumn' => 48,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
        'aliasName' => NULL,
      ),
      'getDiagnosticMessage' => 
      array (
        'name' => 'getDiagnosticMessage',
        'parameters' => 
        array (
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
        'docComment' => '/**
 * Get the diagnostic message.
 */',
        'startLine' => 630,
        'endLine' => 630,
        'startColumn' => 5,
        'endColumn' => 52,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => 'LdapRecord',
        'declaringClassName' => 'LdapRecord\\LdapInterface',
        'implementingClassName' => 'LdapRecord\\LdapInterface',
        'currentClassName' => 'LdapRecord\\LdapInterface',
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