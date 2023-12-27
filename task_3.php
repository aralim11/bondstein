<?php

## define veriables
$list_numbers = [1, 3, 5, 7, 10, 12];
$number = 12;

function twoSum($nums, $target)
{
    $n = count($nums);

    for ($i = 0; $i < $n - 1; $i++) {
        for ($j = $i + 1; $j < $n; $j++) {
            if ($nums[$i] + $nums[$j] === $target) {
                return [$i, $j];
            }
        }
    }
}

$data = twoSum($list_numbers, $number);
print_r($data);
