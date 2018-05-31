<?php
/**
 *  base include file for Mimini
 * @package    Mimini
 * @subpackage Browser
 * @version    $Id: httpResponse.php 2011 2011-04-29 08:22:48Z pp11 $
 */


/**
 *    Creates HTTP headers for the end point of
 *    a HTTP request.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiRoute
{
    private $url;

    /**
     *    Sets the target URL.
     * @param MiminiUrl $url URL as object.
     * @access public
     */
    function __construct($url)
    {
        $this->url = $url;
    }

    /**
     *    Resource name.
     * @return MiminiUrl        Current url.
     * @access protected
     */
    function getUrl()
    {
        return $this->url;
    }

    /**
     *    Creates the first line which is the actual request.
     * @param string $method HTTP request method, usually GET.
     * @return string          Request line content.
     * @access protected
     */
    protected function getRequestLine($method)
    {
        return $method . ' ' . $this->url->getPath() .
        $this->url->getEncodedRequest() . ' HTTP/1.0';
    }

    /**
     *    Creates the host part of the request.
     * @return string          Host line content.
     * @access protected
     */
    protected function getHostLine()
    {
        $line = 'Host: ' . $this->url->getHost();
        if ($this->url->getPort()) {
            $line .= ':' . $this->url->getPort();
        }
        return $line;
    }

    /**
     *    Opens a socket to the route.
     * @param string $method HTTP request method, usually GET.
     * @param integer $timeout Connection timeout.
     * @return MiminiSocket       New socket.
     * @access public
     */
    function createConnection($method, $timeout)
    {
        $default_port = ('https' == $this->url->getScheme()) ? 443 : 80;
        $socket = $this->createSocket(
            $this->url->getScheme() ? $this->url->getScheme() : 'http',
            $this->url->getHost(),
            $this->url->getPort() ? $this->url->getPort() : $default_port,
            $timeout);
        if (!$socket->isError()) {
            $socket->write($this->getRequestLine($method) . "\r\n");
            $socket->write($this->getHostLine() . "\r\n");
            $socket->write("Connection: close\r\n");
        }
        return $socket;
    }

    /**
     *    Factory for socket.
     * @param string $scheme Protocol to use.
     * @param string $host Hostname to connect to.
     * @param integer $port Remote port.
     * @param integer $timeout Connection timeout.
     * @return MiminiSocket/MiminiSecureSocket New socket.
     * @access protected
     */
    protected function createSocket($scheme, $host, $port, $timeout)
    {
        if (in_array($scheme, array('file'))) {
            return new MiminiFileSocket($this->url);
        } elseif (in_array($scheme, array('https'))) {
            return new MiminiSecureSocket($host, $port, $timeout);
        } else {
            return new MiminiSocket($host, $port, $timeout);
        }
    }
}

/**
 *    Creates HTTP headers for the end point of
 *    a HTTP request via a proxy server.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiProxyRoute extends MiminiRoute
{
    /**
     * @var MiminiUrl
     */
    private $proxy;
    private $username;
    private $password;

    /**
     *    Stashes the proxy address.
     * @param MiminiUrl $url URL as object.
     * @param string $proxy Proxy URL.
     * @param string $username Username for authentication.
     * @param string $password Password for authentication.
     * @access public
     */
    function __construct($url, $proxy, $username = null, $password = null)
    {
        parent::__construct($url);
        $this->proxy = $proxy;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     *    Creates the first line which is the actual request.
     * @param string $method HTTP request method, usually GET.
     * @return string          Request line content.
     * @access protected
     */
    function getRequestLine($method)
    {
        $url = $this->getUrl();
        $scheme = $url->getScheme() ? $url->getScheme() : 'http';
        $port = $url->getPort() ? ':' . $url->getPort() : '';
        return $method . ' ' . $scheme . '://' . $url->getHost() . $port .
        $url->getPath() . $url->getEncodedRequest() . ' HTTP/1.0';
    }

    /**
     *    Creates the host part of the request.
     * @return string          Host line content.
     * @access protected
     */
    function getHostLine()
    {
        $host = 'Host: ' . $this->proxy->getHost();
        $port = $this->proxy->getPort() ? $this->proxy->getPort() : 8080;
        return "$host:$port";
    }

    /**
     *    Opens a socket to the route.
     * @param string $method HTTP request method, usually GET.
     * @param integer $timeout Connection timeout.
     * @return MiminiSocket        New socket.
     * @access public
     */
    function createConnection($method, $timeout)
    {
        $socket = $this->createSocket(
            $this->proxy->getScheme() ? $this->proxy->getScheme() : 'http',
            $this->proxy->getHost(),
            $this->proxy->getPort() ? $this->proxy->getPort() : 8080,
            $timeout);
        if ($socket->isError()) {
            return $socket;
        }
        $socket->write($this->getRequestLine($method) . "\r\n");
        $socket->write($this->getHostLine() . "\r\n");
        if ($this->username && $this->password) {
            $socket->write('Proxy-Authorization: Basic ' .
                base64_encode($this->username . ':' . $this->password) .
                "\r\n");
        }
        $socket->write("Connection: close\r\n");
        return $socket;
    }
}

