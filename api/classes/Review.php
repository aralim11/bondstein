<?php

/**
 * Review class
 */
require_once('Functions.php');

class Review extends Functions
{
    /**
     * product_review
     * @return boolean
     */
    public function product_review($request)
    {
        ## validate request method
        $validatePost = $this->validatePostRequest($_SERVER['REQUEST_METHOD']);
        if ($validatePost) {
            ## validate content-type
            $validateContentType = $this->validateContentType($_SERVER);
            if ($validateContentType) {
                ## check empty for request
                if (!empty($request->product_id) && !empty($request->user_id) && !empty($request->review_text)) {
                    ## check value
                    if ($this->check_numeric($request->product_id) && $this->check_numeric($request->user_id)) {
                        ## insert review
                        $sql = $this->connection->query("INSERT INTO product_review (product_id, user_id, review_text) VALUES ('{$request->product_id}', '{$request->user_id}', '{$request->review_text}')");

                        ## return message
                        if ($sql) {
                            $this->throwMessage('200', 'Product Review Added Successfully!!');
                        } else {
                            $this->throwMessage('401', 'Product Review Not Added!!');
                        }
                    }
                } else {
                    $this->throwMessage('401', 'All Field Required!!');
                }
            }
        }
    }
}
