<?php
function testeur($source)
{
  $namefile = "OVRI_after_" . OvriVersion . "-" . date('YmdHis') . ".zip";
  $f = file_put_contents($namefile, fopen($source, 'r'), LOCK_EX);
  if ($f) {
    $zip = new ZipArchive;
    $res = $zip->open($namefile);
    if ($res === TRUE) {
      $installationDIR = getcwd() . '/wp-content/plugins/'; // Get the current path to where the file is located
      $zip->extractTo($installationDIR);
      $zip->close();
      unlink($namefile);
      echo "OVRI Updating finish";
    } else {
      echo "Error during updating";
    }
  }
  exit();
}