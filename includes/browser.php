<?php
/**
 *  Base include file for Mimini
 * @package    Mimini
 * @subpackage Browser
 * @version    $Id: browser.php 2013 2011-04-29 09:29:45Z pp11 $
 */

if (!Mimini::getParsers()) {
    Mimini::setParsers(array(new MiminiTidyPageBuilder(), new MiminiPHPPageBuilder()));
}
/**#@-*/

if (!defined('DEFAULT_MAX_NESTED_FRAMES')) {
    define('DEFAULT_MAX_NESTED_FRAMES', 3);
}

/**
 *    Browser history list.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiBrowserHistory
{
    private $sequence = array();
    private $position = -1;
    private $log_file = 'history.log';
    /**
     *    Test for no entries yet.
     * @return boolean        True if empty.
     * @access private
     */
    protected function isEmpty()
    {
        return ($this->position == -1);
    }

    /**
     *    Test for being at the beginning.
     * @return boolean        True if first.
     * @access private
     */
    protected function atBeginning()
    {
        return ($this->position == 0) && !$this->isEmpty();
    }

    /**
     *    Test for being at the last entry.
     * @return boolean        True if last.
     * @access private
     */
    protected function atEnd()
    {
        return ($this->position + 1 >= count($this->sequence)) && !$this->isEmpty();
    }

    /**
     *    Adds a successfully fetched page to the history.
     * @param MiminiUrl $url URL of fetch.
     * @param MiminiEncoding $parameters Any post data with the fetch.
     * @access public
     */
    function recordEntry($url, $parameters)
    {
        $this->dropFuture();
        array_push(
            $this->sequence,
            array('url' => $url, 'parameters' => $parameters));
        $this->position++;
        $this->log($url,$parameters);
    }

    /**
     *    Last fully qualified URL for current history
     *    position.
     * @return MiminiUrl|boolean        URL for this position.
     * @access public
     */
    function getUrl()
    {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->sequence[$this->position]['url'];
    }

    /**
     *    Parameters of last fetch from current history
     *    position.
     * @return MiminiPostEncoding|boolean    Post parameters.
     * @access public
     */
    function getParameters()
    {
        if ($this->isEmpty()) {
            return false;
        }
        return $this->sequence[$this->position]['parameters'];
    }

    /**
     *    Step back one place in the history. Stops at
     *    the first page.
     * @return boolean     True if any previous entries.
     * @access public
     */
    function back()
    {
        if ($this->isEmpty() || $this->atBeginning()) {
            return false;
        }
        $this->position--;
        return true;
    }

    /**
     *    Step forward one place. If already at the
     *    latest entry then nothing will happen.
     * @return boolean     True if any future entries.
     * @access public
     */
    function forward()
    {
        if ($this->isEmpty() || $this->atEnd()) {
            return false;
        }
        $this->position++;
        return true;
    }

    /**
     *    Ditches all future entries beyond the current
     *    point.
     * @access private
     */
    protected function dropFuture()
    {
        if ($this->isEmpty()) {
            return;
        }
        while (!$this->atEnd()) {
            array_pop($this->sequence);
        }
    }

    /**
     * @param MiminiUrl $url
     * @param string $params
     */
    function log($url,$params=''){
        $save=$url->asString();
        if($params){
            $save.=json_encode($params);
        }
        file_put_contents(MIMINI_DATA.'/'.$this->log_file,$save.PHP_EOL,FILE_APPEND);
    }
    function __construct(){
        file_put_contents(MIMINI_DATA.'/'.$this->log_file,'');
    }
}

