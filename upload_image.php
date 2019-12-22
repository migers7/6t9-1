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
$isAdmin = ApiHelper::rowExists("SELECT id FROM users WHERE username = '$username' AND (isAdmin = 1 OR isStaff = 1 OR isOwner = 1)");

// check if image sharing is enabled
if ($isAdmin == false) {
    $roomName = $headers["room_name"];
    if (ApiHelper::rowExists("SELECT id FROM rooms WHERE name = '$roomName' AND imageShareEnabled = 1") == false) {
        echo ApiHelper::buildErrorResponse("Image sharing is not allowed in this room.");
        exit(0);
    }

// check if user is blocked from image sharing
    if (ApiHelper::rowExists("SELECT id FROM users WHERE username = '$username' AND can_share_image = 0")) {
        echo ApiHelper::buildErrorResponse("You have been blocked from file sharing");
        exit(0);
    }

// check if user is moderator or room owner
    if (ApiHelper::rowExists("SELECT id FROM modship WHERE roomName = '$roomName' AND username = '$username'") == false) {
        if (ApiHelper::rowExists("SELECT id FROM rooms WHERE name = '$roomName' AND owner = '$username'") == false) {
            echo ApiHelper::buildErrorResponse("You do not have authorization to share image.");
            exit(0);
        }
    }
}

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
$keyName = $SHARED_IMAGE_FOLDER . '/' . $username . '/' . basename($_FILES["file"]['tmp_name']) . date("Ymd_His");
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
    echo ApiHelper::buildErrorResponse("File size must be less than 2 MB");
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
    $sql = "INSERT INTO files (file_url, uploaded_by, uploaded_in, purpose) VALUES('$pathInS3', '$username', '$roomName', '$PURPOSE_SHARE')";
    $pdo = ApiHelper::getInstance();
    $updated = $pdo->exec($sql);
    $pdo = null;
    echo ApiHelper::buildSuccessResponse($pathInS3);
} catch (S3Exception $e) {
    echo ApiHelper::buildErrorResponse("Failed to upload image [S]");
} catch (Exception $e) {
    echo ApiHelper::buildErrorResponse("Failed to to upload image [E]");
}