/**
 *    HTTP request for a web page. Factory for
 *    HttpResponse object.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiHttpRequest
{
    private $route;
    /**
     * @var MiminiEncoding
     */
    private $encoding;
    private $headers;
    private $cookies;

    /**
     *    Builds the socket request from the different pieces.
     *    These include proxy information, URL, cookies, headers,
     *    request method and choice of encoding.
     * @param MiminiRoute $route Request route.
     * @param MiminiEncoding $encoding Content to send with
     *                                           request.
     * @access public
     */
    function __construct($route, $encoding)
    {
        $this->route = $route;
        $this->encoding = $encoding;
        $this->headers = array();
        $this->cookies = array();
    }

    /**
     *    Dispatches the content to the route's socket.
     * @param integer $timeout Connection timeout.
     * @return MiminiHttpResponse   A response which may only have
     *                                 an error, but hopefully has a
     *                                 complete web page.
     * @access public
     */
    function fetch($timeout)
    {
        $socket = $this->route->createConnection($this->encoding->getMethod(), $timeout);
        if (!$socket->isError()) {
            $this->dispatchRequest($socket, $this->encoding);
        }
        return $this->createResponse($socket);
    }

    /**
     *    Sends the headers.
     * @param MiminiSocket $socket Open socket.
     * @param MiminiEncoding|MiminiGetEncoding|MiminiPostEncoding $encoding Content to send with request.
     * @access private
     */
    protected function dispatchRequest($socket, $encoding)
    {
        foreach ($this->headers as $header_line) {
            $socket->write($header_line . "\r\n");
        }
        if (count($this->cookies) > 0) {
            $socket->write("Cookie: " . implode(";", $this->cookies) . "\r\n");
        }
        $encoding->writeHeadersTo($socket);
        $socket->write("\r\n");
        $encoding->writeTo($socket);
    }

    /**
     *    Adds a header line to the request.
     * @param string $header_line Text of full header line.
     * @access public
     */
    function addHeaderLine($header_line)
    {
        $this->headers[] = $header_line;
    }

    /**
     *    Reads all the relevant cookies from the
     *    cookie jar.
     * @param MiminiCookieJar $jar Jar to read
     * @param MiminiUrl $url Url to use for scope.
     * @access public
     */
    function readCookiesFromJar($jar, $url)
    {
        $this->cookies = $jar->selectAsPairs($url);
    }

    /**
     *    Wraps the socket in a response parser.
     * @param MiminiSocket $socket Responding socket.
     * @return MiminiHttpResponse    Parsed response object.
     * @access protected
     */
    protected function createResponse($socket)
    {
        $response = new MiminiHttpResponse(
            $socket,
            $this->route->getUrl(),
            $this->encoding);
        $socket->close();
        return $response;
    }
}

