<?php

class Mimini_Loader
{
    var $loadedFiles;

    function __construct()
    {
        $this->loadedFiles = array();
        $this->init();
    }

    function init()
    {
        $this->load_file(array(MIMINI_INC . '/compatibility.php', MIMINI_INC . '/mimini.php', MIMINI_INC . '/base.php'));
        $this->load_dir(MIMINI_INC . '/parsers');
        $this->load_dir(MIMINI_INC . '/agent');
        $this->load_dir(MIMINI_INC . '/page');
        $this->load_file(MIMINI_INC . '/browser.php');
        return $this;
    }

    /**
     * @param $file
     */
    function load_file($file)
    {
        if (is_array($file)) {
            foreach ($file as $f) {
                $this->load_file($f);
            }
        } else {
            if (file_exists($file) && !$this->is_loaded($file)) {
                include $file;
            } else {
                die('failed to load' . $file);
            }
        }
    }

    function is_loaded($file)
    {
        if (in_array($file, $this->loadedFiles)) {
            return true;
        }
        return false;
    }

    function load_dir($dir, $file_mark = '*.php')
    {
        $pattern = rtrim($dir, '\/') . '/' . $file_mark;
        $files = glob($pattern);
        foreach ($files as $file) {
            $this->load_file($file);
        }
    }
}

$mimini_loader = new Mimini_Loader();