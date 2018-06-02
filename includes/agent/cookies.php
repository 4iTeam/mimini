<?php
/**
 *  Base include file for Mimini
 * @package    Mimini
 * @subpackage Browser
 * @version    $Id: cookies.php 2011 2011-04-29 08:22:48Z pp11 $
 */


/**
 *    Cookie data holder. Cookie rules are full of pretty
 *    arbitary stuff. I have used...
 *    http://wp.netscape.com/newsref/std/cookie_spec.html
 *    http://www.cookiecentral.com/faq/
 * @package Mimini
 * @subpackage Browser
 */
class MiminiCookie
{
    public $host;
    public $name;
    public $value;
    public $path;
    public $expiry;
    public $is_secure;

    /**
     *    Constructor. Sets the stored values.
     * @param string $name Cookie key.
     * @param string $value Value of cookie.
     * @param string $path Cookie path if not host wide.
     * @param string $expiry Expiry date as string.
     * @param boolean $is_secure Currently ignored.
     */
    function __construct($name, $value = '', $path = '', $expiry = '', $is_secure = false)
    {
        $host=false;
        if(is_object($name)){
            $cookie=$name;
            $name=$cookie->name;
            $host=$cookie->host;
            $value=$cookie->value;
            $path=$cookie->path;
            $expiry=$cookie->expiry;
            $is_secure=$cookie->is_secure;
        }
        $this->host = $host;
        $this->name = $name;
        $this->value = $value;
        $this->path = ($path ? $this->fixPath($path) : "/");
        $this->expiry = false;
        if (is_string($expiry)) {
            $this->expiry = strtotime($expiry);
        } elseif (is_integer($expiry)) {
            $this->expiry = $expiry;
        }
        $this->is_secure = $is_secure;
    }

    /**
     *    Sets the host. The cookie rules determine
     *    that the first two parts are taken for
     *    certain TLDs and three for others. If the
     *    new host does not match these rules then the
     *    call will fail.
     * @param string $host New hostname.
     * @return boolean           True if hostname is valid.
     * @access public
     */
    function setHost($host)
    {
        if ($host = $this->truncateHost($host)) {
            $this->host = $host;
            return true;
        }
        return false;
    }

    /**
     *    Accessor for the truncated host to which this
     *    cookie applies.
     * @return string       Truncated hostname.
     * @access public
     */
    function getHost()
    {
        return $this->host;
    }

    /**
     *    Test for a cookie being valid for a host name.
     * @param string $host Host to test against.
     * @return boolean        True if the cookie would be valid
     *                           here.
     */
    function isValidHost($host)
    {
        return ($this->truncateHost($host) === $this->getHost());
    }

    /**
     *    Extracts just the domain part that determines a
     *    cookie's host validity.
     * @param string $host Host name to truncate.
     * @return string        Domain or false on a bad host.
     * @access private
     */
    protected function truncateHost($host)
    {
        $tlds = MiminiUrl::getAllTopLevelDomains();
        if (preg_match('/[a-z\-]+\.(' . $tlds . ')$/i', $host, $matches)) {
            return $matches[0];
        } elseif (preg_match('/[a-z\-]+\.[a-z\-]+\.[a-z\-]+$/i', $host, $matches)) {
            return $matches[0];
        }
        return false;
    }

    /**
     *    Accessor for name.
     * @return string       Cookie key.
     * @access public
     */
    function getName()
    {
        return $this->name;
    }

    /**
     *    Accessor for value. A deleted cookie will
     *    have an empty string for this.
     * @return string       Cookie value.
     * @access public
     */
    function getValue()
    {
        return $this->value;
    }

    /**
     *    Accessor for path.
     * @return string       Valid cookie path.
     * @access public
     */
    function getPath()
    {
        return $this->path;
    }

    /**
     *    Tests a path to see if the cookie applies
     *    there. The test path must be longer or
     *    equal to the cookie path.
     * @param string $path Path to test against.
     * @return boolean           True if cookie valid here.
     * @access public
     */
    function isValidPath($path)
    {
        return (strncmp(
                $this->fixPath($path),
                $this->getPath(),
                strlen($this->getPath())) == 0);
    }

    /**
     *    Accessor for expiry.
     * @return string       Expiry string.
     * @access public
     */
    function getExpiry()
    {
        if (!$this->expiry) {
            return false;
        }
        return gmdate("D, d M Y H:i:s", $this->expiry) . " GMT";
    }

    /**
     *    Test to see if cookie is expired against
     *    the cookie format time or timestamp.
     *    Will give true for a session cookie.
     * @param integer /string $now  Time to test against. Result
     *                                will be false if this time
     *                                is later than the cookie expiry.
     *                                Can be either a timestamp integer
     *                                or a cookie format date.
     * @return boolean
     * @access public
     */
    function isExpired($now)
    {
        if (!$this->expiry) {
            return true;
        }
        if ($now&&is_string($now)) {
            $now = strtotime($now);
        }
        if($now<=0){
            $now=time();
        }
        return ($this->expiry < $now);
    }

    /**
     *    Ages the cookie by the specified number of
     *    seconds.
     * @param integer $interval In seconds.
     * @public
     */
    function agePrematurely($interval)
    {
        if ($this->expiry) {
            $this->expiry -= $interval;
        }
    }

    /**
     *    Accessor for the secure flag.
     * @return boolean       True if cookie needs SSL.
     * @access public
     */
    function isSecure()
    {
        return $this->is_secure;
    }

    /**
     *    Adds a trailing and leading slash to the path
     *    if missing.
     * @param string $path Path to fix.
     * @return string
     * @access private
     */
    protected function fixPath($path)
    {
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }
        if (substr($path, -1, 1) != '/') {
            $path .= '/';
        }
        return $path;
    }
}