/**
 *    Simulated web browser. This is an aggregate of
 *    the user agent, the HTML parsing, request history
 *    and the last header set.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiBrowser
{
    private $user_agent;
    private $page;
    private $history;
    private $ignore_frames;
    private $maximum_nested_frames;
    private $parser;
    private $session;

    /**
     *    Starts with a fresh browser with no
     *    cookie or any other state information. The
     *    exception is that a default proxy will be
     *    set up if specified in the options.
     * @param String $session id of session
     * @access public
     */
    function __construct($session=null)
    {
        $this->session=$session;
        $this->user_agent = $this->createUserAgent($session);
        $this->user_agent->useProxy(
            Mimini::getDefaultProxy(),
            Mimini::getDefaultProxyUsername(),
            Mimini::getDefaultProxyPassword());
        $this->page = new MiminiPage();
        $this->history = $this->createHistory();
        $this->ignore_frames = false;
        $this->maximum_nested_frames = DEFAULT_MAX_NESTED_FRAMES;
        $this->addHeader(array(
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; WOW64; rv:41.0) Gecko/20100101 Firefox/41.0',
            'Accept-Encoding'=>'gzip, deflate',
            'Accept-Language'=>'vi,en-Us',
            'Referer'=>array($this,'getUrl'),
        ));
    }

    /**
     *    Creates the underlying user agent.
     * @param String $session the id of session
     * @return MiminiUserAgent    Content fetcher.
     * @access protected
     */
    protected function createUserAgent($session=null)
    {
        return new MiminiUserAgent($session);
    }

    /**
     *    Creates a new empty history list.
     * @return MiminiBrowserHistory    New list.
     * @access protected
     */
    protected function createHistory()
    {
        return new MiminiBrowserHistory();
    }

    /**
     *    Get the HTML parser to use. Can be overridden by
     *    setParser. Otherwise scans through the available parsers and
     *    uses the first one which is available.
     * @return MiminiPHPPageBuilder|MiminiTidyPageBuilder|MiminiParserInterface|boolean
     */
    protected function getParser()
    {
        if ($this->parser) {
            return $this->parser;
        }
        foreach (Mimini::getParsers() as $parser) {
            if ($parser->can()) {
                return $parser;
            }
        }
        return false;
    }

    /**
     *    Override the default HTML parser, allowing parsers to be plugged in.
     * @param MiminiParserInterface $parser parser object instance.
     */
    public function setParser($parser)
    {
        $this->parser = $parser;
    }

    /**
     *    Disables frames support. Frames will not be fetched
     *    and the frameset page will be used instead.
     * @access public
     */
    function ignoreFrames()
    {
        $this->ignore_frames = true;
    }

    /**
     *    Enables frames support. Frames will be fetched from
     *    now on.
     * @access public
     */
    function useFrames()
    {
        $this->ignore_frames = false;
    }

    /**
     *    Switches off cookie sending and recieving.
     * @access public
     */
    function ignoreCookies()
    {
        $this->user_agent->ignoreCookies();
    }

    /**
     *    Switches back on the cookie sending and recieving.
     * @access public
     */
    function useCookies()
    {
        $this->user_agent->useCookies();
    }

    /**
     *    Parses the raw content into a page. Will load further
     *    frame pages unless frames are disabled.
     * @param MiminiHttpResponse $response Response from fetch.
     * @param integer $depth Nested frameset depth.
     * @return MiminiFrameset|MiminiPage                     Parsed HTML.
     * @access private
     */
    protected function parse($response, $depth = 0)
    {
        $page = $this->buildPage($response);

        if ($this->ignore_frames || !$page->hasFrames() || ($depth > $this->maximum_nested_frames)) {
            return $page;
        }
        $frameset = new MiminiFrameset($page);
        foreach ($page->getFrameset() as $key => $url) {
            $frame = $this->fetch($url, new MiminiGetEncoding(), $depth + 1);
            $frameset->addFrame($frame, $key);
        }
        return $frameset;
    }

    /**
     *    Assembles the parsing machinery and actually parses
     *    a single page. Frees all of the builder memory and so
     *    unjams the PHP memory management.
     * @param MiminiHttpResponse $response Response from fetch.
     * @return MiminiPage                     Parsed top level page.
     */
    protected function buildPage($response)
    {
        return $this->getParser()->parse($response,$this);
    }

    /**
     *    Fetches a page. Jointly recursive with the parse()
     *    method as it descends a frameset.
     * @param string /MiminiUrl $url          Target to fetch.
     * @param MiminiEncoding $encoding GET/POST parameters.
     * @param integer $depth Nested frameset depth protection.
     * @return MiminiPage                    Parsed page.
     * @access private
     */
    protected function fetch($url, $encoding, $depth = 0)
    {
        $response = $this->user_agent->fetchResponse($url, $encoding);
        if ($response->isError()) {
            return new MiminiPage($response,$this);
        }
        return $this->parse($response, $depth);
    }

    /**
     *    Fetches a page or a single frame if that is the current
     *    focus.
     * @param MiminiUrl $url Target to fetch.
     * @param MiminiEncoding $parameters GET/POST parameters.
     * @return string                          Raw content of page.
     * @access private
     */
    public function load($url, $parameters)
    {
        $frame = $url->getTarget();
        if (!$frame || !$this->page->hasFrames() || (strtolower($frame) == '_top')) {
            return $this->loadPage($url, $parameters);
        }
        return $this->loadFrame(array($frame), $url, $parameters);
    }

    /**
     *    Fetches a page and makes it the current page/frame.
     * @param string /MiminiUrl $url            Target to fetch as string.
     * @param MiminiEncoding $parameters POST parameters.
     * @return string                          Raw content of page.
     * @access private
     */
    protected function loadPage($url, $parameters)
    {
        $this->page = $this->fetch($url, $parameters);
        $this->history->recordEntry(
            $this->page->getUrl(),
            $this->page->getRequestData());
        return $this->page->getRaw();
    }

    /**
     *    Fetches a frame into the existing frameset replacing the
     *    original.
     * @param array $frames List of names to drill down.
     * @param string /MiminiUrl $url            Target to fetch as string.
     * @param MiminiEncoding $parameters POST parameters.
     * @return string                          Raw content of page.
     * @access private
     */
    protected function loadFrame($frames, $url, $parameters)
    {
        $page = $this->fetch($url, $parameters);
        $this->page->setFrames($frames);
        return $page->getRaw();
    }

    /**
     *    Removes expired and temporary cookies as if
     *    the browser was closed and re-opened.
     * @param string|integer $date Time when session restarted.
     *                                  If omitted then all persistent
     *                                  cookies are kept.
     * @access public
     */
    function restart($date = 0)
    {
        $this->user_agent->restart($date);
    }

    /**
     *    Adds a header to every fetch.
     * @param string|array $header Header line to add to every
     *                                request until cleared.
     * @param string $value value of header
     * @access public
     */
    function addHeader($header,$value=null)
    {
        $this->user_agent->addHeader($header,$value);
    }

    /**
     *    Ages the cookies by the specified time.
     * @param integer $interval Amount in seconds.
     * @access public
     */
    function ageCookies($interval)
    {
        $this->user_agent->ageCookies($interval);
    }

    /**
     *    Sets an additional cookie. If a cookie has
     *    the same name and path it is replaced.
     * @param string $name Cookie key.
     * @param string $value Value of cookie.
     * @param string $host Host upon which the cookie is valid.
     * @param string $path Cookie path if not host wide.
     * @param string $expiry Expiry date.
     * @access public
     */
    function setCookie($name, $value, $host = '', $path = '/', $expiry = '')
    {
        $this->user_agent->setCookie($name, $value, $host, $path, $expiry);
    }

    /**
     *    Reads the most specific cookie value from the
     *    browser cookies.
     * @param string $host Host to search.
     * @param string $path Applicable path.
     * @param string $name Name of cookie to read.
     * @return string             False if not present, else the
     *                               value as a string.
     * @access public
     */
    function getCookieValue($host, $path, $name)
    {
        return $this->user_agent->getCookieValue($host, $path, $name);
    }

    /**
     *    Reads the current cookies for the current URL.
     * @param string $name Key of cookie to find.
     * @return string        Null if there is no current URL, false
     *                          if the cookie is not set.
     * @access public
     */
    function getCurrentCookieValue($name)
    {
        return $this->user_agent->getBaseCookieValue($name, $this->page->getUrl());
    }

    /**
     *    Sets the maximum number of redirects before
     *    a page will be loaded anyway.
     * @param integer $max Most hops allowed.
     * @access public
     */
    function setMaximumRedirects($max)
    {
        $this->user_agent->setMaximumRedirects($max);
    }

    /**
     *    Sets the maximum number of nesting of framed pages
     *    within a framed page to prevent loops.
     * @param integer $max Highest depth allowed.
     * @access public
     */
    function setMaximumNestedFrames($max)
    {
        $this->maximum_nested_frames = $max;
    }

    /**
     *    Sets the socket timeout for opening a connection.
     * @param integer $timeout Maximum time in seconds.
     * @access public
     */
    function setConnectionTimeout($timeout)
    {
        $this->user_agent->setConnectionTimeout($timeout);
    }

    /**
     *    Sets proxy to use on all requests for when
     *    testing from behind a firewall. Set URL
     *    to false to disable.
     * @param string $proxy Proxy URL.
     * @param string $username Proxy username for authentication.
     * @param string $password Proxy password for authentication.
     * @access public
     */
    function useProxy($proxy, $username = '', $password = '')
    {
        $this->user_agent->useProxy($proxy, $username, $password);
    }

    /**
     *    Fetches the page content with a HEAD request.
     *    Will affect cookies, but will not change the base URL.
     * @param string /MiminiUrl $url                Target to fetch as string.
     * @param array $parameters Additional parameters for
     *                                                HEAD request.
     * @return boolean                             True if successful.
     * @access public
     */
    function head($url, $parameters = array())
    {
        if (!is_object($url)) {
            $url = new MiminiUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        $response = $this->user_agent->fetchResponse($url, new MiminiHeadEncoding($parameters));
        $this->page = new MiminiPage($response,$this);
        return !$response->isError();
    }

    /**
     *    Fetches the page content with a simple GET request.
     * @param string /MiminiUrl $url                Target to fetch.
     * @param array $parameters Additional parameters for
     *                                                GET request.
     * @return string                              Content of page or false.
     * @access public
     */
    function get($url, $parameters = array())
    {
        if (!is_object($url)) {
            $url = new MiminiUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        return $this->load($url, new MiminiGetEncoding($parameters));
    }

    /**
     *    Fetches the page content with a POST request.
     * @param string /MiminiUrl $url                Target to fetch as string.
     * @param array $parameters POST parameters or request body.
     * @param string $content_type MIME Content-Type of the request body
     * @return string                              Content of page.
     * @access public
     */
    function post($url, $parameters = array(), $content_type = '')
    {
        if (!is_object($url)) {
            $url = new MiminiUrl($url);
        }
        if ($this->getUrl()) {
            $url = $url->makeAbsolute($this->getUrl());
        }
        return $this->load($url, new MiminiPostEncoding($parameters, $content_type));
    }

    /**
     *    Fetches the page content with a PUT request.
     * @param string|MiminiUrl $url Target to fetch as string.
     * @param array $parameters PUT request body.
     * @param string $content_type MIME Content-Type of the request body
     * @return string                              Content of page.
     * @access public
     */
    function put($url, $parameters = array(), $content_type = '')
    {
        if (!is_object($url)) {
            $url = new MiminiUrl($url);
        }
        return $this->load($url, new MiminiPutEncoding($parameters, $content_type));
    }

    /**
     *    Sends a DELETE request and fetches the response.
     * @param string /MiminiUrl $url                Target to fetch.
     * @param array $parameters Additional parameters for
     *                                                DELETE request.
     * @return string                              Content of page or false.
     * @access public
     */
    function delete($url, $parameters = array())
    {
        if (!is_object($url)) {
            $url = new MiminiUrl($url);
        }
        return $this->load($url, new MiminiDeleteEncoding($parameters));
    }

    /**
     *    Equivalent to hitting the retry button on the
     *    browser. Will attempt to repeat the page fetch. If
     *    there is no history to repeat it will give false.
     * @return string/boolean   Content if fetch succeeded
     *                             else false.
     * @access public
     */
    function retry()
    {
        $frames = $this->page->getFrameFocus();
        if (count($frames) > 0) {
            $this->loadFrame(
                $frames,
                $this->page->getUrl(),
                $this->page->getRequestData());
            return $this->page->getRaw();
        }
        if ($url = $this->history->getUrl()) {
            $this->page = $this->fetch($url, $this->history->getParameters());
            return $this->page->getRaw();
        }
        return false;
    }

    /**
     *    Equivalent to hitting the back button on the
     *    browser. The browser history is unchanged on
     *    failure. The page content is refetched as there
     *    is no concept of content caching in Mimini.
     * @return boolean     True if history entry and
     *                        fetch succeeded
     * @access public
     */
    function back()
    {
        if (!$this->history->back()) {
            return false;
        }
        $content = $this->retry();
        if (!$content) {
            $this->history->forward();
        }
        return $content;
    }

    /**
     *    Equivalent to hitting the forward button on the
     *    browser. The browser history is unchanged on
     *    failure. The page content is refetched as there
     *    is no concept of content caching in Mimini.
     * @return boolean     True if history entry and
     *                        fetch succeeded
     * @access public
     */
    function forward()
    {
        if (!$this->history->forward()) {
            return false;
        }
        $content = $this->retry();
        if (!$content) {
            $this->history->back();
        }
        return $content;
    }

    /**
     *    Retries a request after setting the authentication
     *    for the current realm.
     * @param string $username Username for realm.
     * @param string $password Password for realm.
     * @return boolean            True if successful fetch. Note
     *                               that authentication may still have
     *                               failed.
     * @access public
     */
    function authenticate($username, $password)
    {
        if (!$this->page->getRealm()) {
            return false;
        }
        $url = $this->page->getUrl();
        if (!$url) {
            return false;
        }
        $this->user_agent->setIdentity(
            $url->getHost(),
            $this->page->getRealm(),
            $username,
            $password);
        return $this->retry();
    }

    /**
     *    Accessor for a breakdown of the frameset.
     * @return array   Hash tree of frames by name
     *                    or index if no name.
     * @access public
     */
    function getFrames()
    {
        return $this->page->getFrames();
    }

    /**
     *    Accessor for current frame focus. Will be
     *    false if no frame has focus.
     * @return array    Label if any, otherwise
     *                                      the position in the frameset
     *                                      or false if none.
     * @access public
     */
    function getFrameFocus()
    {
        return $this->page->getFrameFocus();
    }

    /**
     *    Sets the focus by index. The integer index starts from 1.
     * @param integer $choice Chosen frame.
     * @return boolean           True if frame exists.
     * @access public
     */
    function setFrameFocusByIndex($choice)
    {
        return $this->page->setFrameFocusByIndex($choice);
    }

    /**
     *    Sets the focus by name.
     * @param string $name Chosen frame.
     * @return boolean        True if frame exists.
     * @access public
     */
    function setFrameFocus($name)
    {
        return $this->page->setFrameFocus($name);
    }

    /**
     *    Clears the frame focus. All frames will be searched
     *    for content.
     * @access public
     */
    function clearFrameFocus()
    {
        return $this->page->clearFrameFocus();
    }

    /**
     *    Accessor for last error.
     * @return string        Error from last response.
     * @access public
     */
    function getTransportError()
    {
        return $this->page->getTransportError();
    }

    /**
     *    Accessor for current MIME type.
     * @return string    MIME type as string; e.g. 'text/html'
     * @access public
     */
    function getMimeType()
    {
        return $this->page->getMimeType();
    }

    /**
     *    Accessor for last response code.
     * @return integer    Last HTTP response code received.
     * @access public
     */
    function getResponseCode()
    {
        return $this->page->getResponseCode();
    }

    /**
     *    Accessor for last Authentication type. Only valid
     *    straight after a challenge (401).
     * @return string    Description of challenge type.
     * @access public
     */
    function getAuthentication()
    {
        return $this->page->getAuthentication();
    }

    /**
     *    Accessor for last Authentication realm. Only valid
     *    straight after a challenge (401).
     * @return string    Name of security realm.
     * @access public
     */
    function getRealm()
    {
        return $this->page->getRealm();
    }

    /**
     *    Accessor for current URL of page or frame if
     *    focused.
     * @return string    Location of current page or frame as
     *                      a string.
     */
    function getUrl()
    {
        $url = $this->page->getUrl();
        return $url ? $url->asString() : false;
    }

    /**
     *    Accessor for base URL of page if set via BASE tag
     * @return string    base URL
     */
    function getBaseUrl()
    {
        $url = $this->page->getBaseUrl();
        return $url ? $url->asString() : false;
    }

    /**
     *    Accessor for raw bytes sent down the wire.
     * @return string      Original text sent.
     * @access public
     */
    function getRequest()
    {
        return $this->page->getRequest();
    }

    /**
     *    Accessor for raw header information.
     * @return string      Header block.
     * @access public
     */
    function getHeaders()
    {
        return $this->page->getHeaders();
    }

    /**
     *    Accessor for raw page information.
     * @return string      Original text content of web page.
     * @access public
     */
    function getContent()
    {
        return $this->page->getRaw();
    }

    /**
     *    Accessor for plain text version of the page.
     * @return string      Normalised text representation.
     * @access public
     */
    function getContentAsText()
    {
        return $this->page->getText();
    }

    /**
     *    Accessor for parsed title.
     * @return string     Title or false if no title is present.
     * @access public
     */
    function getTitle()
    {
        return $this->page->getTitle();
    }

    /**
     *    Accessor for a list of all links in current page.
     * @return array   List of urls with scheme of
     *                    http or https and hostname.
     * @access public
     */
    function getUrls()
    {
        return $this->page->getAllUrls();
    }

    /**
     *    Sets all form fields with that name.
     * @param string $selector Name/label/id/MiminiSelector of field in forms.
     * @param string $value New value of field.
     * @param int $position
     * @return boolean        True if field exists, otherwise false.
     * @access public
     */
    function setField($selector, $value, $position = null){
        return $this->page->setField($selector, $value, $position);
    }


    /**
     *    Accessor for a form element value within the page.
     *    Finds the first match.
     * @param string|array|MiminiSelector $selector id/name/label or attributes
     *                                              #id for id
     *                                              .class for class name
     *                                              @label for label
     *                                              name for name
     * @return string/boolean     A value if the field is
     *                               present, false if unchecked
     *                               and null if missing.
     * @access public
     */
    function getField($selector)
    {
        return $this->page->getField($selector);
    }

    /**
     * Get form by attributes or id/name
     * @param string|array|MiminiSelector $selector id/name/label or attributes
     *                                              #id for id
     *                                              .class for class name
     *                                              +name for name
     *                                              label for label
     *
     * @param int $offset The offset if multiple forms
     * @return MiminiForm
     */
    function getForm($selector,$offset=0){
        return $this->page->getForm($selector,$offset);
    }

    /**
     * Submit a form
     * @param MiminiForm $form form to submit or selector to select the form
     * @param array $additional additional param to submit
     * @return boolean|string Content on success or false
     */
    function submitForm($form,$additional=array()){
        if(!$form instanceof MiminiForm){
            $form=$this->getForm($form);
        }
        $success = $this->load(
            $form->getAction(),
            $form->submitEncode($additional));
        return ($success ? $this->getContent() : $success);
    }
    /**
     *    Clicks the submit button by label. The owning
     *    form will be submitted by this.
     * @param string|array|MiminiSelector $selector id/name/label or attributes
     *                                              #id for id
     *                                              .class for class name
     *                                              +name for name
     *                                              Label for Label
     * @param array $additional Additional form data.
     * @return string/boolean  Page on success.
     * @access public
     */
    function clickSubmit($selector = 'Submit', $additional = array())
    {
        if (!($form = $this->page->getFormBySubmit($selector))) {
            return false;
        }
        $success = $this->load(
            $form->getAction(),
            $form->submitButton($selector, $additional));
        return ($success ? $this->getContent() : $success);
    }

    /**
     *    Clicks the submit image by some kind of label. Usually
     *    the alt tag or the nearest equivalent. The owning
     *    form will be submitted by this. Clicking outside of
     *    the boundary of the coordinates will result in
     *    a failure.
     * @param string|array|MiminiSelector $selector id/name/label or attributes
     *                                              #id for id
     *                                              .class for class name
     *                                              +name for name
     *                                              label for label
     * @param integer $x X-coordinate of imaginary click.
     * @param integer $y Y-coordinate of imaginary click.
     * @param array $additional Additional form data.
     * @return string/boolean  Page on success.
     * @access public
     */
    function clickImage($selector, $x = 1, $y = 1, $additional = array())
    {
        if (!($form = $this->page->getFormByImage($selector))) {
            return false;
        }
        $success = $this->load(
            $form->getAction(),
            $form->submitImage($selector, $x, $y, $additional));
        return ($success ? $this->getContent() : $success);
    }

    /**
     *    Tests to see if a submit button exists with this
     *    label.
     * @param string|array|MiminiSelector $selector id/name/label or attributes
     *                                              #id for id
     *                                              .class for class name
     *                                              +name for name
     *                                              label for label
     * @return boolean         True if present.
     * @access public
     */
    function isSubmit($selector)
    {
        return (boolean)$this->page->getFormBySubmit($selector);
    }

    /**
     *    Tests to see if an image exists with this
     *    title or alt text.
     * @param string $label Image text.
     * @return boolean         True if present.
     * @access public
     */
    function isImage($label)
    {
        return (boolean)$this->page->getFormByImage(new MiminiByLabel($label));
    }

    /**
     *    Finds a URL by label. Will find the first link
     *    found with this link text by default, or a later
     *    one if an index is given. The match ignores case and
     *    white space issues.
     * @param string|array|MiminiSelector $selector Text between the anchor tags.
     * @param integer $index Link position counting from zero.
     * @return MiminiUrl|false   URL on success.
     * @access public
     */
    function getLink($selector, $index = 0)
    {
        $urls = $this->page->getUrls($selector);

        return isset($urls[$index])?$urls[$index]:false;
    }

    /**
     *    Follows a link by label. Will click the first link
     *    found with this link text by default, or a later
     *    one if an index is given. The match ignores case and
     *    white space issues.
     * @param string|array|MiminiSelector $selector Text between the anchor tags.
     * @param integer $index Link position counting from zero.
     * @return string/boolean   Page on success.
     * @access public
     */
    function clickLink($selector, $index = 0)
    {
        $url = $this->getLink($selector, $index);
        if ($url === false) {
            return false;
        }
        $this->load($url, new MiminiGetEncoding());
        return $this->getContent();
    }

    /**
     *    Clicks a visible text item. Will first try buttons,
     *    then links and then images.
     * @param string $label Visible text or alt text.
     * @return string/boolean      Raw page or false.
     * @access public
     */
    function click($label)
    {
        $raw = $this->clickSubmit($label);
        if (!$raw) {
            $raw = $this->clickLink($label);
        }
        if (!$raw) {
            $raw = $this->clickImage($label);
        }
        return $raw;
    }

    /**
     *    Tests to see if a click target exists.
     * @param string $label Visible text or alt text.
     * @return boolean         True if target present.
     * @access public
     */
    function isClickable($label)
    {
        return $this->isSubmit($label) || ($this->getLink($label) !== false) || $this->isImage($label);
    }

    /**
     * @return MiminiPage
     */
    function getPage(){
        return $this->page;
    }

    /**
     * Close browser
     * @return bool always return true
     */
    function close(){
        $this->user_agent->restart();
        if(isset(Mimini::$instances[$this->session])){
            unset(Mimini::$instances[$this->session]);
        }
        return true;
    }
    function __toString(){
        return $this->getContent();
    }
}