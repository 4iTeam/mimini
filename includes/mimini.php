<?php

/**
 *    Registry and test context. Includes a few
 *    global options that I'm slowly getting rid of.
 * @package  Mimini
 * @subpackage   UnitTester
 */
class Mimini
{

    /**
     * Instances
     * @var MiminiBrowser[]
     *
     */
    static $instances;
    /**
     *    Reads the Mimini version from the release file.
     * @return string        Version string.
     */
    static function getVersion()
    {
        $content = file(dirname(__FILE__) . '/VERSION');
        return trim($content[0]);
    }


    /**
     *    Sets proxy to use on all requests for when
     *    testing from behind a firewall. Set host
     *    to false to disable. This will take effect
     *    if there are no other proxy settings.
     * @param string $proxy Proxy host as URL.
     * @param string $username Proxy username for authentication.
     * @param string $password Proxy password for authentication.
     */
    static function useProxy($proxy, $username = null, $password = null)
    {
        $registry = &Mimini::getRegistry();
        $registry['DefaultProxy'] = $proxy;
        $registry['DefaultProxyUsername'] = $username;
        $registry['DefaultProxyPassword'] = $password;
    }

    /**
     * Start Mimini from a previous session or a new session
     * @param bool|false $session
     * @return MiminiBrowser
     */
    static function open($session = false){
        if($session===false){
            return new MiminiBrowser(sha1('miminiBrowserDefault'));
        }
        $session=trim($session);
        if($session){
            @file_put_contents(MIMINI_DATA.'/last',$session);
            if(isset(self::$instances[$session])) {
                return self::$instances[$session];
            }else{
                return self::$instances[$session] = new MiminiBrowser($session);
            }
        }
        return new MiminiBrowser();
    }

    /**
     * Private browsing
     */
    static function openPrivate(){
        return self::open(null);
    }
    static function lastSession(){
        if(!$last=trim(file_get_contents(MIMINI_DATA.'/last'))){
            $last=md5(time());
        }
        self::open($last);
    }
    static function reset(){
        file_put_contents(MIMINI_DATA.'/last','');
        array_map('unlink', glob(MIMINI_DATA.'/*'));
    }

    /**
     * Close browser
     * @param MiminiBrowser $browser
     */
    static function close($browser){
        $browser->close();
    }

    /**
     *    Accessor for default proxy host.
     * @return string       Proxy URL.
     */
    static function getDefaultProxy()
    {
        $registry = &Mimini::getRegistry();
        return $registry['DefaultProxy'];
    }

    /**
     *    Accessor for default proxy username.
     * @return string    Proxy username for authentication.
     */
    static function getDefaultProxyUsername()
    {
        $registry = &Mimini::getRegistry();
        return $registry['DefaultProxyUsername'];
    }

    /**
     *    Accessor for default proxy password.
     * @return string    Proxy password for authentication.
     */
    static function getDefaultProxyPassword()
    {
        $registry = &Mimini::getRegistry();
        return $registry['DefaultProxyPassword'];
    }

    /**
     *    Accessor for default HTML parsers.
     * @return MiminiParserInterface[]     List of parsers to try in
     *                                      order until one responds true
     *                                      to can().
     */
    static function getParsers()
    {
        $registry = &Mimini::getRegistry();
        return $registry['Parsers'];
    }

    /**
     *    Set the list of HTML parsers to attempt to use by default.
     * @param array $parsers List of parsers to try in
     *                             order until one responds true
     *                             to can().
     */
    static function setParsers($parsers)
    {
        $registry = &Mimini::getRegistry();
        $registry['Parsers'] = $parsers;
    }

    /**
     *    Accessor for global registry of options.
     * @return array           All stored values.
     */
    protected static function &getRegistry()
    {
        static $registry = false;
        if (!$registry) {
            $registry = Mimini::getDefaults();
        }
        return $registry;
    }


    /**
     *    Constant default values.
     * @return array       All registry defaults.
     */
    protected static function getDefaults()
    {
        return array(
            'Parsers' => false,
            'DefaultProxy' => false,
            'DefaultProxyUsername' => false,
            'DefaultProxyPassword' => false,
        );
    }


}
