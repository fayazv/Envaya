<?php
/**
 *  PEAR's Mail:: interface.
 *
 * PHP versions 4 and 5
 *
 * LICENSE:
 *
 * Copyright (c) 2002-2007, Richard Heyes
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * o Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * o The names of the authors may not be used to endorse or promote
 *   products derived from this software without specific prior written
 *   permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category    Mail
 * @package     Mail
 * @author      Chuck Hagenbuch <chuck@horde.org>
 * @copyright   1997-2010 Chuck Hagenbuch
 * @license     http://opensource.org/licenses/bsd-license.php New BSD License
 * @version     CVS: $Id: Mail.php 294747 2010-02-08 08:18:33Z clockwerx $
 * @link        http://pear.php.net/package/Mail/
 */

require_once 'PEAR.php';

/**
 * PEAR's Mail:: interface. Defines the interface for implementing
 * mailers under the PEAR hierarchy, and provides supporting functions
 * useful in multiple mailer backends.
 *
 * @access public
 * @version $Revision: 294747 $
 * @package Mail
 */
class Mail
{
    /**
     * Line terminator used for separating header lines.
     * @var string
     */
    var $sep = "\r\n";

    /**
     * Provides an interface for generating Mail:: objects of various
     * types
     *
     * @param string $driver The kind of Mail:: object to instantiate.
     * @param array  $params The parameters to pass to the Mail:: object.
     * @return object Mail a instance of the driver class or if fails a PEAR Error
     * @access public
     */
    function &factory($driver, $params = array())
    {
        $driver = strtolower($driver);
        @include_once 'Mail/' . $driver . '.php';
        $class = 'Mail_' . $driver;
        if (class_exists($class)) {
            $mailer = new $class($params);
            return $mailer;
        } else {
            return PEAR::raiseError('Unable to find class for driver ' . $driver);
        }
    }

    /**
     * Implements Mail::send() function using php's built-in mail()
     * command.
     *
     * @param mixed $recipients Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid. This may contain recipients not
     *              specified in the headers, for Bcc:, resending
     *              messages, etc.
     *
     * @param array $headers The array of headers to send with the mail, in an
     *              associative array, where the array key is the
     *              header name (ie, 'Subject'), and the array value
     *              is the header value (ie, 'test'). The header
     *              produced from those values would be 'Subject:
     *              test'.
     *
     * @param string $body The full text of the message body, including any
     *               Mime parts, etc.
     *
     * @return mixed Returns true on success, or a PEAR_Error
     *               containing a descriptive error message on
     *               failure.
     *
     * @access public
     * @deprecated use Mail_mail::send instead
     */
    function send($recipients, $headers, $body)
    {
        if (!is_array($headers)) {
            return PEAR::raiseError('$headers must be an array');
        }

        $result = $this->_sanitizeHeaders($headers);
        if (is_a($result, 'PEAR_Error')) {
            return $result;
        }

        // if we're passed an array of recipients, implode it.
        if (is_array($recipients)) {
            $recipients = implode(', ', $recipients);
        }

        // get the Subject out of the headers array so that we can
        // pass it as a seperate argument to mail().
        $subject = '';
        if (isset($headers['Subject'])) {
            $subject = $headers['Subject'];
            unset($headers['Subject']);
        }

        // flatten the headers out.
        list(, $text_headers) = Mail::prepareHeaders($headers);

        return mail($recipients, $subject, $body, $text_headers);
    }

    /**
     * Sanitize an array of mail headers by removing any additional header
     * strings present in a legitimate header's value.  The goal of this
     * filter is to prevent mail injection attacks.
     *
     * @param array $headers The associative array of headers to sanitize.
     *
     * @access private
     */
    function _sanitizeHeaders(&$headers)
    {
        foreach ($headers as $key => $value) {
            $headers[$key] =
                preg_replace('=((<CR>|<LF>|0x0A/%0A|0x0D/%0D|\\n|\\r)\S).*=i',
                             null, $value);
        }
    }

