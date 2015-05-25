<?php

require __DIR__ . "/common.php";

if (!empty($_FILES['foobar'])) {
    var_dump($client->storeUpload('foobar', array('type' => 'image', 'slug' => 'foobar/xxx')));
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