/**
 *    Collection of header lines in the response.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiHttpResponseHeaders
{
    private $raw_headers;
    private $response_code;
    private $http_version;
    private $mime_type;
    private $location;
    /**
     * @var MiminiCookie[]
     */
    private $cookies;
    private $authentication;
    private $realm;
    /**
     * Content encoding
     * @var string
     */
    private $encoding;

    private $data;

    /**
     *    Parses the incoming header block.
     * @param string $headers Header block.
     * @access public
     */
    function __construct($headers)
    {
        $this->raw_headers = $headers;
        $this->response_code = false;
        $this->http_version = false;
        $this->mime_type = '';
        $this->location = false;
        $this->cookies = array();
        $this->authentication = false;
        $this->realm = false;
        $this->data=array();
        foreach (explode("\r\n", $headers) as $header_line) {
            $this->parseHeaderLine($header_line);
        }
    }

    /**
     *    Accessor for parsed HTTP protocol version.
     * @return integer           HTTP error code.
     * @access public
     */
    function getHttpVersion()
    {
        return $this->http_version;
    }

    /**
     *    Accessor for raw header block.
     * @return string        All headers as raw string.
     * @access public
     */
    function getRaw()
    {
        return $this->raw_headers;
    }

    function getData(){
        return $this->data;
    }

    /**
     *    Accessor for parsed HTTP error code.
     * @return integer           HTTP error code.
     * @access public
     */
    function getResponseCode()
    {
        return (integer)$this->response_code;
    }

    function getEncoding(){
        return $this->encoding;
    }
    /**
     *    Returns the redirected URL or false if
     *    no redirection.
     * @return string      URL or false for none.
     * @access public
     */
    function getLocation()
    {
        return $this->location;
    }

    /**
     *    Test to see if the response is a valid redirect.
     * @return boolean       True if valid redirect.
     * @access public
     */
    function isRedirect()
    {
        return in_array($this->response_code, array(301, 302, 303, 307)) &&
        (boolean)$this->getLocation();
    }

    /**
     *    Test to see if the response is an authentication
     *    challenge.
     * @return boolean       True if challenge.
     * @access public
     */
    function isChallenge()
    {
        return ($this->response_code == 401) &&
        (boolean)$this->authentication &&
        (boolean)$this->realm;
    }

    /**
     *    Accessor for MIME type header information.
     * @return string           MIME type.
     * @access public
     */
    function getMimeType()
    {
        return $this->mime_type;
    }

    /**
     *    Accessor for authentication type.
     * @return string        Type.
     * @access public
     */
    function getAuthentication()
    {
        return $this->authentication;
    }

    /**
     *    Accessor for security realm.
     * @return string        Realm.
     * @access public
     */
    function getRealm()
    {
        return $this->realm;
    }

    /**
     *    Writes new cookies to the cookie jar.
     * @param MiminiCookieJar $jar Jar to write to.
     * @param MiminiUrl $url Host and path to write under.
     * @access public
     */
    function writeCookiesToJar($jar, $url)
    {
        foreach ($this->cookies as $cookie) {
            $jar->setCookie(
                $cookie->getName(),
                $cookie->getValue(),
                $url->getHost(),
                $cookie->getPath(),
                $cookie->getExpiry());
        }
        $jar->save();
    }

    /**
     *    Called on each header line to accumulate the held
     *    data within the class.
     * @param string $header_line One line of header.
     * @access protected
     */
    protected function parseHeaderLine($header_line)
    {
        if (preg_match('/HTTP\/(\d+\.\d+)\s+(\d+)/i', $header_line, $matches)) {
            $this->http_version = $matches[1];
            $this->response_code = $matches[2];
        }elseif (preg_match('/Content-type:\s*(.*)/i', $header_line, $matches)) {
            $this->mime_type = trim($matches[1]);
        }elseif (preg_match('/Content-Encoding:\s*(.*)/i', $header_line, $matches)) {
            $this->encoding = trim($matches[1]);
        }elseif (preg_match('/Location:\s*(.*)/i', $header_line, $matches)) {
            $this->location = trim($matches[1]);
        }elseif (preg_match('/Set-cookie:(.*)/i', $header_line, $matches)) {
            $this->cookies[] = $this->parseCookie($matches[1]);
        }elseif (preg_match('/WWW-Authenticate:\s+(\S+)\s+realm=\"(.*?)\"/i', $header_line, $matches)) {
            $this->authentication = $matches[1];
            $this->realm = trim($matches[2]);
        }

        if(strpos($header_line,':')){
            list($name,$value)=explode(':',$header_line);
            $name=trim(strtolower($name));
            $this->data[$name]=$value;
        }
    }

    /**
     *    Parse the Set-cookie content.
     * @param string $cookie_line Text after "Set-cookie:"
     * @return MiminiCookie          New cookie object.
     * @access private
     */
    protected function parseCookie($cookie_line)
    {
        $parts = explode(";", $cookie_line);
        $cookie = array();
        preg_match('/\s*(.*?)\s*=(.*)/', array_shift($parts), $cookie);
        foreach ($parts as $part) {
            if (preg_match('/\s*(.*?)\s*=(.*)/', $part, $matches)) {
                $cookie[$matches[1]] = trim($matches[2]);
            }
        }
        return new MiminiCookie(
            $cookie[1],
            trim($cookie[2]),
            isset($cookie["path"]) ? $cookie["path"] : "",
            isset($cookie["expires"]) ? $cookie["expires"] : false);
    }
    function getNewCookies(){
        return array();
    }
}

