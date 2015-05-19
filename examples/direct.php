<?php

require __DIR__ . "/common.php";

use Stored\Client;

if (Client::did_upload()) {
    var_dump(Client::get_upload_details());exit;
}

$upload = Client::client_upload(array('type' => 'image', 'name' => 'foobar'));
?>
<html>
<head>
    <title>Upload (through our servers)</title>
</head>
<body>
    <form id="fileupload" action="<?php echo $upload['url']?>" method="POST" enctype="multipart/form-data">
        <input type="file" name="foobar" multiple>
        <button type="submit" class="btn btn-primary start">
            <i class="glyphicon glyphicon-upload"></i>
            <span>Start upload</span>
        </button>
    </form>
</body>
</html>
