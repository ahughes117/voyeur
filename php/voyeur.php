<?php

//remove for production version
error_reporting(E_ALL | E_STRICT);
ini_set("display_errors", "1");  

require_once('src/entities/preview.php');

if (isset($_GET['url'])) {
    try {
        $preview = Preview::create_url_preview($_GET['url']);

        if (!$preview)
            throw new Exception("Error while processing url");

        $data = array(
            "image" => $preview->image,
            "url" => $preview->url,
            "description" => $preview->description,
            "title" => $preview->title
        );

        echo json_encode($data);
    } catch (Exception $x) {
        echo json_encode(array('error' => $x->getMessage()));
    }
}
