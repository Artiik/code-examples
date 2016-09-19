<?php

require_once 'config.php';

ppStart();
//ppRequireLogin();

$site_id = (int)$_REQUEST['id'];

$res = mysql_query("SELECT * FROM sites WHERE id = $site_id LIMIT 1");
$site = mysql_fetch_assoc($res);

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    if (isset($_REQUEST['id']) && isset($_REQUEST['design']) && isset($_REQUEST['code_type'])) {
        createZipArchive($site['domain'], $site['id'], (int)$_REQUEST['code_type'], (int)$_REQUEST['design']);
    } else {
        $tmpl = startTemplate('code.tmpl');
        $tmpl->setVar('site_id', $site['id']);
        $tmpl->setVar('domain', $site['domain']);
        $tmpl->pparse();
        ppFinish();
    }
} else if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_REQUEST['id']) && isset($_REQUEST['design']) && isset($_REQUEST['code_type'])) {
        createZipArchive($site['domain'], $site['id'], (int)$_REQUEST['code_type'], (int)$_REQUEST['design'],true);
    }
} else {
    $design = (int)$_REQUEST['design'];
    $script = (int)$_REQUEST['code_type'];
    createZipArchive($site['domain'], $site['id'], $script, $design);
}

function createZipArchive($domain_name, $site_id, $code_type, $design, $google_drive = false) {
    $filename = urlencode($domain_name) . '.zip';

    $zip = new ZipArchive();
    $zip->open(sys_get_temp_dir() . '/' . $filename, ZIPARCHIVE::CREATE);

    $config = file_get_contents('code/config.tmpl');
    $config_local = file_get_contents('code/config-local.tmpl');

    $config_local = preg_replace('/<TMPL_VAR NAME="site_id">/is', $site_id, $config_local);
    $config_local = preg_replace('/<TMPL_VAR NAME="aff_id">/is', $_SESSION['aff_id'], $config_local);
    $config = preg_replace('/<TMPL_VAR NAME="billing-server">/is', "billing." . preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']), $config);
    $config = preg_replace('/<TMPL_VAR NAME="calc-server">/is', "http://" . preg_replace('/^www\./', '', $_SERVER['SERVER_NAME']) . "/api/calc-delivery.php", $config);

    $zip->addFromString('config.php', $config);
    $zip->addFromString('config-local.php', $config_local);

    addFolderToZip("code/scripts/$code_type/", $zip);
    addFolderToZip('code/vlib/', $zip, 'vlib/');
    addFolderToZip('code/pages/', $zip, 'pages/');
    addFolderToZip("code/themes/$design/", $zip, 'theme/');
    addFolderToZip('code/products/', $zip, 'products/');

    $zip->addEmptyDir('custom');
    $zip->addEmptyDir('custom/cat/');
    $zip->addEmptyDir('custom/pages/');
    $zip->addEmptyDir('custom/products/html/');

    $zip->close();

    $size = filesize(sys_get_temp_dir() . '/' . $filename);
    Header("Content-type: application/octet-stream");
    header('Content-Length: ' . $size, TRUE);
    header('Content-disposition: attachment; filename=' . $filename, TRUE);
    if ($google_drive) {
        Header("Access-Control-Allow-Origin: *");
        Header("Access-Control-Allow-Headers: Range");
        Header("Access-Control-Expose-Headers: Cache-Control, Content-Encoding, Content-Range");
    }

    $chunksize = 1 * (1024 * 1024); // how many bytes per chunk
    if ($size > $chunksize) {
        $handle = fopen(sys_get_temp_dir() . '/' . $filename, 'rb');
        while (!feof($handle)) {
            $buffer = fread($handle, $chunksize);
            echo $buffer;
            ob_flush();
            flush();
        }
        fclose($handle);
    } else {
        readfile(sys_get_temp_dir() . '/' . $filename);
    }
    unlink(sys_get_temp_dir() . '/' . $filename);
}

function addFolderToZip($dir, $zipArchive, $zipdir = '')
{
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            if (!empty($zipdir)) $zipArchive->addEmptyDir($zipdir);
            while (($file = readdir($dh)) !== false) {
                if (!is_file($dir . $file)) {
                    if (($file !== ".") && ($file !== "..")) {
                        addFolderToZip($dir . $file . "/", $zipArchive, $zipdir . $file . "/");
                    }
                } else {
                    $zipArchive->addFile($dir . $file, $zipdir . $file);
                }
            }
        }
    }
}

?>
