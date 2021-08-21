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
    header('HTTP/1.1 ' . $status . ' ' . $messages[$status]);

    exit(json_encode($data));
}

$iLovePDFKey = empty($iLovePDFProjectId) || empty($iLovePDFProjectKey)
    ? ''
    : $iLovePDFProjectId . ':' . $iLovePDFProjectKey;

$fileCompressor = new Compressor(
    $tinyPngKey,
    $iLovePDFKey
);

if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'])) {
    jsonRequest(['data' => 'File not upload'], 500);
}

$uploadDir = __DIR__ . '/uploads/';
$fileName = basename($_FILES['file']['name']);
$newFileName = pathinfo($fileName, PATHINFO_FILENAME)
    . '_compressed.' . pathinfo($fileName, PATHINFO_EXTENSION);

if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fileName)) {
    jsonRequest(['data' => 'Upload file not moved'], 500);
}

$beforeFileSize = filesize($uploadDir . $fileName);
$fileCompressor->compressionImage(
    $uploadDir . $fileName,
    $uploadDir . $newFileName
);
$afterFileSize = filesize($uploadDir . $newFileName);;

unlink($uploadDir . $fileName);
unlink($uploadDir . $newFileName);

jsonRequest(
    [
        'before' => $beforeFileSize,
        'after' => $afterFileSize
    ]
);
