<?php declare(strict_types = 1);

// ftm-/home/user/Core-Low-Code-Framework/backend/vendor/laravel/framework/src/Illuminate/Mail/MailManager.php
return \PHPStan\Cache\CacheItem::__set_state(array(
   'variableKey' => 'v6-2.3.5',
   'data' => 
  array (
    0 => 
    array (
      '017ad7d9c77e1de46137a00a10b9ec24' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
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
      'e69a305165aec5ed69b17bc02945ecdd' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => '__construct',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'da1e615a5fdf8282aa241e4491007b95' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'mailer',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '8a19640dd951e64a91032e165aa62bcf' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'driver',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '616a060ebf096f0e13ae96c15d71fd24' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'get',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'ba6c496bf71ec9c492112ebb3cea605f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'resolve',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '610205193ca7ce20a309196d8c527051' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'build',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'c5d8ba5b0814108e8eafa51abe2df90d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createSymfonyTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'a13c6c54413f1fb87031c92d1ca8a709' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createSmtpTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '94447cd5de6028321a0660a48e118303' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'configureSmtpTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'a82e74ac2e7e4b534c55fa2df34d27ae' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createSendmailTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '0f5225e203839959216053035ca844d8' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createSesTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'e108fca177f61c304214d6cad6661772' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createSesV2Transport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '63ace1102c70c2fe609a0107349ad5a1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'addSesCredentials',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'c19ea97445908da73aea8c92b4eaff38' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createResendTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '7d6eefd55278b005cfecbc8b66e01cc5' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createMailTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '85671ea68268b0d2bc48fdc887a0844a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createMailgunTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '286975bc48d11d2f9cc3703b37e98125' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createPostmarkTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'b3150fce5956b3c60cb90193aee5015d' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createFailoverTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'bc328627e5c87f4518d71b896183fbcb' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createRoundrobinTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '5ae0df3924ebf5bf7718a5394f04667f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createRoundrobinTransportOfClass',
         'templatePhpDocNodes' => 
        array (
          'TClass' => 
          array (
            0 => '@template',
            1 => 
            \PHPStan\PhpDocParser\Ast\PhpDoc\TemplateTagValueNode::__set_state(array(
               'name' => 'TClass',
               'bound' => 
              \PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode::__set_state(array(
                 'name' => '\\Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
                 'attributes' => 
                array (
                  'startLine' => 4,
                  'endLine' => 4,
                ),
              )),
               'default' => NULL,
               'lowerBound' => NULL,
               'description' => '',
               'attributes' => 
              array (
                'startLine' => 4,
                'endLine' => 4,
              ),
            )),
          ),
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '7a955abbc607ddd7b6f30c5a651de307' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createLogTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '27aa7b3725dca872662ef6d8ee9caca1' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'createArrayTransport',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '52fa01ca6172831f5136e7c217a914e2' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'getHttpClient',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '39019c1db534107e0dbb3c4163f16093' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'setGlobalAddress',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'b44675140c94c831b5c92db4b3fc6366' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'getConfig',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'a082800e232a4f01010cde70ad409f5f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'getDefaultDriver',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'ef4cd6effddea42d8f52450ef281084f' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'setDefaultDriver',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '3e32b419841c29354e5dde8c99cdc5d4' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'purge',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'd37da37e87ca20ac78a8ab9d4592e1d6' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'extend',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '7b3a90b2cfa1f28f2b6a6ab14fd5b17a' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'getApplication',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '14e677705b70b82bcadb8915db8d94dc' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'setApplication',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      'fec73c8242f00229a0a04dd6c329790e' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => 'forgetMailers',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '6e100062243ab8186cee903d32883809' => 
      \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
         'namespace' => 'Illuminate\\Mail',
         'uses' => 
        array (
          'sesclient' => 'Aws\\Ses\\SesClient',
          'sesv2client' => 'Aws\\SesV2\\SesV2Client',
          'closure' => 'Closure',
          'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
          'logmanager' => 'Illuminate\\Log\\LogManager',
          'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
          'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
          'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
          'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
          'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
          'arr' => 'Illuminate\\Support\\Arr',
          'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
          'str' => 'Illuminate\\Support\\Str',
          'invalidargumentexception' => 'InvalidArgumentException',
          'loggerinterface' => 'Psr\\Log\\LoggerInterface',
          'resend' => 'Resend',
          'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
          'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
          'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
          'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
          'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
          'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
          'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
          'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
          'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
          'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
        ),
         'className' => 'Illuminate\\Mail\\MailManager',
         'functionName' => '__call',
         'templatePhpDocNodes' => 
        array (
        ),
         'parent' => 
        \PHPStan\Analyser\IntermediaryNameScope::__set_state(array(
           'namespace' => 'Illuminate\\Mail',
           'uses' => 
          array (
            'sesclient' => 'Aws\\Ses\\SesClient',
            'sesv2client' => 'Aws\\SesV2\\SesV2Client',
            'closure' => 'Closure',
            'factorycontract' => 'Illuminate\\Contracts\\Mail\\Factory',
            'logmanager' => 'Illuminate\\Log\\LogManager',
            'arraytransport' => 'Illuminate\\Mail\\Transport\\ArrayTransport',
            'logtransport' => 'Illuminate\\Mail\\Transport\\LogTransport',
            'resendtransport' => 'Illuminate\\Mail\\Transport\\ResendTransport',
            'sestransport' => 'Illuminate\\Mail\\Transport\\SesTransport',
            'sesv2transport' => 'Illuminate\\Mail\\Transport\\SesV2Transport',
            'arr' => 'Illuminate\\Support\\Arr',
            'configurationurlparser' => 'Illuminate\\Support\\ConfigurationUrlParser',
            'str' => 'Illuminate\\Support\\Str',
            'invalidargumentexception' => 'InvalidArgumentException',
            'loggerinterface' => 'Psr\\Log\\LoggerInterface',
            'resend' => 'Resend',
            'httpclient' => 'Symfony\\Component\\HttpClient\\HttpClient',
            'mailguntransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Mailgun\\Transport\\MailgunTransportFactory',
            'postmarktransportfactory' => 'Symfony\\Component\\Mailer\\Bridge\\Postmark\\Transport\\PostmarkTransportFactory',
            'dsn' => 'Symfony\\Component\\Mailer\\Transport\\Dsn',
            'failovertransport' => 'Symfony\\Component\\Mailer\\Transport\\FailoverTransport',
            'roundrobintransport' => 'Symfony\\Component\\Mailer\\Transport\\RoundRobinTransport',
            'sendmailtransport' => 'Symfony\\Component\\Mailer\\Transport\\SendmailTransport',
            'esmtptransport' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransport',
            'esmtptransportfactory' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\EsmtpTransportFactory',
            'socketstream' => 'Symfony\\Component\\Mailer\\Transport\\Smtp\\Stream\\SocketStream',
          ),
           'className' => 'Illuminate\\Mail\\MailManager',
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
      '/home/user/Core-Low-Code-Framework/backend/vendor/laravel/framework/src/Illuminate/Mail/MailManager.php' => '0878d927ce67714936599ea1c9a4421f2ccc69c4e1674430053d330cefecc2f8',
    ),
  ),
));