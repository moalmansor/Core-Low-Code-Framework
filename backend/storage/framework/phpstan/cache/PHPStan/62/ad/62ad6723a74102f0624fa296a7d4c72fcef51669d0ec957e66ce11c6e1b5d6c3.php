<?php declare(strict_types = 1);

// phpinternal-PHPStan\BetterReflection\Reflection\ReflectionClass-datetimeimmutable
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v3-6.73.0.5-dev-master@e4f5f6c-8.3.6-',
   'data' => 
  array (
    'locatedSource' => 
    array (
      'class' => 'PHPStan\\BetterReflection\\SourceLocator\\Located\\InternalLocatedSource',
      'data' => 
      array (
        'name' => 'DateTimeImmutable',
        'filename' => 'phpstorm-stubs:date/date_c.stub',
        'extensionName' => 'date',
        'aliasName' => NULL,
      ),
    ),
    'namespace' => NULL,
    'name' => 'DateTimeImmutable',
    'shortName' => 'DateTimeImmutable',
    'isInterface' => false,
    'isTrait' => false,
    'isEnum' => false,
    'isBackedEnum' => false,
    'modifiers' => 0,
    'docComment' => '/**
 * Representation of date and time.
 *
 * This class behaves the same as DateTime except new objects are returned when modification methods
 * such as DateTime::modify are called.
 *
 * @link https://php.net/manual/en/class.datetimeimmutable.php
 * @since 5.5
 */',
    'attributes' => 
    array (
    ),
    'startLine' => 13,
    'endLine' => 467,
    'startColumn' => 5,
    'endColumn' => 5,
    'parentClassName' => NULL,
    'implementsClassNames' => 
    array (
      0 => 'DateTimeInterface',
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
      '__construct' => 
      array (
        'name' => '__construct',
        'parameters' => 
        array (
          'datetime' => 
          array (
            'name' => 'datetime',
            'default' => 
            array (
              'code' => '"now"',
              'attributes' => 
              array (
                'startLine' => 34,
                'endLine' => 34,
                'startTokenPos' => 62,
                'startFilePos' => 1794,
                'endTokenPos' => 62,
                'endFilePos' => 1798,
              ),
            ),
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 33,
                      'endLine' => 33,
                      'startTokenPos' => 40,
                      'startFilePos' => 1728,
                      'endTokenPos' => 46,
                      'endFilePos' => 1746,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 33,
                      'endLine' => 33,
                      'startTokenPos' => 52,
                      'startFilePos' => 1758,
                      'endTokenPos' => 52,
                      'endFilePos' => 1759,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 33,
            'endLine' => 34,
            'startColumn' => 13,
            'endColumn' => 36,
            'parameterIndex' => 0,
            'isOptional' => true,
          ),
          'timezone' => 
          array (
            'name' => 'timezone',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 36,
                'endLine' => 36,
                'startTokenPos' => 92,
                'startFilePos' => 1967,
                'endTokenPos' => 92,
                'endFilePos' => 1970,
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
                      'name' => 'DateTimeZone',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'DateTimeZone|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 35,
                      'endLine' => 35,
                      'startTokenPos' => 68,
                      'startFilePos' => 1867,
                      'endTokenPos' => 74,
                      'endFilePos' => 1896,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'DateTimeZone\'',
                    'attributes' => 
                    array (
                      'startLine' => 35,
                      'endLine' => 35,
                      'startTokenPos' => 80,
                      'startFilePos' => 1908,
                      'endTokenPos' => 80,
                      'endFilePos' => 1921,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 35,
            'endLine' => 36,
            'startColumn' => 13,
            'endColumn' => 46,
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
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              'from' => 
              array (
                'code' => '\'8.3\'',
                'attributes' => 
                array (
                  'startLine' => 31,
                  'endLine' => 31,
                  'startTokenPos' => 26,
                  'startFilePos' => 1617,
                  'endTokenPos' => 26,
                  'endFilePos' => 1621,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 8 &gt;=8.3.0)<br/>
 * @link https://php.net/manual/en/datetimeimmutable.construct.php
 * @param string $datetime [optional]
 * <p>A date/time string. Valid formats are explained in {@link https://php.net/manual/en/datetime.formats.php Date and Time Formats}.</p>
 * <p>Enter <b>\'now\'</b> here to obtain the current time when using the <em>$timezone</em> parameter.</p>
 * @param null|DateTimeZone $timezone [optional] <p>
 * A {@link https://php.net/manual/en/class.datetimezone.php DateTimeZone} object representing the timezone of <em>$datetime</em>.
 * </p>
 * <p>If <em>$timezone</em> is omitted, the current timezone will be used.</p>
 * <blockquote><p><b>Note</b>:</p><p>
 * The <em>$timezone</em> parameter and the current timezone are ignored when the <em>$datetime</em> parameter either
 * is a UNIX timestamp (e.g. <em>@946684800</em>) or specifies a timezone (e.g. <em>2010-01-28T15:00:00+02:00</em>).
 * </p></blockquote>
 * @throws DateMalformedStringException Emits Exception in case of an error.
 */',
        'startLine' => 31,
        'endLine' => 39,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'add' => 
      array (
        'name' => 'add',
        'parameters' => 
        array (
          'interval' => 
          array (
            'name' => 'interval',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DateInterval',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 50,
            'endLine' => 50,
            'startColumn' => 29,
            'endColumn' => 51,
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
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::add() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 49,
                  'endLine' => 49,
                  'startTokenPos' => 112,
                  'startFilePos' => 2491,
                  'endTokenPos' => 112,
                  'endFilePos' => 2553,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Adds an amount of days, months, years, hours, minutes and seconds
 * @param DateInterval $interval A DateInterval object
 * @return static Returns a new DateTimeImmutable object with the modified data.
 * @link https://php.net/manual/en/datetimeimmutable.add.php
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 48,
        'endLine' => 52,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'createFromFormat' => 
      array (
        'name' => 'createFromFormat',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 142,
                      'endLine' => 142,
                      'startTokenPos' => 162,
                      'startFilePos' => 11203,
                      'endTokenPos' => 168,
                      'endFilePos' => 11221,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 142,
                      'endLine' => 142,
                      'startTokenPos' => 174,
                      'startFilePos' => 11233,
                      'endTokenPos' => 174,
                      'endFilePos' => 11234,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 142,
            'endLine' => 143,
            'startColumn' => 13,
            'endColumn' => 26,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'datetime' => 
          array (
            'name' => 'datetime',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 144,
                      'endLine' => 144,
                      'startTokenPos' => 186,
                      'startFilePos' => 11332,
                      'endTokenPos' => 192,
                      'endFilePos' => 11350,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 144,
                      'endLine' => 144,
                      'startTokenPos' => 198,
                      'startFilePos' => 11362,
                      'endTokenPos' => 198,
                      'endFilePos' => 11363,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 144,
            'endLine' => 145,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'timezone' => 
          array (
            'name' => 'timezone',
            'default' => 
            array (
              'code' => '\\null',
              'attributes' => 
              array (
                'startLine' => 147,
                'endLine' => 147,
                'startTokenPos' => 234,
                'startFilePos' => 11563,
                'endTokenPos' => 234,
                'endFilePos' => 11566,
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
                      'name' => 'DateTimeZone',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'DateTimeZone|null\']',
                    'attributes' => 
                    array (
                      'startLine' => 146,
                      'endLine' => 146,
                      'startTokenPos' => 210,
                      'startFilePos' => 11463,
                      'endTokenPos' => 216,
                      'endFilePos' => 11492,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'DateTimeZone\'',
                    'attributes' => 
                    array (
                      'startLine' => 146,
                      'endLine' => 146,
                      'startTokenPos' => 222,
                      'startFilePos' => 11504,
                      'endTokenPos' => 222,
                      'endFilePos' => 11517,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 146,
            'endLine' => 147,
            'startColumn' => 13,
            'endColumn' => 46,
            'parameterIndex' => 2,
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
                  'name' => 'DateTimeImmutable',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              'from' => 
              array (
                'code' => '\'8.0\'',
                'attributes' => 
                array (
                  'startLine' => 140,
                  'endLine' => 140,
                  'startTokenPos' => 146,
                  'startFilePos' => 11080,
                  'endTokenPos' => 146,
                  'endFilePos' => 11084,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Returns new DateTimeImmutable object formatted according to the specified format
 * @link https://php.net/manual/en/datetimeimmutable.createfromformat.php
 * @param string $format The format that the passed in string should be in. See the formatting
 * options below. In most cases, the same letters as for the date can be used. All fields are
 * initialised with the current date/time. In most cases you would want to reset these to "zero"
 * (the Unix epoch, 1970-01-01 00:00:00 UTC). You do that by including the ! character as first
 * character in your format, or | as your last. Please see the documentation for each character
 * below for more information. The format is parsed from left to right, which means that in some
 * situations the order in which the format characters are present affects the result. In the
 * case of z (the day of the year), it is required that a year has already been parsed, for
 * example through the Y or y characters. Letters that are used for parsing numbers allow a wide
 * range of values, outside of what the logical range would be. For example, the d (day of the
 * month) accepts values in the range from 00 to 99. The only constraint is on the amount of
 * digits. The date/time parser\'s overflow mechanism is used when out-of-range values are given.
 * The examples below show some of this behaviour. This also means that the data parsed for a
 * format letter is greedy, and will read up to the amount of digits its format allows for. That
 * can then also mean that there are no longer enough characters in the datetime string for
 * following format characters. An example on this page also illustrates this issue. The
 * following characters are recognized in the format parameter string format character
 * Description Example parsable values Day --- --- d and j Day of the month, 2 digits with or
 * without leading zeros 01 to 31 or 1 to 31. (2 digit numbers higher than the number of days in
 * the month are accepted, in which case they will make the month overflow. For example using 33
 * with January, means February 2nd) D and l A textual representation of a day Mon through Sun
 * or Sunday through Saturday. If the day name given is different than the day name belonging to
 * a parsed (or default) date is different, then an overflow occurs to the next date with the
 * given day name. See the examples below for an explanation. S English ordinal suffix for the
 * day of the month, 2 characters. It\'s ignored while processing. st, nd, rd or th. z The day of
 * the year (starting from 0); must be preceded by Y or y. 0 through 365. (3 digit numbers
 * higher than the numbers in a year are accepted, in which case they will make the year
 * overflow. For example using 366 with 2022, means January 2nd, 2023) Month --- --- F and M A
 * textual representation of a month, such as January or Sept January through December or Jan
 * through Dec m and n Numeric representation of a month, with or without leading zeros 01
 * through 12 or 1 through 12. (2 digit numbers higher than 12 are accepted, in which case they
 * will make the year overflow. For example using 13 means January in the next year) Year ---
 * --- X and x A full numeric representation of a year, up to 19 digits, optionally prefixed by
 * + or - Examples: 0055, 787, 1999, -2003, +10191 Y A full numeric representation of a year, up
 * to 4 digits Examples: 25 (same as 0025), 787, 1999, 2003 y A two digit representation of a
 * year (which is assumed to be in the range 1970-2069, inclusive) Examples: 99 or 03 (which
 * will be interpreted as 1999 and 2003, respectively) Time --- --- a and A Ante meridiem and
 * Post meridiem am or pm g and h 12-hour format of an hour with or without leading zero 1
 * through 12 or 01 through 12 (2 digit numbers higher than 12 are accepted, in which case they
 * will make the day overflow. For example using 14 means 02 in the next AM/PM period) G and H
 * 24-hour format of an hour with or without leading zeros 0 through 23 or 00 through 23 (2
 * digit numbers higher than 24 are accepted, in which case they will make the day overflow. For
 * example using 26 means 02:00 the next day) i Minutes with leading zeros 00 to 59. (2 digit
 * numbers higher than 59 are accepted, in which case they will make the hour overflow. For
 * example using 66 means :06 the next hour) s Seconds, with leading zeros 00 through 59 (2
 * digit numbers higher than 59 are accepted, in which case they will make the minute overflow.
 * For example using 90 means :30 the next minute) v Fraction in milliseconds (up to three
 * digits) Example: 12 (0.12 seconds), 345 (0.345 seconds) u Fraction in microseconds (up to six
 * digits) Example: 45 (0.45 seconds), 654321 (0.654321 seconds) Timezone --- --- e, O, p, P and
 * T Timezone identifier, or difference to UTC in hours, or difference to UTC with colon between
 * hours and minutes, or timezone abbreviation Examples: UTC, GMT, Atlantic/Azores or +0200 or
 * +02:00 or EST, MDT Full Date/Time --- --- U Seconds since the Unix Epoch (January 1 1970
 * 00:00:00 GMT) Example: 1292177455 Whitespace and Separators --- --- (space) Zero or more
 * spaces, tabs, NBSP (U+A0), or NNBSP (U+202F) characters Example: "\\t", " " # One of the
 * following separation symbol: ;, :, /, ., ,, -, ( or ) Example: / ;, :, /, ., ,, -, ( or ) The
 * specified character. Example: - ? A random byte Example: ^ (Be aware that for UTF-8
 * characters you might need more than one ?. In this case, using * is probably what you want
 * instead) * Random bytes until the next separator or digit Example: * in Y-*-d with the string
 * 2009-aWord-08 will match aWord ! Resets all fields (year, month, day, hour, minute, second,
 * fraction and timezone information) to zero-like values ( 0 for hour, minute, second and
 * fraction, 1 for month and day, 1970 for year and the default timezone) Without !, all fields
 * will be set to the current date and time. | Resets all fields (year, month, day, hour,
 * minute, second, fraction and timezone information) to zero-like values if they have not been
 * parsed yet Y-m-d| will set the year, month and day to the information found in the string to
 * parse, and sets the hour, minute and second to 0. + If this format specifier is present,
 * trailing data in the string will not cause an error, but a warning instead Use
 * DateTimeImmutable::getLastErrors to find out whether trailing data was present. Unrecognized
 * characters in the format string will cause the parsing to fail and an error message is
 * appended to the returned structure. You can query error messages with
 * DateTimeImmutable::getLastErrors. To include literal characters in format, you have to escape
 * them with a backslash (\\). If format does not contain the character ! then portions of the
 * generated date/time which are not specified in format will be set to the current system time.
 * If format contains the character !, then portions of the generated date/time not provided in
 * format, as well as values to the left-hand side of the !, will be set to corresponding values
 * from the Unix epoch. If any time character is parsed, then all other time-related fields are
 * set to "0", unless also parsed. The Unix epoch is 1970-01-01 00:00:00 UTC.
 * @param string $datetime String representing the time.
 * @param null|DateTimeZone $timezone [optional]
 * @return DateTimeImmutable|false Returns a new DateTimeImmutable instance or false on failure.
 * @throws ValueError when the datetime contains NULL-bytes.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 139,
        'endLine' => 150,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'createFromMutable' => 
      array (
        'name' => 'createFromMutable',
        'parameters' => 
        array (
          'object' => 
          array (
            'name' => 'object',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DateTime',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 161,
            'endLine' => 161,
            'startColumn' => 50,
            'endColumn' => 66,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.2\' => \'static\']',
                'attributes' => 
                array (
                  'startLine' => 160,
                  'endLine' => 160,
                  'startTokenPos' => 256,
                  'startFilePos' => 12349,
                  'endTokenPos' => 262,
                  'endFilePos' => 12367,
                ),
              ),
              'default' => 
              array (
                'code' => '\'DateTimeImmutable\'',
                'attributes' => 
                array (
                  'startLine' => 160,
                  'endLine' => 160,
                  'startTokenPos' => 268,
                  'startFilePos' => 12379,
                  'endTokenPos' => 268,
                  'endFilePos' => 12397,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.6.0)<br/>
 * Returns new DateTimeImmutable object encapsulating the given DateTime object
 * @link https://php.net/manual/en/datetimeimmutable.createfrommutable.php
 * @param DateTime $object The mutable DateTime object that you want to convert to an immutable version. This object is not modified, but instead a new DateTimeImmutable object is created containing the same date time and timezone information.
 * @return DateTimeImmutable returns a new DateTimeImmutable instance.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 159,
        'endLine' => 163,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'getLastErrors' => 
      array (
        'name' => 'getLastErrors',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\ArrayShape',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '["warning_count" => "int", "warnings" => "string[]", "error_count" => "int", "errors" => "string[]"]',
                'attributes' => 
                array (
                  'startLine' => 171,
                  'endLine' => 171,
                  'startTokenPos' => 294,
                  'startFilePos' => 12847,
                  'endTokenPos' => 321,
                  'endFilePos' => 12946,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Returns the warnings and errors
 * @link https://php.net/manual/en/datetimeimmutable.getlasterrors.php
 * @return array|false Returns array containing info about warnings and errors.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 171,
        'endLine' => 175,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'modify' => 
      array (
        'name' => 'modify',
        'parameters' => 
        array (
          'modifier' => 
          array (
            'name' => 'modifier',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 193,
                      'endLine' => 193,
                      'startTokenPos' => 407,
                      'startFilePos' => 14270,
                      'endTokenPos' => 413,
                      'endFilePos' => 14288,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 193,
                      'endLine' => 193,
                      'startTokenPos' => 419,
                      'startFilePos' => 14300,
                      'endTokenPos' => 419,
                      'endFilePos' => 14301,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 193,
            'endLine' => 194,
            'startColumn' => 13,
            'endColumn' => 28,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => NULL,
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              'from' => 
              array (
                'code' => '\'8.3\'',
                'attributes' => 
                array (
                  'startLine' => 187,
                  'endLine' => 187,
                  'startTokenPos' => 356,
                  'startFilePos' => 13844,
                  'endTokenPos' => 356,
                  'endFilePos' => 13848,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Pure',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          2 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          3 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '[\'8.4\' => \'DateTimeImmutable\']',
                'attributes' => 
                array (
                  'startLine' => 190,
                  'endLine' => 190,
                  'startTokenPos' => 371,
                  'startFilePos' => 14004,
                  'endTokenPos' => 377,
                  'endFilePos' => 14033,
                ),
              ),
              'default' => 
              array (
                'code' => '\'DateTimeImmutable|false\'',
                'attributes' => 
                array (
                  'startLine' => 190,
                  'endLine' => 190,
                  'startTokenPos' => 383,
                  'startFilePos' => 14045,
                  'endTokenPos' => 383,
                  'endFilePos' => 14069,
                ),
              ),
            ),
          ),
          4 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::modify() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 191,
                  'endLine' => 191,
                  'startTokenPos' => 393,
                  'startFilePos' => 14103,
                  'endTokenPos' => 393,
                  'endFilePos' => 14168,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 8 &gt;=8.3.0)<br/>
 * Alters the timestamp
 * @link https://php.net/manual/en/datetimeimmutable.modify.php
 * @param string $modifier <p>A date/time string. Valid formats are explained in
 * {@link https://php.net/manual/en/datetime.formats.php Date and Time Formats}.</p>
 * @return static|false Returns the newly created object or false on failure.
 * @throws DateMalformedStringException
 * Returns the {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 187,
        'endLine' => 197,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      '__set_state' => 
      array (
        'name' => '__set_state',
        'parameters' => 
        array (
          'array' => 
          array (
            'name' => 'array',
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
            'startLine' => 208,
            'endLine' => 208,
            'startColumn' => 44,
            'endColumn' => 55,
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
            'name' => 'static',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * The __set_state handler
 * @link https://php.net/manual/en/datetimeimmutable.set-state.php
 * @param array $array <p>Initialization array.</p>
 * @return DateTimeImmutable
 * Returns a new instance of a {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 207,
        'endLine' => 210,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'setDate' => 
      array (
        'name' => 'setDate',
        'parameters' => 
        array (
          'year' => 
          array (
            'name' => 'year',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 226,
                      'endLine' => 226,
                      'startTokenPos' => 485,
                      'startFilePos' => 15785,
                      'endTokenPos' => 491,
                      'endFilePos' => 15800,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 226,
                      'endLine' => 226,
                      'startTokenPos' => 497,
                      'startFilePos' => 15812,
                      'endTokenPos' => 497,
                      'endFilePos' => 15813,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 226,
            'endLine' => 227,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'month' => 
          array (
            'name' => 'month',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 228,
                      'endLine' => 228,
                      'startTokenPos' => 509,
                      'startFilePos' => 15906,
                      'endTokenPos' => 515,
                      'endFilePos' => 15921,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 228,
                      'endLine' => 228,
                      'startTokenPos' => 521,
                      'startFilePos' => 15933,
                      'endTokenPos' => 521,
                      'endFilePos' => 15934,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 228,
            'endLine' => 229,
            'startColumn' => 13,
            'endColumn' => 22,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'day' => 
          array (
            'name' => 'day',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 230,
                      'endLine' => 230,
                      'startTokenPos' => 533,
                      'startFilePos' => 16028,
                      'endTokenPos' => 539,
                      'endFilePos' => 16043,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 230,
                      'endLine' => 230,
                      'startTokenPos' => 545,
                      'startFilePos' => 16055,
                      'endTokenPos' => 545,
                      'endFilePos' => 16056,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 230,
            'endLine' => 231,
            'startColumn' => 13,
            'endColumn' => 20,
            'parameterIndex' => 2,
            'isOptional' => false,
          ),
        ),
        'returnsReference' => false,
        'returnType' => 
        array (
          'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
          'data' => 
          array (
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::setDate() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 224,
                  'endLine' => 224,
                  'startTokenPos' => 471,
                  'startFilePos' => 15616,
                  'endTokenPos' => 471,
                  'endFilePos' => 15682,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Sets the date
 * @link https://php.net/manual/en/datetimeimmutable.setdate.php
 * @param int $year <p>Year of the date.</p>
 * @param int $month <p>Month of the date.</p>
 * @param int $day <p>Day of the date.</p>
 * @return static
 * Returns the {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining.
 * Prior to PHP 8.1, <b>FALSE</b> was returned on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 223,
        'endLine' => 234,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'setISODate' => 
      array (
        'name' => 'setISODate',
        'parameters' => 
        array (
          'year' => 
          array (
            'name' => 'year',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 250,
                      'endLine' => 250,
                      'startTokenPos' => 588,
                      'startFilePos' => 17017,
                      'endTokenPos' => 594,
                      'endFilePos' => 17032,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 250,
                      'endLine' => 250,
                      'startTokenPos' => 600,
                      'startFilePos' => 17044,
                      'endTokenPos' => 600,
                      'endFilePos' => 17045,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 250,
            'endLine' => 251,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'week' => 
          array (
            'name' => 'week',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 252,
                      'endLine' => 252,
                      'startTokenPos' => 612,
                      'startFilePos' => 17138,
                      'endTokenPos' => 618,
                      'endFilePos' => 17153,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 252,
                      'endLine' => 252,
                      'startTokenPos' => 624,
                      'startFilePos' => 17165,
                      'endTokenPos' => 624,
                      'endFilePos' => 17166,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 252,
            'endLine' => 253,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'dayOfWeek' => 
          array (
            'name' => 'dayOfWeek',
            'default' => 
            array (
              'code' => '1',
              'attributes' => 
              array (
                'startLine' => 255,
                'endLine' => 255,
                'startTokenPos' => 658,
                'startFilePos' => 17320,
                'endTokenPos' => 658,
                'endFilePos' => 17320,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 254,
                      'endLine' => 254,
                      'startTokenPos' => 636,
                      'startFilePos' => 17259,
                      'endTokenPos' => 642,
                      'endFilePos' => 17274,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 254,
                      'endLine' => 254,
                      'startTokenPos' => 648,
                      'startFilePos' => 17286,
                      'endTokenPos' => 648,
                      'endFilePos' => 17287,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 254,
            'endLine' => 255,
            'startColumn' => 13,
            'endColumn' => 30,
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
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::setISODate() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 248,
                  'endLine' => 248,
                  'startTokenPos' => 574,
                  'startFilePos' => 16842,
                  'endTokenPos' => 574,
                  'endFilePos' => 16911,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Sets the ISO date
 * @link https://php.net/manual/en/class.datetimeimmutable.php
 * @param int $year <p>Year of the date.</p>
 * @param int $week <p>Week of the date.</p>
 * @param int $dayOfWeek [optional] <p>Offset from the first day of the week.</p>
 * @return static
 * Returns the {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining.
 * Prior to PHP 8.1, <b>FALSE</b> was returned on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 247,
        'endLine' => 258,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'setTime' => 
      array (
        'name' => 'setTime',
        'parameters' => 
        array (
          'hour' => 
          array (
            'name' => 'hour',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 276,
                      'endLine' => 276,
                      'startTokenPos' => 699,
                      'startFilePos' => 18370,
                      'endTokenPos' => 705,
                      'endFilePos' => 18385,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 276,
                      'endLine' => 276,
                      'startTokenPos' => 711,
                      'startFilePos' => 18397,
                      'endTokenPos' => 711,
                      'endFilePos' => 18398,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 276,
            'endLine' => 277,
            'startColumn' => 13,
            'endColumn' => 21,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'minute' => 
          array (
            'name' => 'minute',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 278,
                      'endLine' => 278,
                      'startTokenPos' => 723,
                      'startFilePos' => 18491,
                      'endTokenPos' => 729,
                      'endFilePos' => 18506,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 278,
                      'endLine' => 278,
                      'startTokenPos' => 735,
                      'startFilePos' => 18518,
                      'endTokenPos' => 735,
                      'endFilePos' => 18519,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 278,
            'endLine' => 279,
            'startColumn' => 13,
            'endColumn' => 23,
            'parameterIndex' => 1,
            'isOptional' => false,
          ),
          'second' => 
          array (
            'name' => 'second',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 281,
                'endLine' => 281,
                'startTokenPos' => 769,
                'startFilePos' => 18672,
                'endTokenPos' => 769,
                'endFilePos' => 18672,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 280,
                      'endLine' => 280,
                      'startTokenPos' => 747,
                      'startFilePos' => 18614,
                      'endTokenPos' => 753,
                      'endFilePos' => 18629,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 280,
                      'endLine' => 280,
                      'startTokenPos' => 759,
                      'startFilePos' => 18641,
                      'endTokenPos' => 759,
                      'endFilePos' => 18642,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 280,
            'endLine' => 281,
            'startColumn' => 13,
            'endColumn' => 27,
            'parameterIndex' => 2,
            'isOptional' => true,
          ),
          'microsecond' => 
          array (
            'name' => 'microsecond',
            'default' => 
            array (
              'code' => '0',
              'attributes' => 
              array (
                'startLine' => 284,
                'endLine' => 284,
                'startTokenPos' => 807,
                'startFilePos' => 18891,
                'endTokenPos' => 807,
                'endFilePos' => 18891,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
                'isRepeated' => false,
                'arguments' => 
                array (
                  'from' => 
                  array (
                    'code' => '\'7.1\'',
                    'attributes' => 
                    array (
                      'startLine' => 282,
                      'endLine' => 282,
                      'startTokenPos' => 778,
                      'startFilePos' => 18754,
                      'endTokenPos' => 778,
                      'endFilePos' => 18758,
                    ),
                  ),
                ),
              ),
              1 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 283,
                      'endLine' => 283,
                      'startTokenPos' => 785,
                      'startFilePos' => 18828,
                      'endTokenPos' => 791,
                      'endFilePos' => 18843,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 283,
                      'endLine' => 283,
                      'startTokenPos' => 797,
                      'startFilePos' => 18855,
                      'endTokenPos' => 797,
                      'endFilePos' => 18856,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 282,
            'endLine' => 284,
            'startColumn' => 13,
            'endColumn' => 32,
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
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::setTime() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 273,
                  'endLine' => 273,
                  'startTokenPos' => 681,
                  'startFilePos' => 18165,
                  'endTokenPos' => 681,
                  'endFilePos' => 18231,
                ),
              ),
            ),
          ),
          2 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Pure',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Sets the time
 * @link https://php.net/manual/en/datetimeimmutable.settime.php
 * @param int $hour <p> Hour of the time. </p>
 * @param int $minute <p> Minute of the time. </p>
 * @param int $second [optional] <p> Second of the time. </p>
 * @param int $microsecond [optional] <p> Microseconds of the time. Added since 7.1</p>
 * @return static
 * Returns the {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining.
 * Prior to PHP 8.1, <b>FALSE</b> was returned on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 272,
        'endLine' => 287,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'setTimestamp' => 
      array (
        'name' => 'setTimestamp',
        'parameters' => 
        array (
          'timestamp' => 
          array (
            'name' => 'timestamp',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'int\']',
                    'attributes' => 
                    array (
                      'startLine' => 301,
                      'endLine' => 301,
                      'startTokenPos' => 844,
                      'startFilePos' => 19754,
                      'endTokenPos' => 850,
                      'endFilePos' => 19769,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 301,
                      'endLine' => 301,
                      'startTokenPos' => 856,
                      'startFilePos' => 19781,
                      'endTokenPos' => 856,
                      'endFilePos' => 19782,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 301,
            'endLine' => 302,
            'startColumn' => 13,
            'endColumn' => 26,
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
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::setTimestamp() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 299,
                  'endLine' => 299,
                  'startTokenPos' => 830,
                  'startFilePos' => 19575,
                  'endTokenPos' => 830,
                  'endFilePos' => 19646,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Sets the date and time based on an Unix timestamp
 * @link https://php.net/manual/en/datetimeimmutable.settimestamp.php
 * @param int $timestamp <p>Unix timestamp representing the date.</p>
 * @return static
 * Returns the {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining.
 * Prior to PHP 8.1, <b>FALSE</b> was returned on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 298,
        'endLine' => 305,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'setTimezone' => 
      array (
        'name' => 'setTimezone',
        'parameters' => 
        array (
          'timezone' => 
          array (
            'name' => 'timezone',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DateTimeZone',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 321,
            'endLine' => 321,
            'startColumn' => 37,
            'endColumn' => 59,
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
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::setTimezone() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 320,
                  'endLine' => 320,
                  'startTokenPos' => 885,
                  'startFilePos' => 20583,
                  'endTokenPos' => 885,
                  'endFilePos' => 20653,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Sets the time zone
 * @link https://php.net/manual/en/datetimeimmutable.settimezone.php
 * @param DateTimeZone $timezone <p>
 * A {@link https://php.net/manual/en/class.datetimezone.php DateTimeZone} object representing the
 * desired time zone.
 * </p>
 * @return static
 * Returns the {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining.
 * Prior to PHP 8.1, <b>FALSE</b> was returned on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 319,
        'endLine' => 323,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'sub' => 
      array (
        'name' => 'sub',
        'parameters' => 
        array (
          'interval' => 
          array (
            'name' => 'interval',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DateInterval',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 338,
            'endLine' => 338,
            'startColumn' => 29,
            'endColumn' => 51,
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
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'NoDiscard',
            'isRepeated' => false,
            'arguments' => 
            array (
              'message' => 
              array (
                'code' => '"as DateTimeImmutable::sub() does not modify the object itself"',
                'attributes' => 
                array (
                  'startLine' => 337,
                  'endLine' => 337,
                  'startTokenPos' => 919,
                  'startFilePos' => 21547,
                  'endTokenPos' => 919,
                  'endFilePos' => 21609,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Subtracts an amount of days, months, years, hours, minutes and seconds
 * @link https://php.net/manual/en/datetimeimmutable.sub.php
 * @param DateInterval $interval <p>
 * A {@link https://php.net/manual/en/class.dateinterval.php DateInterval} object
 * </p>
 * @return static Returns a new DateTimeImmutable object with the modified data.
 * @throws DateInvalidOperationException
 * Returns the {@link https://php.net/manual/en/class.datetimeimmutable.php DateTimeImmutable} object for method chaining or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 336,
        'endLine' => 340,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'diff' => 
      array (
        'name' => 'diff',
        'parameters' => 
        array (
          'targetObject' => 
          array (
            'name' => 'targetObject',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DateTimeInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'DateTimeInterface\']',
                    'attributes' => 
                    array (
                      'startLine' => 354,
                      'endLine' => 354,
                      'startTokenPos' => 957,
                      'startFilePos' => 22451,
                      'endTokenPos' => 963,
                      'endFilePos' => 22480,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 354,
                      'endLine' => 354,
                      'startTokenPos' => 969,
                      'startFilePos' => 22492,
                      'endTokenPos' => 969,
                      'endFilePos' => 22493,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 354,
            'endLine' => 355,
            'startColumn' => 13,
            'endColumn' => 43,
            'parameterIndex' => 0,
            'isOptional' => false,
          ),
          'absolute' => 
          array (
            'name' => 'absolute',
            'default' => 
            array (
              'code' => '\\false',
              'attributes' => 
              array (
                'startLine' => 357,
                'endLine' => 357,
                'startTokenPos' => 1003,
                'startFilePos' => 22670,
                'endTokenPos' => 1003,
                'endFilePos' => 22674,
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'bool\']',
                    'attributes' => 
                    array (
                      'startLine' => 356,
                      'endLine' => 356,
                      'startTokenPos' => 981,
                      'startFilePos' => 22608,
                      'endTokenPos' => 987,
                      'endFilePos' => 22624,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 356,
                      'endLine' => 356,
                      'startTokenPos' => 993,
                      'startFilePos' => 22636,
                      'endTokenPos' => 993,
                      'endFilePos' => 22637,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 356,
            'endLine' => 357,
            'startColumn' => 13,
            'endColumn' => 34,
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
            'name' => 'DateInterval',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Returns the difference between two DateTime objects
 * @link https://php.net/manual/en/datetime.diff.php
 * @param DateTimeInterface $targetObject <p>The date to compare to.</p>
 * @param bool $absolute [optional] <p>Should the interval be forced to be positive?</p>
 * @return DateInterval
 * The {@link https://php.net/manual/en/class.dateinterval.php DateInterval} object representing the
 * difference between the two dates.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 352,
        'endLine' => 360,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'format' => 
      array (
        'name' => 'format',
        'parameters' => 
        array (
          'format' => 
          array (
            'name' => 'format',
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
              0 => 
              array (
                'name' => 'JetBrains\\PhpStorm\\Internal\\LanguageLevelTypeAware',
                'isRepeated' => false,
                'arguments' => 
                array (
                  0 => 
                  array (
                    'code' => '[\'8.0\' => \'string\']',
                    'attributes' => 
                    array (
                      'startLine' => 376,
                      'endLine' => 376,
                      'startTokenPos' => 1037,
                      'startFilePos' => 23439,
                      'endTokenPos' => 1043,
                      'endFilePos' => 23457,
                    ),
                  ),
                  'default' => 
                  array (
                    'code' => '\'\'',
                    'attributes' => 
                    array (
                      'startLine' => 376,
                      'endLine' => 376,
                      'startTokenPos' => 1049,
                      'startFilePos' => 23469,
                      'endTokenPos' => 1049,
                      'endFilePos' => 23470,
                    ),
                  ),
                ),
              ),
            ),
            'startLine' => 376,
            'endLine' => 377,
            'startColumn' => 13,
            'endColumn' => 26,
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Pure',
            'isRepeated' => false,
            'arguments' => 
            array (
              0 => 
              array (
                'code' => '\\true',
                'attributes' => 
                array (
                  'startLine' => 373,
                  'endLine' => 373,
                  'startTokenPos' => 1019,
                  'startFilePos' => 23280,
                  'endTokenPos' => 1019,
                  'endFilePos' => 23283,
                ),
              ),
            ),
          ),
          1 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Returns date formatted according to given format
 * @link https://php.net/manual/en/datetime.format.php
 * @param string $format <p>
 * Format accepted by  {@link https://php.net/manual/en/function.date.php date()}.
 * </p>
 * @return string
 * Returns the formatted date string on success.
 * Prior to PHP 8.1, <b>FALSE</b> was returned on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 373,
        'endLine' => 380,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'getOffset' => 
      array (
        'name' => 'getOffset',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Returns the timezone offset
 * @link https://php.net/manual/en/datetime.getoffset.php
 * @return int
 * Returns the timezone offset in seconds from UTC on success.
 * Prior to PHP 8.1, <b>FALSE</b> was returned on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 390,
        'endLine' => 393,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'getTimestamp' => 
      array (
        'name' => 'getTimestamp',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Gets the Unix timestamp
 * @link https://php.net/manual/en/datetime.gettimestamp.php
 * @return int
 * Returns the Unix timestamp representing the date.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 402,
        'endLine' => 405,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'getTimezone' => 
      array (
        'name' => 'getTimezone',
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
                  'name' => 'DateTimeZone',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * Return time zone relative to given DateTime
 * @link https://php.net/manual/en/datetime.gettimezone.php
 * @return DateTimeZone|false
 * Returns a {@link https://php.net/manual/en/class.datetimezone.php DateTimeZone} object on success
 * or <b>FALSE</b> on failure.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 415,
        'endLine' => 418,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      '__wakeup' => 
      array (
        'name' => '__wakeup',
        'parameters' => 
        array (
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\TentativeType',
            'isRepeated' => false,
            'arguments' => 
            array (
            ),
          ),
          1 => 
          array (
            'name' => 'Deprecated',
            'isRepeated' => false,
            'arguments' => 
            array (
              'since' => 
              array (
                'code' => '\'8.5\'',
                'attributes' => 
                array (
                  'startLine' => 427,
                  'endLine' => 427,
                  'startTokenPos' => 1143,
                  'startFilePos' => 25329,
                  'endTokenPos' => 1143,
                  'endFilePos' => 25333,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * (PHP 5 &gt;=5.5.0)<br/>
 * The __wakeup handler
 * @link https://php.net/manual/en/datetime.wakeup.php
 * @return void Initializes a DateTime object.
 * @betterReflectionTentativeReturnType
 */',
        'startLine' => 426,
        'endLine' => 430,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      'createFromInterface' => 
      array (
        'name' => 'createFromInterface',
        'parameters' => 
        array (
          'object' => 
          array (
            'name' => 'object',
            'default' => NULL,
            'type' => 
            array (
              'class' => 'PHPStan\\BetterReflection\\Reflection\\ReflectionNamedType',
              'data' => 
              array (
                'name' => 'DateTimeInterface',
                'isIdentifier' => false,
              ),
            ),
            'isVariadic' => false,
            'byRef' => false,
            'isPromoted' => false,
            'attributes' => 
            array (
            ),
            'startLine' => 440,
            'endLine' => 440,
            'startColumn' => 52,
            'endColumn' => 77,
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
            'name' => 'DateTimeImmutable',
            'isIdentifier' => false,
          ),
        ),
        'attributes' => 
        array (
        ),
        'docComment' => '/**
 * Returns new DateTimeImmutable object encapsulating the given DateTimeInterface object
 * @link https://php.net/manual/en/datetimeimmutable.createfrominterface.php
 * @param DateTimeInterface $object The DateTimeInterface object that needs to be converted to
 * an immutable version. This object is not modified, but instead a new DateTimeImmutable object
 * is created containing the same date, time, and timezone information.
 * @return static Returns a new DateTimeImmutable instance.
 * @since 8.0
 */',
        'startLine' => 440,
        'endLine' => 442,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 17,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      '__serialize' => 
      array (
        'name' => '__serialize',
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
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              'from' => 
              array (
                'code' => '\'8.2\'',
                'attributes' => 
                array (
                  'startLine' => 451,
                  'endLine' => 451,
                  'startTokenPos' => 1192,
                  'startFilePos' => 26437,
                  'endTokenPos' => 1192,
                  'endFilePos' => 26441,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * Serialize a DateTime
 *
 * The __serialize() handler.
 *
 * @link https://php.net/manual/en/datetime.serialize.php
 * @return array The serialized representation of the DateTime object.
 */',
        'startLine' => 451,
        'endLine' => 454,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
        'aliasName' => NULL,
      ),
      '__unserialize' => 
      array (
        'name' => '__unserialize',
        'parameters' => 
        array (
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
            'startLine' => 464,
            'endLine' => 464,
            'startColumn' => 39,
            'endColumn' => 49,
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
            'name' => 'void',
            'isIdentifier' => true,
          ),
        ),
        'attributes' => 
        array (
          0 => 
          array (
            'name' => 'JetBrains\\PhpStorm\\Internal\\PhpStormStubsElementAvailable',
            'isRepeated' => false,
            'arguments' => 
            array (
              'from' => 
              array (
                'code' => '\'8.2\'',
                'attributes' => 
                array (
                  'startLine' => 463,
                  'endLine' => 463,
                  'startTokenPos' => 1219,
                  'startFilePos' => 26829,
                  'endTokenPos' => 1219,
                  'endFilePos' => 26833,
                ),
              ),
            ),
          ),
        ),
        'docComment' => '/**
 * Unserialize an Datetime
 *
 * The __unserialize() handler.
 *
 * @link https://php.net/manual/en/datetime.unserialize.php
 * @param array $data The serialized DateTime.
 */',
        'startLine' => 463,
        'endLine' => 466,
        'startColumn' => 9,
        'endColumn' => 9,
        'couldThrow' => false,
        'isClosure' => false,
        'isGenerator' => false,
        'isVariadic' => false,
        'modifiers' => 1,
        'namespace' => NULL,
        'declaringClassName' => 'DateTimeImmutable',
        'implementingClassName' => 'DateTimeImmutable',
        'currentClassName' => 'DateTimeImmutable',
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