    /**
     * Take an array of mail headers and return a string containing
     * text usable in sending a message.
     *
     * @param array $headers The array of headers to prepare, in an associative
     *              array, where the array key is the header name (ie,
     *              'Subject'), and the array value is the header
     *              value (ie, 'test'). The header produced from those
     *              values would be 'Subject: test'.
     *
     * @return mixed Returns false if it encounters a bad address,
     *               otherwise returns an array containing two
     *               elements: Any From: address found in the headers,
     *               and the plain text version of the headers.
     * @access private
     */
    function prepareHeaders($headers)
    {
        $lines = array();
        $from = null;

        foreach ($headers as $key => $value) {
            if (strcasecmp($key, 'From') === 0) {
                include_once 'RFC822.php';
                $parser = new Mail_RFC822();
                $addresses = $parser->parseAddressList($value, 'localhost', false);
                if (is_a($addresses, 'PEAR_Error')) {
                    return $addresses;
                }

                $from = $addresses[0]->mailbox . '@' . $addresses[0]->host;

                // Reject envelope From: addresses with spaces.
                if (strstr($from, ' ')) {
                    return false;
                }

                $lines[] = $key . ': ' . $value;
            } elseif (strcasecmp($key, 'Received') === 0) {
                $received = array();
                if (is_array($value)) {
                    foreach ($value as $line) {
                        $received[] = $key . ': ' . $line;
                    }
                }
                else {
                    $received[] = $key . ': ' . $value;
                }
                // Put Received: headers at the top.  Spam detectors often
                // flag messages with Received: headers after the Subject:
                // as spam.
                $lines = array_merge($received, $lines);
            } else {
                // If $value is an array (i.e., a list of addresses), convert
                // it to a comma-delimited string of its elements (addresses).
                if (is_array($value)) {
                    $value = implode(', ', $value);
                }
                $lines[] = $key . ': ' . $value;
            }
        }

        return array($from, join($this->sep, $lines));
    }

    /**
     * Take a set of recipients and parse them, returning an array of
     * bare addresses (forward paths) that can be passed to sendmail
     * or an smtp server with the rcpt to: command.
     *
     * @param mixed Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid.
     *
     * @return mixed An array of forward paths (bare addresses) or a PEAR_Error
     *               object if the address list could not be parsed.
     * @access private
     */
    function parseRecipients($recipients)
    {
        include_once 'RFC822.php';

        // if we're passed an array, assume addresses are valid and
        // implode them before parsing.
        if (is_array($recipients)) {
            $recipients = implode(', ', $recipients);
        }

        // Parse recipients, leaving out all personal info. This is
        // for smtp recipients, etc. All relevant personal information
        // should already be in the headers.
        $addresses = Mail_RFC822::parseAddressList($recipients, 'localhost', false);

        // If parseAddressList() returned a PEAR_Error object, just return it.
        if (is_a($addresses, 'PEAR_Error')) {
            return $addresses;
        }

        $recipients = array();
        if (is_array($addresses)) {
            foreach ($addresses as $ob) {
                $recipients[] = $ob->mailbox . '@' . $ob->host;
            }
        }

        return $recipients;
    }

}

/**
 * SMTP implementation of the PEAR Mail interface. Requires the Net_SMTP class.
 *
 * PHP versions 4 and 5
 *
 * LICENSE:
 *
 * Copyright (c) 2010, Chuck Hagenbuch
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions
 * are met:
 *
 * o Redistributions of source code must retain the above copyright
 *   notice, this list of conditions and the following disclaimer.
 * o Redistributions in binary form must reproduce the above copyright
 *   notice, this list of conditions and the following disclaimer in the
 *   documentation and/or other materials provided with the distribution.
 * o The names of the authors may not be used to endorse or promote
 *   products derived from this software without specific prior written
 *   permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category    HTTP
 * @package     HTTP_Request
 * @author      Jon Parise <jon@php.net> 
 * @author      Chuck Hagenbuch <chuck@horde.org>
 * @copyright   2010 Chuck Hagenbuch
 * @license     http://opensource.org/licenses/bsd-license.php New BSD License
 * @version     CVS: $Id: smtp.php 294747 2010-02-08 08:18:33Z clockwerx $
 * @link        http://pear.php.net/package/Mail/
 */

