<?php

namespace Linguise\Script\Core;

defined('LINGUISE_SCRIPT_TRANSLATION') or die();

class Boundary
{
    /**
     * Generated boundary
     *
     * @var string|null
     */
    protected $boundary = null;

    /**
     * Array of boundaries to store
     * @var array
     */
    protected $fields = [];

    /**
     * Post field content
     *
     * @var string
     */
    protected $content = '';


    public function __construct()
    {
        $this->boundary = '------'.substr(str_shuffle(str_repeat($c='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(15/strlen($c)) )),1, 10);
    }

    /**
     * Return generated boundary
     *
     * @return string|null
     */
    public function getBoundary()
    {
        return $this->boundary;
    }

    /**
     * Add a field to the fields array
     *
     * @param $name
     * @param $value
     * @return void
     */
    public function addPostFields($name, $value)
    {
        $this->fields[$name] = $value;
    }

    /**
     * Return the end of a boundary
     * @return string
     */
    protected function endPostFields()
    {
        return '--'.$this->boundary.'--';
    }

    /**
     * Retrieve the Content-Disposition header
     *
     * @return string
     */
    public function getContent()
    {
        $content = '';
        foreach ($this->fields as $name => $value) {
            $content .= '--'.$this->boundary."\r\n";
            $content .= "Content-Disposition: form-data; name=\"".$name."\"\r\n\r\n".$value."\r\n";
        }
        $content .= $this->endPostFields();
        return $content;
    }
}