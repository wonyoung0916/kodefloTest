<?php namespace Config;

use CodeIgniter\Config\BaseConfig;

class Cors extends BaseConfig
{
    public $allowedOrigins = ['*'];
    public $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE'];
    public $allowedHeaders = ['*'];
    public $exposedHeaders = [];
    public $maxAge = 0;
    public $supportsCredentials = false;
}