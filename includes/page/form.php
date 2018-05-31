<?php
/**
 *  Base include file for Mimini.
 * @package    Mimini
 * @subpackage Browser
 * @version    $Id: form.php 2013 2011-04-29 09:29:45Z pp11 $
 */


/**
 *    Form tag class to hold widget values.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiForm
{
    private $method;
    private $action;
    private $encoding;
    private $default_target;
    private $id;
    /**
     * @var array|MiminiButtonTag[]
     */
    private $buttons;
    /**
     * @var array|MiminiImageSubmitTag[]
     */
    private $images;
    /**
     * @var array|MiminiTagGroup[]|MiminiWidget[]
     */
    private $widgets;
    /**
     * @var array
     */
    private $radios;
    /**
     * @var array
     */
    private $checkboxes;
    /**
     * @var MiminiFormTag pointer to tag
     */
    private $tag;
    /**
     * @var MiminiBrowser
     */
    private $browser;

    /**
     *    Starts with no held controls/widgets.
     * @param MiminiFormTag|MiminiTag $tag Form tag to read.
     * @param MiminiPage $page Holding page.
     */
    function __construct($tag, $page)
    {
        $this->method = $tag->getAttribute('method');
        $this->action = $this->createAction($tag->getAttribute('action'), $page);
        $this->encoding = $this->setEncodingClass($tag);
        $this->default_target = false;
        $this->id = $tag->getAttribute('id');
        $this->buttons = array();
        $this->images = array();
        $this->widgets = array();
        $this->radios = array();
        $this->checkboxes = array();
        $this->tag=$tag;
        $this->browser=$page->getBrowser();
    }

    /**
     * @return MiminiFormTag
     */
    function getTag(){
        return $this->tag;
    }

    /**
     *    Creates the request packet to be sent by the form.
     * @param MiminiTag $tag Form tag to read.
     * @return string               Packet class.
     * @access private
     */
    protected function setEncodingClass($tag)
    {
        if (strtolower($tag->getAttribute('method')) == 'post') {
            if (strtolower($tag->getAttribute('enctype')) == 'multipart/form-data') {
                return 'MiminiMultipartEncoding';
            }
            return 'MiminiPostEncoding';
        }
        return 'MiminiGetEncoding';
    }

    /**
     *    Sets the frame target within a frameset.
     * @param string $frame Name of frame.
     * @access public
     */
    function setDefaultTarget($frame)
    {
        $this->default_target = $frame;
    }

    /**
     *    Accessor for method of form submission.
     * @return string           Either get or post.
     * @access public
     */
    function getMethod()
    {
        return ($this->method ? strtolower($this->method) : 'get');
    }

    /**
     *    Combined action attribute with current location
     *    to get an absolute form target.
     * @param string $action Action attribute from form tag.
     * @param MiminiPage $page Page location.
     * @return MiminiUrl        Absolute form target.
     */
    protected function createAction($action, $page)
    {
        if (($action === '') || ($action === false)) {
            return $page->expandUrl($page->getUrl());
        }
        return $page->expandUrl(new MiminiUrl($action));
    }

    /**
     *    Absolute URL of the target.
     * @return MiminiUrl           URL target.
     * @access public
     */
    function getAction()
    {
        $url = $this->action;
        if ($this->default_target && !$url->getTarget()) {
            $url->setTarget($this->default_target);
        }
        if ($this->getMethod() == 'get') {
            $url->clearRequest();
        }
        return $url;
    }

    /**
     *    Creates the encoding for the current values in the
     *    form.
     * @return MiminiEncoding    Request to submit.
     * @access private
     */
    protected function encode()
    {
        $class = $this->encoding;
        $encoding = new $class();
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            $this->widgets[$i]->write($encoding);
        }
        return $encoding;
    }

    /**
     *    ID field of form for unique identification.
     * @return string           Unique tag ID.
     * @access public
     */
    function getId()
    {
        return $this->id;
    }

    /**
     *    Adds a tag contents to the form.
     * @param MiminiWidget $tag Input tag to add.
     */
    function addWidget($tag)
    {
        if (strtolower($tag->getAttribute('type')) == 'submit') {
            $this->buttons[] = $tag;
        } elseif (strtolower($tag->getAttribute('type')) == 'image') {
            $this->images[] = $tag;
        } elseif ($tag->getName()) {
            $this->setWidget($tag);
        }
    }

    /**
     *    Sets the widget into the form, grouping radio
     *    buttons if any.
     * @param MiminiWidget $tag Incoming form control.
     * @access private
     */
    protected function setWidget($tag)
    {
        if (strtolower($tag->getAttribute('type')) == 'radio') {
            $this->addRadioButton($tag);
        } elseif (strtolower($tag->getAttribute('type')) == 'checkbox') {
            $this->addCheckbox($tag);
        } else {
            $this->widgets[] = &$tag;
        }
    }

    /**
     *    Adds a radio button, building a group if necessary.
     * @param MiminiRadioButtonTag|MiminiWidget $tag Incoming form control.
     * @access private
     */
    protected function addRadioButton($tag)
    {
        if (!isset($this->radios[$tag->getName()])) {
            $this->widgets[] = new MiminiRadioGroup();
            $this->radios[$tag->getName()] = count($this->widgets) - 1;
        }
        $this->widgets[$this->radios[$tag->getName()]]->addWidget($tag);
    }

    /**
     *    Adds a checkbox, making it a group on a repeated name.
     * @param MiminiCheckboxTag|MiminiWidget $tag Incoming form control.
     * @access private
     */
    protected function addCheckbox($tag)
    {
        if (!isset($this->checkboxes[$tag->getName()])) {
            $this->widgets[] = $tag;
            $this->checkboxes[$tag->getName()] = count($this->widgets) - 1;
        } else {
            $index = $this->checkboxes[$tag->getName()];
            if (!MiminiCompatibility::isA($this->widgets[$index], 'MiminiCheckboxGroup')) {
                $previous = $this->widgets[$index];
                $this->widgets[$index] = new MiminiCheckboxGroup();
                $this->widgets[$index]->addWidget($previous);
            }
            $this->widgets[$index]->addWidget($tag);
        }
    }

    /**
     *    Extracts current value from form.
     * @param MiminiSelector|string|array $selector Criteria to apply.
     * @return string/array              Value(s) as string or null
     *                                      if not set.
     * @access public
     */
    function getValue($selector)
    {
        $selector=MiminiSelectorFactory::getSelector($selector);
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($selector->isMatch($this->widgets[$i])) {
                return $this->widgets[$i]->getValue();
            }
        }
        foreach ($this->buttons as $button) {
            if ($selector->isMatch($button)) {
                return $button->getValue();
            }
        }
        return null;
    }

    /**
     *    Sets a widget value within the form by selector or name/label/id.
     * @param MiminiSelector|string $selector or name/label/id Criteria to apply.
     * @param string $value Value to input into the widget.
     * @param int $position
     * @return boolean                   True if value is legal, false
     *                                      otherwise. If the field is not
     *                                      present, nothing will be set.
     * @access public
     */
    function setField($selector, $value, $position = null){
        $selector=MiminiSelectorFactory::getSelector($selector);
        $success = false;
        $_position = 0;
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($selector->isMatch($this->widgets[$i])) {
                $_position++;
                if ($position === null or $_position === (int)$position) {
                    if ($this->widgets[$i]->setValue($value)) {
                        $success = true;
                    }
                }
            }
        }
        return $success;
    }

    /**
     *    Sets all form fields with that name. Will use label if
     *    one is available (not yet implemented).
     * @param string $name Name of field in forms.
     * @param string $value New value of field.
     * @param int $position
     * @return boolean        True if field exists, otherwise false.
     * @access public
     */
    function setFieldByName($name, $value, $position = null){
        return $this->setField(new MiminiByName($name), $value, $position);
    }

    /**
     *    Used by the page object to set widgets labels to
     *    external label tags.
     * @param MiminiSelector $selector Criteria to apply.
     * @param $label
     * @access public
     */
    function attachLabelBySelector($selector, $label)
    {
        for ($i = 0, $count = count($this->widgets); $i < $count; $i++) {
            if ($selector->isMatch($this->widgets[$i])) {
                if (method_exists($this->widgets[$i], 'setLabel')) {
                    $this->widgets[$i]->setLabel($label);
                    return;
                }
            }
        }
    }

    /**
     *    Test to see if a form has a submit button.
     * @param MiminiSelector $selector Criteria to apply.
     * @return boolean                   True if present.
     * @access public
     */
    function hasSubmit($selector)
    {
        $selector=MiminiSelectorFactory::getSelector($selector);
        foreach ($this->buttons as $button) {
            if ($selector->isMatch($button)) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Test to see if a form has an image control.
     * @param MiminiSelector $selector Criteria to apply.
     * @return boolean                   True if present.
     * @access public
     */
    function hasImage($selector)
    {
        $selector=MiminiSelectorFactory::getSelector($selector);
        foreach ($this->images as $image) {
            if ($selector->isMatch($image)) {
                return true;
            }
        }
        return false;
    }

    /**
     *    Gets the submit values for a selected button.
     * @param MiminiSelector $selector Criteria to apply.
     * @param array $additional Additional data for the form.
     * @return MiminiEncoding            Submitted values or false
     *                                      if there is no such button
     *                                      in the form.
     * @access public
     */
    function submitButton($selector, $additional = array())
    {
        $selector=MiminiSelectorFactory::getSelector($selector);
        $additional = $additional ? $additional : array();
        foreach ($this->buttons as $button) {
            if ($selector->isMatch($button)) {
                $encoding = $this->encode();
                $button->write($encoding);
                if ($additional) {
                    $encoding->merge($additional);
                }
                //var_dump($encoding->encode());die;
                return $encoding;
            }
        }
        return false;
    }

    /**
     *    Gets the submit values for an image.
     * @param MiminiSelector $selector Criteria to apply.
     * @param integer $x X-coordinate of click.
     * @param integer $y Y-coordinate of click.
     * @param array $additional Additional data for the form.
     * @return MiminiEncoding            Submitted values or false
     *                                      if there is no such button in the
     *                                      form.
     * @access public
     */
    function submitImage($selector, $x, $y, $additional = array())
    {
        $selector=MiminiSelectorFactory::getSelector($selector);
        $additional = $additional ? $additional : array();
        foreach ($this->images as $image) {
            if ($selector->isMatch($image)) {
                $encoding = $this->encode();
                $image->write($encoding, $x, $y);
                if ($additional) {
                    $encoding->merge($additional);
                }
                return $encoding;
            }
        }
        return false;
    }

    /**
     *    Simply submits the form without the submit button
     *    value. Used when there is only one button or it
     *    is unimportant.
     * @param array $additional
     * @return MiminiEncoding           Submitted values.
     * @access public
     */
    function submitEncode($additional = array())
    {
        $encoding = $this->encode();
        if ($additional) {
            $encoding->merge($additional);
        }
        return $encoding;
    }

    /**
     * submit current form and return content
     * @param array $additional
     * @return boolean/string
     */
    function submit($additional=array()){

        return $this->browser->submitForm($this,$additional);
        /*$success = $this->browser->load(
            $this->getAction(),
            $this->submitEncode($additional));
        return ($success ? $this->browser->getContent() : $success);
        */
    }
}