<?php
/**
 * Created by PhpStorm.
 * User: maruf
 * Date: 10/28/2018
 * Time: 11:36 PM
 */
require 'vendor/autoload.php';

use Aws\S3\Exception\S3Exception;
use Aws\S3\S3Client;

include_once 'config.php';
include_once 'file_config.php';
include_once 'auth_util.php';

$headers = apache_request_headers();

$validationError = AuthUtil::validate($headers);
if ($validationError != null) {
    echo $validationError;
    exit(0);
}

$username = $headers["username"];

// AWS Info
$bucketName = $BUCKET_NAME;
$s3 = new S3Client([
    'version' => $VERSION,
    'region' => $REGION,
    'credentials' => [
        'key' => $API_KEY,
        'secret' => $API_SECRET
    ]
]);

if (isset($_FILES["file"]) == false) {
    echo ApiHelper::buildErrorResponse("Invalid file.");
    exit(0);
}


// For this, I would generate a unqiue random string for the key name. But you can do whatever.
$keyName = $PROFILE_PICTURE_FOLDER . '/' . $username . '/' . basename($_FILES["file"]['tmp_name']) . date("Ymd_His");
$pathInS3 = 'https://s3.us-east-1.amazonaws.com/' . $bucketName . '/' . $keyName;

$file = $_FILES["file"]['tmp_name'];
$filesize = filesize($file); // bytes
$filesize = round($filesize / 1024 / 1024, 1);

$ext = strtolower(pathinfo($_FILES["file"]['name'], PATHINFO_EXTENSION));
if (in_array($ext, $ALLOWED_FILES) == false) {
    echo ApiHelper::buildErrorResponse("Invalid file. We only accept png, jpeg or jpg file. Your file is $ext");
    exit(0);
}

if ($filesize > $MAX_FILE_SIZE) {
    echo ApiHelper::buildErrorResponse("File size must be less than $MAX_FILE_SIZE MB");
    exit(0);
}
// Add it to S3
try {
    // Uploaded:
    $s3->putObject(
        array(
            'Bucket' => $bucketName,
            'Key' => $keyName,
            'SourceFile' => $file,
            'StorageClass' => $STORAGE_CLASS,
            'ACL' => $PUBLIC_READ
        )
    );
    $sql = "INSERT INTO files (file_url, uploaded_by, uploaded_in, purpose) VALUES('$pathInS3', '$username', '', '$PURPOSE_PROFILE_PICTURE')";
    $pdo = ApiHelper::getInstance();
    $updated = $pdo->exec($sql);
    $sql = "UPDATE users SET dp = '$pathInS3' WHERE username = '$username'";
    $updated = $pdo->exec($sql);
    $pdo = null;
    if ($updated) {
        echo ApiHelper::buildSuccessResponse($pathInS3);
    } else {
        echo ApiHelper::buildErrorResponse("Failed to update profile picture");
    }
} catch (S3Exception $e) {
    echo ApiHelper::buildErrorResponse("Failed to update profile picture [S]");
} catch (Exception $e) {
    echo ApiHelper::buildErrorResponse("Failed to update profile picture[E]");
}
