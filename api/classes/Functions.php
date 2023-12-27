<?php

/**
 * Functions class
 * define all needed functions, database connections
 */

class Functions
{
    public $connection;
    public $baseUrl = 'https://omni.ihelpbd.com/ihelpbd_social/';

    /**
     * __construct
     * mysqli database connection
     * @return void
     */
    public function __construct()
    {
        $server = "localhost";
        $userName = "root";
        $password = "";
        $db = "bond_stein";

        $this->connection = new mysqli($server, $userName, $password, $db);
    }

    /**
     * validatePostRequest
     * validate POST request
     * @return boolean
     */
    public function validatePostRequest($method)
    {
        if ($method == 'POST') {
            return true;
        } else {
            return $this->throwMessage('403', 'Request Method is Not Valid');
        }
    }

    /**
     * validateContentType
     * validate content type
     * @return boolean
     */
    public function validateContentType($contentType)
    {
        if (array_key_exists('CONTENT_TYPE', $contentType) && str_replace(' ', '', $contentType['CONTENT_TYPE']) == 'application/json') {
            return true;
        } else {
            return $this->throwMessage('400', 'Content-type is Not Valid');
        }
    }

    /**
     * throwMessage
     * @param message $code
     * @param message $message
     * @return json
     */
    public function throwMessage($code, $message)
    {
        echo json_encode(['status' => $code, 'data' => $message]);
    }

    /**
     * check_numeric
     * @param $request
     * @return void
     */
    public function check_numeric($data)
    {
        if (!empty($data)) {
            if (is_numeric($data) && $data > 0) {
                return true;
            } else {
                return $this->throwMessage('400', 'Product ID & User ID Must Numeric');
            }
        }
    }
}
