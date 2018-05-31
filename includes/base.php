<?php

/**
 *    Stashes an error for later. Useful for constructors
 *    until PHP gets exceptions.
 * @package Mimini
 * @subpackage Browser
 */
class MiminiStickyError
{
    private $error = 'Constructor not chained';

    /**
     *    Sets the error to empty.
     * @access public
     */
    function __construct()
    {
        $this->clearError();
    }

    /**
     *    Test for an outstanding error.
     * @return boolean           True if there is an error.
     * @access public
     */
    function isError()
    {
        return ($this->error != '');
    }

    /**
     *    Accessor for an outstanding error.
     * @return string     Empty string if no error otherwise
     *                       the error message.
     * @access public
     */
    function getError()
    {
        return $this->error;
    }

    /**
     *    Sets the internal error.
     * @param string  $error     Error message to stash.
     * @access protected
     */
    function setError($error)
    {
        $this->error = $error;
    }

    /**
     *    Resets the error state to no error.
     * @access protected
     */
    function clearError()
    {
        $this->setError('');
    }
}

interface MiminiParserInterface
{
    public function can();
}