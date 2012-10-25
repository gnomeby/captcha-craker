<?php

$filename = $_SERVER['argv'][1];

system('php captcha-cleaner.php '.$filename);
system('pngtopnm captcha.png > captcha.pnm');
$text_gorc = shell_exec('cat captcha.pnm | gocr -');
$text_ocrad = shell_exec('cat captcha.pnm | ocrad -');
$text_gorc=rtrim($text_gorc, "\n");
$text_ocrad=rtrim($text_ocrad, "\n");
var_dump($text_gorc, $text_ocrad);
unlink('captcha.png');
unlink('captcha.pnm');

$text = "____";
$text = fill_chars($text, $text_gorc);
$text = fill_chars($text, $text_ocrad);
var_dump($text);

function fill_chars($text, $ocr)
{
  if(strlen($ocr) == 4)
    for($i=0; $i < 4; $i++)
      if($text[$i] == '_' && $ocr[$i] != '_')
	$text[$i] = $ocr[$i];

  return $text;
}