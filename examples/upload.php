<?php

require __DIR__ . "/common.php";

use Stored\Client;


if (!empty($_FILES['foobar'])) {
    var_dump(Client::store_upload('foobar', array('type' => 'image')));
}

?>
<html>
<head>
    <title>Upload (through our servers)</title>
</head>
<body>
    <form id="fileupload" action="upload.php" method="POST" enctype="multipart/form-data">
        <input type="file" name="foobar" multiple>
        <button type="submit" class="btn btn-primary start">
            <i class="glyphicon glyphicon-upload"></i>
            <span>Start upload</span>
        </button>
    </form>
</body>
</html>
