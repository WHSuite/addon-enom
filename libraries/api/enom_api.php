<?php
namespace Addon\Enom\Libraries\Api;
class EnomApi
{

    public $cmd;

    private $live_api = 'https://reseller.enom.com/interface.asp';
    private $sandbox_api = 'https://resellertest.enom.com/interface.asp';

    public $reseller_uid;

    public $reseller_password;

    public $sandbox;

    public $base_url;

    public $global_params = array();

    /**
     * Constructor
     *
     * Sets up the Enom API
     */
    public function __construct()
    {
        $reseller_uid = \App::get('configs')->get('settings.enom.enom_uid');
        $reseller_password = \App::get('configs')->get('settings.enom.enom_password');

        $sandbox = false;
        $sandbox_enabled = \App::get('configs')->get('settings.enom.enom_enable_sandbox');
        $sandbox_enabled = '1';
        if ($sandbox_enabled == '1') {
            $sandbox = true;
            $this->base_url = $this->sandbox_api;
        } else {
            $this->base_url = $this->live_api;
        }

        $this->reseller_uid = $reseller_uid;
        $this->reseller_password = $reseller_password;
        $this->sandbox = $sandbox;

        $this->global_params = array(
            'uid' => $this->reseller_uid,
            'pw' => $this->reseller_password,
            'responsetype' => 'xml'
        );
    }

    /**
     * Get TLD
     *
     * Returns the TLD for a given domain name.
     *
     * @return string Returns the TLD
     */
    public function getTld($domain)
    {
        $domain_split = preg_split('/([\.])/', $domain, 2);
        $extension = '.'.$domain_split[1];

        return $extension;
    }

    /**
     * Get
     *
     * Performs a get request on Enom and returns the data as an object.
     *
     * @param  string The URI path to query
     * @param  array Optional paramiters to add to the get request
     * @return object Returns the get request data
     */
    public function get($params = array())
    {
        $params = $this->global_params + $params;
        return simplexml_load_string(\App::get('dispatcher')->load($this->base_url, "GET", $params));
    }

    /**
     * Post
     *
     * Performs a post request on Enom and returns the data as an object.
     *
     * @param  string The URI path to query
     * @param  array The post data to send
     * @return object Returns the post request data
     */
    public function post($url, $params)
    {
        $params = $this->global_params + $params;
        return simplexml_load_string(\App::get('dispatcher')->load($this->base_url, "POST", $params));
    }

}