/**
 *    Basic HTTP response.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiHttpResponse extends MiminiStickyError
{
    private $url;
    private $encoding;
    private $sent;
    private $content;
    /**
     * @var MiminiHttpResponseHeaders
     */
    private $headers;

    /**
     *    Constructor. Reads and parses the incoming
     *    content and headers.
     * @param MiminiSocket $socket Network connection to fetch
     *                                  response text from.
     * @param MiminiUrl $url Resource name.
     * @param mixed $encoding Record of content sent.
     * @access public
     */
    function __construct($socket, $url, $encoding)
    {
        parent::__construct();
        $this->url = $url;
        $this->encoding = $encoding;
        $this->sent = $socket->getSent();
        $this->content = false;
        $raw = $this->readAll($socket);
        if ($socket->isError()) {
            $this->setError('Error reading socket [' . $socket->getError() . ']');
            return;
        }
        $this->parse($raw);
    }

    /**
     *    Splits up the headers and the rest of the content.
     * @param string $raw Content to parse.
     * @access private
     */
    protected function parse($raw)
    {
        if (!$raw) {
            $this->setError('Nothing fetched');
            $this->headers = new MiminiHttpResponseHeaders('');
        } elseif ('file' == $this->url->getScheme()) {
            $this->headers = new MiminiHttpResponseHeaders('');
            $this->content = $raw;
        } elseif (!strstr($raw, "\r\n\r\n")) {
            $this->setError('Could not split headers from content');
            $this->headers = new MiminiHttpResponseHeaders($raw);
        } else {
            list($headers, $this->content) = explode("\r\n\r\n", $raw, 2);
            $this->headers = new MiminiHttpResponseHeaders($headers);
            if($encoding=$this->headers->getEncoding()){
                if($encoding=='gzip'){
                    $this->content=gzdecode($this->content);
                }else{
                    @$this->content=gzuncompress($this->content);
                    if($this->content===false){
                        @$this->content=gzinflate($this->content);
                    }
                }
            }
        }
    }

    /**
     *    Original request method.
     * @return string        GET, POST or HEAD.
     * @access public
     */
    function getMethod()
    {
        return $this->encoding->getMethod();
    }

    /**
     *    Resource name.
     * @return MiminiUrl        Current url.
     * @access public
     */
    function getUrl()
    {
        return $this->url;
    }

    /**
     *    Original request data.
     * @return mixed              Sent content.
     * @access public
     */
    function getRequestData()
    {
        return $this->encoding;
    }

    /**
     *    Raw request that was sent down the wire.
     * @return string        Bytes actually sent.
     * @access public
     */
    function getSent()
    {
        return $this->sent;
    }

    /**
     *    Accessor for the content after the last
     *    header line.
     * @return string           All content.
     * @access public
     */
    function getContent()
    {
        return $this->content;
    }

    /**
     *    Accessor for header block. The response is the
     *    combination of this and the content.
     * @return MiminiHttpResponseHeaders        Wrapped header block.
     * @access public
     */
    function getHeaders()
    {
        return $this->headers;
    }

    /**
     *    Accessor for any new cookies.
     * @return array       List of new cookies.
     * @access public
     */
    function getNewCookies()
    {
        return $this->headers->getNewCookies();
    }

    /**
     *    Reads the whole of the socket output into a
     *    single string.
     * @param MiminiSocket $socket Unread socket.
     * @return string               Raw output if successful
     *                                 else false.
     * @access private
     */
    protected function readAll($socket)
    {
        $all = '';
        while (!$this->isLastPacket($next = $socket->read())) {
            $all .= $next;
        }
        return $all;
    }

    /**
     *    Test to see if the packet from the socket is the
     *    last one.
     * @param string $packet Chunk to interpret.
     * @return boolean          True if empty or EOF.
     * @access private
     */
    protected function isLastPacket($packet)
    {
        if (is_string($packet)) {
            return $packet === '';
        }
        return !$packet;
    }
}

?>