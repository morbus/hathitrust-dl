<?php

/**
 * @file
 * hathitrust-dl.php
 *
 * Automated downloading and packaing of HathiTrust resources.
 * Copyright (C) 2016 Morbus Iff <morbus@disobey.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

set_time_limit(0);

if (!file_exists('./images')) {
  if (!mkdir('./images')) {
    exit("ERROR: Unable to create required ./images directory.\n");
  }
}

if (!file_exists('./cbrs')) {
  if (!mkdir('./cbrs')) {
    exit("ERROR: Unable to create required ./cbrs directory.\n");
  }
}

if (empty($argv[1])) {
  exit("ERROR: HashiTrust ID is required, ex. php hathitrust-dl.php inu.32000000696932 \"Metropolitan v38n01 (1913-05)\"\n");
}

$hathitrust_id = $argv[1];

if (!file_exists("./images/$hathitrust_id")) {
  if (!mkdir("./images/$hathitrust_id")) {
    exit("ERROR: Unable to create required ./images/$hathitrust_id directory.\n");
  }
}

if (!file_exists("./cbrs/$hathitrust_id")) {
  if (!mkdir("./cbrs/$hathitrust_id")) {
    exit("ERROR: Unable to create required ./cbrs/$hathitrust_id directory.\n");
  }
}

if (empty($argv[2])) {
  exit("ERROR: Desired final name is required, ex. php hathitrust-dl.php inu.32000000696932 \"Metropolitan v38n01 (1913-05)\"\n");
}

$desired_name = $argv[2];

$page = 1;
$batch_not_finished = TRUE;
while ($batch_not_finished) {
  $page_number = str_pad($page, 4, 0, STR_PAD_LEFT);
  $page_filepath = "./images/$hathitrust_id/$desired_name $page_number.jpg";
  $fh = fopen($page_filepath, "a");

  if (!$fh) {
    exit("ERROR: Unable to create required $page_filepath file.\n");
  }

  $ch = curl_init();
  curl_setopt_array($ch, array(
    CURLOPT_AUTOREFERER     => TRUE,
    CURLOPT_FOLLOWLOCATION  => TRUE,
    CURLOPT_RETURNTRANSFER  => TRUE,
    CURLOPT_FILE            => $fh,
    CURLOPT_URL             => "https://babel.hathitrust.org/cgi/imgsrv/image?id=$hathitrust_id;seq=$page;width=10000",
    CURLOPT_USERAGENT       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36',
  ));

  if (file_exists($page_filepath)) {
    $from = filesize($page_filepath);
    curl_setopt($ch, CURLOPT_RANGE, $from . "-");
  }

  print "Downloading $page_filepath.\n";
  curl_exec($ch);

  if (!curl_errno($ch)) {
    switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
    case 200:
      break;
    default:
      unlink($page_filepath);
      $batch_not_finished = FALSE;
      exit('Unexpected HTTP code: ' . $http_code . "\n");
    }
  }

// @todo Write some error handling.

  curl_close($ch);
  fclose($fh);
  $page++;
}

system('rar a ' . escapeshellarg("./cbrs/$hathitrust_id/$desired_name.cbr") . ' ' . escapeshellarg("./images/$hathitrust_id/*.jpg"));
