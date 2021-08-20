<?php

declare(strict_types=1);

use Bearman\FileCompressor\Compressor;

require_once(__DIR__ . '/../vendor/autoload.php');

$tinyPngKey = 'crazy';
$iLovePDFProjectId = '';
$iLovePDFProjectKey = '';

/**
 * @param array $data
 * @param int   $status
 *
 * @return false|string
 */
function jsonRequest(array $data, $status = 200)
{
    $messages = [
        200 => 'OK',
        400 => 'Bad Request',
        500 => 'Internal Server Error'
    ];

    header('Content-Type: application/json');
    header($messages[$status], true, $status);

    exit(json_encode($data));
}

$fileCompressor = new Compressor(
    $tinyPngKey,
    $iLovePDFProjectId . ':' . $iLovePDFProjectKey
);

$uploadDir = __DIR__ . '/uploads/';
$fileName = basename($_FILES['file']['name']);

if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName)) {
    jsonRequest(['data' => 'Upload file not moved'], 500);
}

$beforeFileSize = filesize($uploadDir . $fileName);
$fileCompressor->compressionImage($uploadDir . $fileName);
$afterFileSize = filesize($uploadDir . $fileName);

jsonRequest(
    [
        'before' => $beforeFileSize,
        'after' => $afterFileSize
    ]
);
