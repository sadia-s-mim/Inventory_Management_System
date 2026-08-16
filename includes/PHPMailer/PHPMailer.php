<?php

namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const CHARSET_ASCII = 'us-ascii';
    const CHARSET_ISO88591 = 'iso-8859-1';
    const CHARSET_UTF8 = 'utf-8';

    const CONTENT_TYPE_PLAINTEXT = 'text/plain';
    const CONTENT_TYPE_TEXT_CALENDAR = 'text/calendar';
    const CONTENT_TYPE_TEXT_HTML = 'text/html';
    const CONTENT_TYPE_MULTIPART_ALTERNATIVE = 'multipart/alternative';
    const CONTENT_TYPE_MULTIPART_MIXED = 'multipart/mixed';
    const CONTENT_TYPE_MULTIPART_RELATED = 'multipart/related';

    const ENCODING_7BIT = '7bit';
    const ENCODING_8BIT = '8bit';
    const ENCODING_BASE64 = 'base64';
    const ENCODING_BINARY = 'binary';
    const ENCODING_QUOTED_PRINTABLE = 'quoted-printable';

    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';

    const ICAL_METHOD_REQUEST = 'REQUEST';
    const ICAL_METHOD_PUBLISH = 'PUBLISH';
    const ICAL_METHOD_REPLY = 'REPLY';
    const ICAL_METHOD_ADD = 'ADD';
    const ICAL_METHOD_CANCEL = 'CANCEL';
    const ICAL_METHOD_REFRESH = 'REFRESH';
    const ICAL_METHOD_COUNTER = 'COUNTER';
    const ICAL_METHOD_DECLINECOUNTER = 'DECLINECOUNTER';
    const RFC822_DATE_FORMAT = 'D, j M Y H:i:s O';

    public $Priority;
    public $CharSet = self::CHARSET_ISO88591;
    public $ContentType = self::CONTENT_TYPE_PLAINTEXT;
    public $Encoding = self::ENCODING_8BIT;
    public $ErrorInfo = '';
    public $From = '';
    public $FromName = '';
    public $Sender = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $Ical = '';
    protected static $IcalMethods = [
        self::ICAL_METHOD_REQUEST,
        self::ICAL_METHOD_PUBLISH,
        self::ICAL_METHOD_REPLY,
        self::ICAL_METHOD_ADD,
        self::ICAL_METHOD_CANCEL,
        self::ICAL_METHOD_REFRESH,
        self::ICAL_METHOD_COUNTER,
        self::ICAL_METHOD_DECLINECOUNTER,
    ];
    protected $MIMEBody = '';
    protected $MIMEHeader = '';
    protected $mailHeader = '';
    public $WordWrap = 0;
    public $Mailer = 'mail';
    public $Sendmail = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = '';
    public $Hostname = '';
    public $MessageID = '';
    public $MessageDate = '';
    public $Host = 'localhost';
    public $Port = 25;
    public $Helo = '';
    public $SMTPSecure = '';
    public $SMTPAutoTLS = true;
    public $SMTPAuth = false;
    public $SMTPOptions = [];
    public $Username = '';
    public $Password = '';
    public $AuthType = '';
    protected $SMTPXClient = [];
    protected $oauth;
    public $Timeout = 300;
    public $dsn = '';
    public $SMTPDebug = 0;
    public $Debugoutput = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo = false;
    protected $SingleToArray = [];
    public $do_verp = false;
    public $AllowEmpty = false;
    public $DKIM_selector = '';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain = '';
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];
    public $DKIM_private = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer = '';
    public static $validator = 'php';
    protected $smtp;
    protected $to = [];
    protected $cc = [];
    protected $bcc = [];
    protected $ReplyTo = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue = [];
    public $UseSMTPUTF8 = false;
    protected $attachment = [];
    protected $CustomHeader = [];
    protected $lastMessageID = '';
    protected $message_type = '';
    protected $boundary = [];
    protected static $language = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass = '';
    protected $exceptions = false;
    protected $uniqueid = '';
    const VERSION = '7.1.1';
    const STOP_MESSAGE = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;
    const CRLF = "\r\n";
    const FWS = ' ';
    protected static $LE = self::CRLF;
    const MAIL_MAX_LINE_LENGTH = 63;
    const MAX_LINE_LENGTH = 998;
    const STD_LINE_LENGTH = 76;

    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->exceptions = (bool) $exceptions;
        }
        $this->Debugoutput = (strpos(PHP_SAPI, 'cli') !== false ? 'echo' : 'html');
    }

    public function __destruct()
    {
        $this->smtpClose();
    }

    private function mailPassthru($to, $subject, $body, $header, $params)
    {
        if ((int)ini_get('mbstring.func_overload') & 1) {
            $subject = $this->secureHeader($subject);
        } else {
            $subject = $this->encodeHeader($this->secureHeader($subject));
        }
        $this->edebug('Sending with mail()');
        $this->edebug('Sendmail path: ' . ini_get('sendmail_path'));
        $this->edebug("Envelope sender: {$this->Sender}");
        $this->edebug("To: {$to}");
        $this->edebug("Subject: {$subject}");
        $this->edebug("Headers: {$header}");
        if (!$this->UseSendmailOptions || null === $params) {
            $result = @mail($to, $subject, $body, $header);
        } else {
            $this->edebug("Additional params: {$params}");
            $result = @mail($to, $subject, $body, $header, $params);
        }
        $this->edebug('Result: ' . ($result ? 'true' : 'false'));
        return $result;
    }

    protected function edebug($str)
    {
        if ($this->SMTPDebug <= 0) {
            return;
        }
        if ($this->Debugoutput instanceof \Psr\Log\LoggerInterface) {
            $this->Debugoutput->debug(rtrim($str, "\r\n"));
            return;
        }
        if (is_callable($this->Debugoutput) && !in_array($this->Debugoutput, ['error_log', 'html', 'echo'])) {
            call_user_func($this->Debugoutput, $str, $this->SMTPDebug);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo htmlentities(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                ), "<br>\n";
                break;
            case 'echo':
            default:
                $str = preg_replace('/\r\n|\r/m', "\n", $str);
                echo gmdate('Y-m-d H:i:s'),
                "\t",
                trim(
                    str_replace(
                        "\n",
                        "\n                   \t                  ",
                        trim($str)
                    )
                ),
                "\n";
        }
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = static::CONTENT_TYPE_TEXT_HTML;
        } else {
            $this->ContentType = static::CONTENT_TYPE_PLAINTEXT;
        }
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
    }

    public function isMail()
    {
        $this->Mailer = 'mail';
    }

    private function parseSendmailPath($sendmailPath)
    {
        $sendmailPath = trim((string)$sendmailPath);
        if ($sendmailPath === '') {
            return $sendmailPath;
        }

        $parts = preg_split('/\s+/', $sendmailPath);
        if (empty($parts)) {
            return $sendmailPath;
        }

        $command = array_shift($parts);
        $remainder = [];

        for ($i = 0; $i < count($parts); ++$i) {
            $part = $parts[$i];
            if (preg_match('/^-(i|oi|t)$/', $part, $matches)) {
                continue;
            }
            if (preg_match('/^-f(.*)$/', $part, $matches)) {
                $address = $matches[1];
                if ($address === '' && isset($parts[$i + 1]) && strpos($parts[$i + 1], '-') !== 0) {
                    $address = $parts[++$i];
                }
                $this->Sender = $address;
                continue;
            }

            $remainder[] = $part;
        }

        if (!empty($remainder)) {
            $command .= ' ' . implode(' ', $remainder);
        }

        return $command;
    }

    public function isSendmail()
    {
        $ini_sendmail_path = ini_get('sendmail_path');

        if (false === stripos($ini_sendmail_path, 'sendmail')) {
            $ini_sendmail_path = '/usr/sbin/sendmail';
        }
        $this->Sendmail = $this->parseSendmailPath($ini_sendmail_path);
        $this->Mailer = 'sendmail';
    }

    public function isQmail()
    {
        $ini_sendmail_path = ini_get('sendmail_path');

        if (false === stripos($ini_sendmail_path, 'qmail')) {
            $ini_sendmail_path = '/var/qmail/bin/qmail-inject';
        }
        $this->Sendmail = $this->parseSendmailPath($ini_sendmail_path);
        $this->Mailer = 'qmail';
    }

    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }

    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }

    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }

    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $pos = false;
        if ($address !== null) {
            $address = trim($address);
            $pos = strrpos($address, '@');
        }
        if (false === $pos) {
            $error_message = sprintf(
                '%s (%s): %s',
                self::lang('invalid_address'),
                $kind,
                $address
            );
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }

            return false;
        }
        if ($name !== null && is_string($name)) {
            $name = trim(preg_replace('/[\r\n]+/', '', $name));
        } else {
            $name = '';
        }
        $params = [$kind, $address, $name];
        if ($this->has8bitChars(substr($address, ++$pos))) {
            if (static::idnSupported()) {
                if ('Reply-To' !== $kind) {
                    if (!array_key_exists($address, $this->RecipientsQueue)) {
                        $this->RecipientsQueue[$address] = $params;

                        return true;
                    }
                } elseif (!array_key_exists($address, $this->ReplyToQueue)) {
                    $this->ReplyToQueue[$address] = $params;

                    return true;
                }
            }
            return false;
        }

        return call_user_func_array([$this, 'addAnAddress'], $params);
    }

    public function setBoundaries()
    {
        $this->uniqueid = $this->generateId();
        $this->boundary[1] = 'b1=_' . $this->uniqueid;
        $this->boundary[2] = 'b2=_' . $this->uniqueid;
        $this->boundary[3] = 'b3=_' . $this->uniqueid;
    }

    protected function addAnAddress($kind, $address, $name = '')
    {
        if (
            self::$validator === 'php' &&
            ((bool) preg_match('/[\x80-\xFF]/', $address))
        ) {
            $this->CharSet = self::CHARSET_UTF8;
            self::$validator = 'eai';
        }
        if (!in_array($kind, ['to', 'cc', 'bcc', 'Reply-To'])) {
            $error_message = sprintf(
                '%s: %s',
                self::lang('Invalid recipient kind'),
                $kind
            );
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }

            return false;
        }
        if (!static::validateAddress($address)) {
            $error_message = sprintf(
                '%s (%s): %s',
                self::lang('invalid_address'),
                $kind,
                $address
            );
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }

            return false;
        }
        if ('Reply-To' !== $kind) {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[strtolower($address)] = true;

                return true;
            }
        } else {
            foreach ($this->ReplyTo as $replyTo) {
                if (0 === strcasecmp($replyTo[0], $address)) {
                    return false;
                }
            }
            $this->ReplyTo[] = [$address, $name];

            return true;
        }
        return false;
    }

    public static function parseAddresses($addrstr, $useimap = null, $charset = self::CHARSET_ISO88591)
    {
        if ($useimap) {
            trigger_error(self::lang('deprecated_argument') . '$useimap', E_USER_DEPRECATED);
        }
        $addresses = [];
        if ($useimap !== false && function_exists('imap_rfc822_parse_adrlist')) {
            $list = imap_rfc822_parse_adrlist($addrstr, '');
            imap_errors();
            foreach ($list as $address) {
                if (
                    '.SYNTAX-ERROR.' !== $address->host &&
                    static::validateAddress($address->mailbox . '@' . $address->host)
                ) {
                    if (
                        property_exists($address, 'personal')
                        && is_string($address->personal)
                        && $address->personal !== ''
                    ) {
                        $address->personal = static::decodeHeader($address->personal, $charset);
                    }

                    $addresses[] = [
                        'name' => (property_exists($address, 'personal') ? $address->personal : ''),
                        'address' => $address->mailbox . '@' . $address->host,
                    ];
                }
            }
        } else {
            $addresses = static::parseSimplerAddresses($addrstr, $charset);
        }

        return $addresses;
    }

    protected static function parseSimplerAddresses($addrstr, $charset)
    {
        trigger_error(self::lang('imap_recommended'), E_USER_NOTICE);

        $addresses = [];
        $list = explode(',', $addrstr);
        foreach ($list as $address) {
            $address = trim($address);
            if (strpos($address, '<') === false) {
                if (static::validateAddress($address)) {
                    $addresses[] = [
                        'name' => '',
                        'address' => $address,
                    ];
                }
            } else {
                $parsed = static::parseEmailString($address);
                $email = $parsed['email'];
                if (static::validateAddress($email)) {
                    $name = static::decodeHeader($parsed['name'], $charset);
                    $addresses[] = [
                        'name' => trim($name, '\'" '),
                        'address' => $email,
                    ];
                }
            }
        }

        return $addresses;
    }

    private static function parseEmailString($input)
    {
        $input = trim((string)$input);

        if ($input === '') {
            return ['name' => '', 'email' => ''];
        }

        $pattern = '/^\s*(?:(?:"([^"]*)"|\'([^\']*)\'|([^<]*?))\s*)?<\s*([^>]+)\s*>\s*$/';
        if (preg_match($pattern, $input, $matches)) {
            $name = '';
            if (isset($matches[1]) && $matches[1] !== '') {
                $name = $matches[1];
            } elseif (isset($matches[2]) && $matches[2] !== '') {
                $name = $matches[2];
            } elseif (isset($matches[3])) {
                $name = trim($matches[3]);
            }

            return ['name' => $name, 'email' => trim($matches[4])];
        }

        return ['name' => '', 'email' => $input];
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        if (is_null($name)) {
            $name = '';
        }
        $address = trim((string)$address);
        $name = trim(preg_replace('/[\r\n]+/', '', $name));
        $pos = strrpos($address, '@');
        if (
            (false === $pos)
            || ((!$this->has8bitChars(substr($address, ++$pos)) || !static::idnSupported())
            && !static::validateAddress($address))
        ) {
            $error_message = sprintf(
                '%s (From): %s',
                self::lang('invalid_address'),
                $address
            );
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) {
                throw new Exception($error_message);
            }

            return false;
        }
        $this->From = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }

        return true;
    }

    public function getLastMessageID()
    {
        return $this->lastMessageID;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        if (null === $patternselect) {
            $patternselect = static::$validator;
        }
        if (is_callable($patternselect) && !is_string($patternselect)) {
            return call_user_func($patternselect, $address);
        }
        if (strpos($address, "\n") !== false || strpos($address, "\r") !== false) {
            return false;
        }
        switch ($patternselect) {
            case 'pcre':
            case 'pcre8':
                return (bool) preg_match(
                    '/^(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){255,})(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){65,}@)' .
                    '((?>(?>(?>((?>(?>(?>\x0D\x0A)?[\t ])+|(?>[\t ]*\x0D\x0A)?[\t ]+)?)(\((?>(?2)' .
                    '(?>[\x01-\x08\x0B\x0C\x0E-\'*-\[\]-\x7F]|\\\[\x00-\x7F]|(?3)))*(?2)\)))+(?2))|(?2))?)' .
                    '([!#-\'*+\/-9=?^-~-]+|"(?>(?2)(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\x7F]))*' .
                    '(?2)")(?>(?1)\.(?1)(?4))*(?1)@(?!(?1)[a-z0-9-]{64,})(?1)(?>([a-z0-9](?>[a-z0-9-]*[a-z0-9])?)' .
                    '(?>(?1)\.(?!(?1)[a-z0-9-]{64,})(?1)(?5)){0,126}|\[(?:(?>IPv6:(?>([a-f0-9]{1,4})(?>:(?6)){7}' .
                    '|(?!(?:.*[a-f0-9][:\]]){8,})((?6)(?>:(?6)){0,6})?::(?7)?))|(?>(?>IPv6:(?>(?6)(?>:(?6)){5}:' .
                    '|(?!(?:.*[a-f0-9]:){6,})(?8)?::(?>((?6)(?>:(?6)){0,4}):)?))?(25[0-5]|2[0-4][0-9]|1[0-9]{2}' .
                    '|[1-9]?[0-9])(?>\.(?9)){3}))\])(?1)$/isD',
                    $address
                );
            case 'html5':
                return (bool) preg_match(
                    '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}' .
                    '[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/sD',
                    $address
                );
            case 'eai':
                return (bool) preg_match(
                    '/^[-\p{L}\p{N}\p{M}.!#$%&\'*+\/=?^_`{|}~]+@[\p{L}\p{N}\p{M}](?:[\p{L}\p{N}\p{M}-]{0,61}' .
                    '[\p{L}\p{N}\p{M}])?(?:\.[\p{L}\p{N}\p{M}]' .
                    '(?:[-\p{L}\p{N}\p{M}]{0,61}[\p{L}\p{N}\p{M}])?)*$/usD',
                    $address
                );
            case 'php':
            default:
                return filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
        }
    }

    public static function idnSupported()
    {
        return function_exists('idn_to_ascii') && func