/** Error: Failed to create a Net_SMTP object */
define('PEAR_MAIL_SMTP_ERROR_CREATE', 10000);

/** Error: Failed to connect to SMTP server */
define('PEAR_MAIL_SMTP_ERROR_CONNECT', 10001);

/** Error: SMTP authentication failure */
define('PEAR_MAIL_SMTP_ERROR_AUTH', 10002);

/** Error: No From: address has been provided */
define('PEAR_MAIL_SMTP_ERROR_FROM', 10003);

/** Error: Failed to set sender */
define('PEAR_MAIL_SMTP_ERROR_SENDER', 10004);

/** Error: Failed to add recipient */
define('PEAR_MAIL_SMTP_ERROR_RECIPIENT', 10005);

/** Error: Failed to send data */
define('PEAR_MAIL_SMTP_ERROR_DATA', 10006);

/**
 * SMTP implementation of the PEAR Mail interface. Requires the Net_SMTP class.
 * @access public
 * @package Mail
 * @version $Revision: 294747 $
 */
class Mail_smtp extends Mail {

    /**
     * SMTP connection object.
     *
     * @var object
     * @access private
     */
    var $_smtp = null;

    /**
     * The list of service extension parameters to pass to the Net_SMTP
     * mailFrom() command.
     * @var array
     */
    var $_extparams = array();

    /**
     * The SMTP host to connect to.
     * @var string
     */
    var $host = 'localhost';

    /**
     * The port the SMTP server is on.
     * @var integer
     */
    var $port = 25;

    /**
     * Should SMTP authentication be used?
     *
     * This value may be set to true, false or the name of a specific
     * authentication method.
     *
     * If the value is set to true, the Net_SMTP package will attempt to use
     * the best authentication method advertised by the remote SMTP server.
     *
     * @var mixed
     */
    var $auth = false;

    /**
     * The username to use if the SMTP server requires authentication.
     * @var string
     */
    var $username = '';

    /**
     * The password to use if the SMTP server requires authentication.
     * @var string
     */
    var $password = '';

    /**
     * Hostname or domain that will be sent to the remote SMTP server in the
     * HELO / EHLO message.
     *
     * @var string
     */
    var $localhost = 'localhost';

    /**
     * SMTP connection timeout value.  NULL indicates no timeout.
     *
     * @var integer
     */
    var $timeout = null;

    /**
     * Turn on Net_SMTP debugging?
     *
     * @var boolean $debug
     */
    var $debug = false;

    /**
     * Indicates whether or not the SMTP connection should persist over
     * multiple calls to the send() method.
     *
     * @var boolean
     */
    var $persist = false;

    /**
     * Use SMTP command pipelining (specified in RFC 2920) if the SMTP server
     * supports it. This speeds up delivery over high-latency connections. By
     * default, use the default value supplied by Net_SMTP.
     * @var bool
     */
    var $pipelining;

