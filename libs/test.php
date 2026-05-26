<?php

include_once __DIR__ . '/LiTimeParser.php';

// Test-Data from my Battery
$data = "00 00 65 01 93 55 AA 00 E4 37 00 00 37 36 00 00 8F 0D 92 0D 91 0D 85 0D 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 18 00 1A 00 00 00 00 00 00 00 60 28 60 28 00 00 48 00 00 00 04 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 00 64 00 67 00 00 00 00 00 00 00 57 00 00 00 9B";

var_dump(LiTimeParser::parse(hex2bin(str_replace(' ', '', $data))));