/**
 *    Repository for cookies. This stuff is a
 *    tiny bit browser dependent.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiCookieJar
{
    /**
     * @var MiminiCookie[]|array
     */
    private $cookies;
    private $session;

    /**
     *    Constructor. Jar starts empty.
     * @param string $session
     * @access public
     */
    function __construct($session=null)
    {
        $this->cookies = array();
        $this->session=$session;
        $this->load();
    }

    /**
     *    Removes expired and temporary cookies as if
     *    the browser was closed and re-opened.
     * @param string|integer $date Time to test expiry against.
     * @access public
     */
    function restartSession($date = 0)
    {
        $surviving_cookies = array();
        for ($i = 0; $i < count($this->cookies); $i++) {
            if (!$this->cookies[$i]->getValue()) {//empty cookie
                continue;
            }
            if (!$this->cookies[$i]->getExpiry()) {//Session
                continue;
            }
            if ($date && $this->cookies[$i]->isExpired($date)) {//expired
                continue;
            }
            $surviving_cookies[] = $this->cookies[$i];
        }
        $this->cookies = $surviving_cookies;
        $this->save();
    }

    /**
     *    Ages all cookies in the cookie jar.
     * @param integer $interval The old session is moved
     *                                 into the past by this number
     *                                 of seconds. Cookies now over
     *                                 age will be removed.
     * @access public
     */
    function agePrematurely($interval)
    {
        for ($i = 0; $i < count($this->cookies); $i++) {
            $this->cookies[$i]->agePrematurely($interval);
        }
        $this->save();
    }

    /**
     *    Sets an additional cookie. If a cookie has
     *    the same name and path it is replaced.
     * @param string|MiminiCookie $name Cookie key or object
     * @param string $value Value of cookie.
     * @param string $host Host upon which the cookie is valid.
     * @param string $path Cookie path if not host wide.
     * @param string $expiry Expiry date.
     * @return $this;
     * @access public
     */
    function setCookie($name, $value='', $host = '', $path = '/', $expiry = '')
    {
        if($name instanceof MiminiCookie){
            $this->cookies[$this->findFirstMatch($name)] = $name;
            return $this;
        }
        $cookie = new MiminiCookie($name, $value, $path, $expiry);
        if ($host) {
            $cookie->setHost($host);
        }
        $this->cookies[$this->findFirstMatch($cookie)] = $cookie;
        return $this;
    }

    /**
     *    Finds a matching cookie to write over or the
     *    first empty slot if none.
     * @param MiminiCookie $cookie Cookie to write into jar.
     * @return integer                Available slot.
     * @access private
     */
    protected function findFirstMatch($cookie)
    {
        for ($i = 0; $i < count($this->cookies); $i++) {
            $is_match = $this->isMatch(
                $cookie,
                $this->cookies[$i]->getHost(),
                $this->cookies[$i]->getPath(),
                $this->cookies[$i]->getName());
            if ($is_match) {
                return $i;
            }
        }
        return count($this->cookies);
    }

    /**
     *    Reads the most specific cookie value from the
     *    browser cookies. Looks for the longest path that
     *    matches.
     * @param string $host Host to search.
     * @param string $path Applicable path.
     * @param string $name Name of cookie to read.
     * @return string             False if not present, else the
     *                               value as a string.
     * @access public
     */
    function getCookieValue($host, $path, $name)
    {
        $longest_path = '';
        foreach ($this->cookies as $cookie) {
            if ($this->isMatch($cookie, $host, $path, $name)) {
                if (strlen($cookie->getPath()) > strlen($longest_path)) {
                    $value = $cookie->getValue();
                    $longest_path = $cookie->getPath();
                }
            }
        }
        return (isset($value) ? $value : false);
    }

    /**
     *    Tests cookie for matching against search
     *    criteria.
     * @param MiminiCookie $cookie Cookie to test.
     * @param string $host Host must match.
     * @param string $path Cookie path must be shorter than
     *                                 this path.
     * @param string $name Name must match.
     * @return boolean              True if matched.
     * @access private
     */
    protected function isMatch($cookie, $host, $path, $name)
    {
        if ($cookie->getName() != $name) {
            return false;
        }
        if ($host && $cookie->getHost() && !$cookie->isValidHost($host)) {
            return false;
        }
        if (!$cookie->isValidPath($path)) {
            return false;
        }
        return true;
    }

    /**
     *    Uses a URL to sift relevant cookies by host and
     *    path. Results are list of strings of form "name=value".
     * @param MiminiUrl $url Url to select by.
     * @return array               Valid name and value pairs.
     * @access public
     */
    function selectAsPairs($url)
    {
        $pairs = array();
        foreach ($this->cookies as $cookie) {
            if ($this->isMatch($cookie, $url->getHost(), $url->getPath(), $cookie->getName())) {
                $pairs[] = $cookie->getName() . '=' . $cookie->getValue();
            }
        }
        return $pairs;
    }
    function save(){
        $file_name=$this->getStorageFile();
        $cookie=json_encode($this->cookies);
        if($file_name) {
            file_put_contents($file_name, $cookie);
        }
    }
    function load(){
        if($file=$this->getStorageFile()){
            if(file_exists($file)){
                $content=file_get_contents($file);
                @$cookies=json_decode($content);
                if($cookies){
                    foreach($cookies as $cookie){
                        $this->setCookie(new MiminiCookie($cookie));
                    }
                }
            }
        }
        return $this;
    }
    function getStorageFile(){
        if(!$this->session){
            return false;
        }
        return MIMINI_DATA.'/'.$this->session.'.sess';
    }
}