    /**
     * Constructor.
     *
     * Instantiates a new Mail_smtp:: object based on the parameters
     * passed in. It looks for the following parameters:
     *     host        The server to connect to. Defaults to localhost.
     *     port        The port to connect to. Defaults to 25.
     *     auth        SMTP authentication.  Defaults to none.
     *     username    The username to use for SMTP auth. No default.
     *     password    The password to use for SMTP auth. No default.
     *     localhost   The local hostname / domain. Defaults to localhost.
     *     timeout     The SMTP connection timeout. Defaults to none.
     *     verp        Whether to use VERP or not. Defaults to false.
     *                 DEPRECATED as of 1.2.0 (use setMailParams()).
     *     debug       Activate SMTP debug mode? Defaults to false.
     *     persist     Should the SMTP connection persist?
     *     pipelining  Use SMTP command pipelining
     *
     * If a parameter is present in the $params array, it replaces the
     * default.
     *
     * @param array Hash containing any parameters different from the
     *              defaults.
     * @access public
     */
    function Mail_smtp($params)
    {
        if (isset($params['host'])) $this->host = $params['host'];
        if (isset($params['port'])) $this->port = $params['port'];
        if (isset($params['auth'])) $this->auth = $params['auth'];
        if (isset($params['username'])) $this->username = $params['username'];
        if (isset($params['password'])) $this->password = $params['password'];
        if (isset($params['localhost'])) $this->localhost = $params['localhost'];
        if (isset($params['timeout'])) $this->timeout = $params['timeout'];
        if (isset($params['debug'])) $this->debug = (bool)$params['debug'];
        if (isset($params['persist'])) $this->persist = (bool)$params['persist'];
        if (isset($params['pipelining'])) $this->pipelining = (bool)$params['pipelining'];

        // Deprecated options
        if (isset($params['verp'])) {
            $this->addServiceExtensionParameter('XVERP', is_bool($params['verp']) ? null : $params['verp']);
        }

        register_shutdown_function(array(&$this, '_Mail_smtp'));
    }

    /**
     * Destructor implementation to ensure that we disconnect from any
     * potentially-alive persistent SMTP connections.
     */
    function _Mail_smtp()
    {
        $this->disconnect();
    }

    /**
     * Implements Mail::send() function using SMTP.
     *
     * @param mixed $recipients Either a comma-seperated list of recipients
     *              (RFC822 compliant), or an array of recipients,
     *              each RFC822 valid. This may contain recipients not
     *              specified in the headers, for Bcc:, resending
     *              messages, etc.
     *
     * @param array $headers The array of headers to send with the mail, in an
     *              associative array, where the array key is the
     *              header name (e.g., 'Subject'), and the array value
     *              is the header value (e.g., 'test'). The header
     *              produced from those values would be 'Subject:
     *              test'.
     *
     * @param string $body The full text of the message body, including any
     *               MIME parts, etc.
     *
     * @return mixed Returns true on success, or a PEAR_Error
     *               containing a descriptive error message on
     *               failure.
     * @access public
     */
    function send($recipients, $headers, $body)
    {
        /* If we don't already have an SMTP object, create one. */
        $result = &$this->getSMTPObject();
        if (isPearError($result)) {
            return $result;
        }

        if (!is_array($headers)) {
            return PEAR::raiseError('$headers must be an array');
        }

        $this->_sanitizeHeaders($headers);

        $headerElements = $this->prepareHeaders($headers);
        if (is_a($headerElements, 'PEAR_Error')) {
            $this->_smtp->rset();
            return $headerElements;
        }
        list($from, $textHeaders) = $headerElements;

        /* Since few MTAs are going to allow this header to be forged
         * unless it's in the MAIL FROM: exchange, we'll use
         * Return-Path instead of From: if it's set. */
        if (!empty($headers['Return-Path'])) {
            $from = $headers['Return-Path'];
        }

        if (!isset($from)) {
            $this->_smtp->rset();
            return PEAR::raiseError('No From: address has been provided',
                                    PEAR_MAIL_SMTP_ERROR_FROM);
        }

        $params = null;
        if (!empty($this->_extparams)) {
            foreach ($this->_extparams as $key => $val) {
                $params .= ' ' . $key . (is_null($val) ? '' : '=' . $val);
            }
        }
        if (isPearError($res = $this->_smtp->mailFrom($from, ltrim($params)))) {
            $error = $this->_error("Failed to set sender: $from", $res);
            $this->_smtp->rset();
            return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_SENDER);
        }

        $recipients = $this->parseRecipients($recipients);
        if (is_a($recipients, 'PEAR_Error')) {
            $this->_smtp->rset();
            return $recipients;
        }

