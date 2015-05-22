<?php

require __DIR__ . "/common.php";

use Stored\Client;

if ($client->uploadSuccess()) {
    $details = $client->getUploadDetails();
    var_dump($details);
    echo '<img src="//188.166.70.124/' . $details['id'] . '/image/square/150/grey"/>';
}

$upload_url = $client->getUploadUrl(array('type' => 'image', 'name' => 'foobar'));
?>
<html>
<head>
    <title>Upload (through our servers)</title>
</head>
<body>
    <form id="fileupload" action="<?php echo $upload_url?>" method="POST" enctype="multipart/form-data">
        <input type="file" name="foobar" multiple>
        <button type="submit" class="btn btn-primary start">
            <i class="glyphicon glyphicon-upload"></i>
            <span>Start upload</span>
        </button>
    </form>
</body>
</html>
