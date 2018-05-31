<?php
/**
 *  Base include file for Mimini
 * @package    Mimini
 * @subpackage Browser
 * @version    $Id: page.php 1938 2009-08-05 17:16:23Z dgheath $
 */


/**
 *    A wrapper for a web page.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiPage
{
    /**
     * @var array|MiminiAnchorTag[]
     */
    private $links = array();
    /**
     * @var MiminiTitleTag
     */
    private $title = false;
    /**
     * @var array|MiminiForm[]
     */
    private $forms = array();
    /**
     * @var array|MiminiTag[]
     */
    private $frames = array();
    private $transport_error;
    private $raw;
    private $text = false;
    private $sent;
    /**
     * @var MiminiHttpResponseHeaders
     */
    private $headers;
    private $method;
    private $url;
    private $base = false;
    private $request_data;
    private $browser;

    /**
     *    Parses a page ready to access it's contents.
     * @param MiminiHttpResponse $response Result of HTTP fetch.
     * @param MiminiBrowser $browser
     * @access public
     */
    function __construct($response = null,$browser=null)
    {
        if ($response) {
            $this->extractResponse($response);
        } else {
            $this->noResponse();
        }
        $this->setBrowser($browser);
    }

    function setBrowser($browser){
        $this->browser=$browser;
        return $this;
    }
    function getBrowser(){
        return $this->browser;
    }

    /**
     *    Extracts all of the response information.
     * @param MiminiHttpResponse $response Response being parsed.
     * @access private
     */
    protected function extractResponse($response)
    {
        $this->transport_error = $response->getError();
        $this->raw = $response->getContent();
        $this->sent = $response->getSent();
        $this->headers = $response->getHeaders();
        $this->method = $response->getMethod();
        $this->url = $response->getUrl();
        $this->request_data = $response->getRequestData();
    }

    /**
     *    Sets up a missing response.
     * @access private
     */
    protected function noResponse()
    {
        $this->transport_error = 'No page fetched yet';
        $this->raw = false;
        $this->sent = false;
        $this->headers = false;
        $this->method = 'GET';
        $this->url = false;
        $this->request_data = false;
    }

    /**
     *    Original request as bytes sent down the wire.
     * @return mixed              Sent content.
     * @access public
     */
    function getRequest()
    {
        return $this->sent;
    }

    /**
     *    Accessor for raw text of page.
     * @return string        Raw unparsed content.
     * @access public
     */
    function getRaw()
    {
        return $this->raw;
    }

    /**
     *    Accessor for plain text of page as a text browser
     *    would see it.
     * @return string        Plain text of page.
     * @access public
     */
    function getText()
    {
        if (!$this->text) {
            $this->text = MiminiPage::normalise($this->raw);
        }
        return $this->text;
    }

    /**
     *    Accessor for raw headers of page.
     * @return string       Header block as text.
     * @access public
     */
    function getHeaders()
    {
        if ($this->headers) {
            return $this->headers->getRaw();
        }
        return false;
    }

    /**
     *    Original request method.
     * @return string        GET, POST or HEAD.
     * @access public
     */
    function getMethod()
    {
        return $this->method;
    }

    /**
     *    Original resource name.
     * @return MiminiUrl        Current url.
     * @access public
     */
    function getUrl()
    {
        return $this->url;
    }

    /**
     *    Base URL if set via BASE tag page url otherwise
     * @return MiminiUrl        Base url.
     * @access public
     */
    function getBaseUrl()
    {
        return $this->base;
    }

    /**
     *    Original request data.
     * @return mixed              Sent content.
     * @access public
     */
    function getRequestData()
    {
        return $this->request_data;
    }

    /**
     *    Accessor for last error.
     * @return string        Error from last response.
     * @access public
     */
    function getTransportError()
    {
        return $this->transport_error;
    }

    /**
     *    Accessor for current MIME type.
     * @return string    MIME type as string; e.g. 'text/html'
     * @access public
     */
    function getMimeType()
    {
        if ($this->headers) {
            return $this->headers->getMimeType();
        }
        return false;
    }

    /**
     *    Accessor for HTTP response code.
     * @return integer    HTTP response code received.
     * @access public
     */
    function getResponseCode()
    {
        if ($this->headers) {
            return $this->headers->getResponseCode();
        }
        return false;
    }

    /**
     *    Accessor for last Authentication type. Only valid
     *    straight after a challenge (401).
     * @return string    Description of challenge type.
     * @access public
     */
    function getAuthentication()
    {
        if ($this->headers) {
            return $this->headers->getAuthentication();
        }
        return false;
    }

    /**
     *    Accessor for last Authentication realm. Only valid
     *    straight after a challenge (401).
     * @return string    Name of security realm.
     * @access public
     */
    function getRealm()
    {
        if ($this->headers) {
            return $this->headers->getRealm();
        }
        return false;
    }

    /**
     *    Accessor for current frame focus. Will be
     *    false as no frames.
     * @return array    Always empty.
     * @access public
     */
    function getFrameFocus()
    {
        return array();
    }

    /**
     *    Sets the focus by index. The integer index starts from 1.
     * @param integer $choice Chosen frame.
     * @return boolean           Always false.
     * @access public
     */
    function setFrameFocusByIndex($choice=null)
    {
        return $choice;
    }

    /**
     *    Sets the focus by name. Always fails for a leaf page.
     * @param string $name Chosen frame.
     * @return boolean        False as no frames.
     * @access public
     */
    function setFrameFocus($name)
    {
        return $name;
    }

    /**
     *    Clears the frame focus. Does nothing for a leaf page.
     * @access public
     */
    function clearFrameFocus()
    {
        return true;
    }

    /**
     * Set page frames
     * @param array $frames Frames to set
     * @return $this
     */
    function setFrames($frames)
    {
        $this->frames = $frames;
        return $this;
    }

    /**
     * @param $path
     * @param $page
     */
    function setFrame($path,$page){

    }

    /**
     *    Test to see if link is an absolute one.
     * @param string $url Url to test.
     * @return boolean        True if absolute.
     * @access protected
     */
    protected function linkIsAbsolute($url)
    {
        $parsed = new MiminiUrl($url);
        return (boolean)($parsed->getScheme() && $parsed->getHost());
    }

    /**
     *    Adds a link to the page.
     * @param MiminiAnchorTag $tag Link to accept.
     */
    function addLink($tag)
    {
        $this->links[] = $tag;
    }

    /**
     *    Set the forms
     * @param array $forms An array of MiminiForm objects
     */
    function setForms($forms)
    {
        $this->forms = $forms;
    }

    /**
     *    Test for the presence of a frameset.
     * @return boolean        True if frameset.
     * @access public
     */
    function hasFrames()
    {
        return count($this->frames) > 0;
    }

    /**
     *    Accessor for frame name and source URL for every frame that
     *    will need to be loaded. Immediate children only.
     * @return array|boolean     False if no frameset or
     *                              otherwise a hash of frame URLs.
     *                              The key is either a numerical
     *                              base one index or the name attribute.
     * @access public
     */
    function getFrameset()
    {
        if (!$this->hasFrames()) {
            return false;
        }
        $urls = array();
        for ($i = 0; $i < count($this->frames); $i++) {
            $name = $this->frames[$i]->getAttribute('name');
            $url = new MiminiUrl($this->frames[$i]->getAttribute('src'));
            $urls[$name ? $name : $i + 1] = $this->expandUrl($url);
        }
        return $urls;
    }

    /**
     *    Fetches a list of loaded frames.
     * @return array/string    Just the URL for a single page.
     * @access public
     */
    function getFrames()
    {
        $url = $this->expandUrl($this->getUrl());
        return $url->asString();
    }

    /**
     *    Accessor for a list of all links.
     * @return array   List of urls with scheme of
     *                    http or https and hostname.
     * @access public
     */
    function getAllUrls()
    {
        $all = array();
        foreach ($this->links as $link) {
            $all[] = $this->getUrlFromLink($link);
        }
        return $all;
    }
    function getAllLinks(){
        return $this->links;
    }

    /**
     *    Accessor for URLs by the link label. Label will match
     *    regardess of whitespace issues and case.
     * @param mixed $selector Text of link or any selector
     * @return array|MiminiUrl[]           List of links with that label.
     * @access public
     */
    function getUrls($selector)
    {
        return $this->getLinks($selector,true);
    }

    /**
     * @param null $selector
     * @param boolean $returnUrl return type urls or links
     * @return array|MiminiAnchorTag[]|MiminiUrl[]
     */
    function getLinks($selector=null,$returnUrl=false){
        $matches=array();
        $selector=MiminiSelectorFactory::getSelector($selector);
        foreach($this->links as $link){
            if($selector->isMatch($link)){
                if($returnUrl){
                    $matches[]=$this->getUrlFromLink($link);
                }else {
                    $matches[] = $link;
                }
            }
        }
        return $matches;
    }


    /**
     *    Converts a link tag into a target URL.
     * @param MiminiAnchorTag $link Parsed link.
     * @return MiminiUrl            URL with frame target if any.
     * @access private
     */
    protected function getUrlFromLink($link)
    {
        $url = $this->expandUrl($link->getHref());
        if ($link->getAttribute('target')) {
            $url->setTarget($link->getAttribute('target'));
        }
        return $url;
    }

    /**
     *    Expands expandomatic URLs into fully qualified
     *    URLs.
     * @param MiminiUrl|string $url Relative URL.
     * @return MiminiUrl            Absolute URL.
     * @access public
     */
    function expandUrl($url)
    {
        if (!is_object($url)) {
            $url = new MiminiUrl($url);
        }
        $location = $this->getBaseUrl() ? $this->getBaseUrl() : new MiminiUrl();
        return $url->makeAbsolute($location->makeAbsolute($this->getUrl()));
    }

    /**
     *    Sets the base url for the page.
     * @param string $url Base URL for page.
     */
    function setBase($url)
    {
        $this->base = new MiminiUrl($url);
    }

    /**
     *    Sets the title tag contents.
     * @param MiminiTitleTag $tag Title of page.
     */
    function setTitle($tag)
    {
        $this->title = $tag;
    }

    /**
     *    Accessor for parsed title.
     * @return string     Title or false if no title is present.
     * @access public
     */
    function getTitle()
    {
        if ($this->title) {
            return $this->title->getText();
        }
        return false;
    }

    /**
     * Get form by attributes
     * @param MiminiSelector|string|array $selector
     * @param int $offset
     * @return MiminiForm
     */
    function getForm($selector,$offset=0){
        $selector=MiminiSelectorFactory::getSelector($selector);
        $_offset=-1;
        foreach($this->forms as $form){
            if($selector->isMatch($form->getTag())){
                $_offset++;
                if($offset==$_offset){
                    return $form;
                }
            }
        }
        return false;
    }
    /**
     *    Finds a held form by button label. Will only
     *    search correctly built forms.
     * @param MiminiSelector $selector Button finder.
     * @return MiminiForm                    Form object containing
     *                                          the button.
     * @access public
     */
    function getFormBySubmit($selector)
    {
        $stop=count($this->forms);
        for ($i = 0; $i < $stop; $i++) {
            if ($this->forms[$i]->hasSubmit($selector)) {
                return $this->forms[$i];
            }
        }
        return null;
    }

    /**
     *    Finds a held form by image using a selector.
     *    Will only search correctly built forms.
     * @param MiminiSelector $selector Image finder.
     * @return MiminiForm               Form object containing
     *                                     the image.
     * @access public
     */
    function getFormByImage($selector)
    {
        $stop=count($this->forms);
        for ($i = 0; $i < $stop; $i++) {
            if ($this->forms[$i]->hasImage($selector)) {
                return $this->forms[$i];
            }
        }
        return null;
    }

    /**
     *    Sets a field on each form in which the field is
     *    available.
     * @param MiminiSelector|string $selector Field finder.
     * @param string $value Value to set field to.
     * @param int $position
     * @return boolean                    True if value is valid.
     * @access public
     */
    function setField($selector, $value, $position = null)
    {
        $is_set = false;
        $stop=count($this->forms);
        for ($i = 0; $i < $stop; $i++) {
            if ($this->forms[$i]->setField($selector, $value, $position)) {
                $is_set = true;
            }
        }
        return $is_set;
    }

    /**
     *    Accessor for a form element value within a page.
     * @param MiminiSelector $selector Field finder.
     * @return string/boolean             A string if the field is
     *                                       present, false if unchecked
     *                                       and null if missing.
     * @access public
     */
    function getField($selector)
    {
        $stop= count($this->forms);
        for ($i = 0; $i < $stop; $i++) {
            $value = $this->forms[$i]->getValue($selector);
            if (isset($value)) {
                return $value;
            }
        }
        return null;
    }

    /**
     *    Turns HTML into text browser visible text. Images
     *    are converted to their alt text and tags are supressed.
     *    Entities are converted to their visible representation.
     * @param string $html HTML to convert.
     * @return string             Plain text.
     * @access public
     */
    static function normalise($html)
    {
        $text = preg_replace('#<!--.*?-->#si', '', $html);
        $text = preg_replace('#<(script|option|textarea)[^>]*>.*?</\1>#si', '', $text);
        $text = preg_replace('#<img[^>]*alt\s*=\s*("([^"]*)"|\'([^\']*)\'|([a-zA-Z_]+))[^>]*>#', ' \2\3\4 ', $text);
        $text = preg_replace('#<[^>]*>#', '', $text);
        $text = html_entity_decode($text, ENT_QUOTES);
        $text = preg_replace('#\s+#', ' ', $text);
        return trim(trim($text), "\xA0");
    }
}