        foreach ($recipients as $recipient) {
            $res = $this->_smtp->rcptTo($recipient);
            if (is_a($res, 'PEAR_Error')) {
                $error = $this->_error("Failed to add recipient: $recipient", $res);
                $this->_smtp->rset();
                return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_RECIPIENT);
            }
        }

        /* Send the message's headers and the body as SMTP data. */
        $res = $this->_smtp->data($textHeaders . "\r\n\r\n" . $body);
        list(,$args) = $this->_smtp->getResponse();

        if (preg_match("/Ok: queued as (.*)/", $args, $queued)) {
            $this->queued_as = $queued[1];
        }

        /* we need the greeting; from it we can extract the authorative name of the mail server we've really connected to.
         * ideal if we're connecting to a round-robin of relay servers and need to track which exact one took the email */
        $this->greeting = $this->_smtp->getGreeting();

        if (is_a($res, 'PEAR_Error')) {
            $error = $this->_error('Failed to send data', $res);
            $this->_smtp->rset();
            return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_DATA);
        }

        /* If persistent connections are disabled, destroy our SMTP object. */
        if ($this->persist === false) {
            $this->disconnect();
        }

        return true;
    }

    /**
     * Connect to the SMTP server by instantiating a Net_SMTP object.
     *
     * @return mixed Returns a reference to the Net_SMTP object on success, or
     *               a PEAR_Error containing a descriptive error message on
     *               failure.
     *
     * @since  1.2.0
     * @access public
     */
    function &getSMTPObject()
    {
        if (is_object($this->_smtp) !== false) {
            return $this->_smtp;
        }

        include_once 'Net/SMTP.php';
        $this->_smtp = &new Net_SMTP($this->host,
                                     $this->port,
                                     $this->localhost);

        /* If we still don't have an SMTP object at this point, fail. */
        if (is_object($this->_smtp) === false) {
            return PEAR::raiseError('Failed to create a Net_SMTP object',
                                    PEAR_MAIL_SMTP_ERROR_CREATE);
        }

        /* Configure the SMTP connection. */
        if ($this->debug) {
            $this->_smtp->setDebug(true);
        }

        /* Attempt to connect to the configured SMTP server. */
        if (isPearError($res = $this->_smtp->connect($this->timeout))) {
            $error = $this->_error('Failed to connect to ' .
                                   $this->host . ':' . $this->port,
                                   $res);
            return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_CONNECT);
        }

        /* Attempt to authenticate if authentication has been enabled. */
        if ($this->auth) {
            $method = is_string($this->auth) ? $this->auth : '';

            if (isPearError($res = $this->_smtp->auth($this->username,
                                                        $this->password,
                                                        $method))) {
                $error = $this->_error("$method authentication failure",
                                       $res);
                $this->_smtp->rset();
                return PEAR::raiseError($error, PEAR_MAIL_SMTP_ERROR_AUTH);
            }
        }

        return $this->_smtp;
    }

    /**
     * Add parameter associated with a SMTP service extension.
     *
     * @param string Extension keyword.
     * @param string Any value the keyword needs.
     *
     * @since 1.2.0
     * @access public
     */
    function addServiceExtensionParameter($keyword, $value = null)
    {
        $this->_extparams[$keyword] = $value;
    }

    /**
     * Disconnect and destroy the current SMTP connection.
     *
     * @return boolean True if the SMTP connection no longer exists.
     *
     * @since  1.1.9
     * @access public
     */
    function disconnect()
    {
        /* If we have an SMTP object, disconnect and destroy it. */
        if (is_object($this->_smtp) && $this->_smtp->disconnect()) {
            $this->_smtp = null;
        }

        /* We are disconnected if we no longer have an SMTP object. */
        return ($this->_smtp === null);
    }

    /**
     * Build a standardized string describing the current SMTP error.
     *
     * @param string $text  Custom string describing the error context.
     * @param object $error Reference to the current PEAR_Error object.
     *
     * @return string       A string describing the current SMTP error.
     *
     * @since  1.1.7
     * @access private
     */
    function _error($text, &$error)
    {
        /* Split the SMTP response into a code and a response string. */
        list($code, $response) = $this->_smtp->getResponse();

        /* Build our standardized error string. */
        return $text
            . ' [SMTP: ' . $error->getMessage()
            . " (code: $code, response: $response)]";
    }

}