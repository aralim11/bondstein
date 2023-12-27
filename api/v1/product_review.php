<?php

/**
 * product review
 */

## set header
header('Content-Type: application/json');

## require review class & create object
require_once('./../classes/Review.php');
$review = new Review;

## get request data
$request = json_decode(file_get_contents("php://input"));

## submit product review
$review->product_review($request);
