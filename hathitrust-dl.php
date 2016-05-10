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

if (empty($argv[1])) {
  exit("ERROR: HathiTrust ID is required, ex. php hathitrust-dl.php inu.32000000696932 \"Metropolitan v38n01 (1913-05)\"\n");
}

if (empty($argv[2])) {
  exit("ERROR: Desired final name is required, ex. php hathitrust-dl.php inu.32000000696932 \"Metropolitan v38n01 (1913-05)\"\n");
}

// Command line arguments.
$hathitrust_id  = $argv[1];
$desired_name   = $argv[2];

// Create directories for storing files.
foreach (array('images', 'cbrs') as $directory) {
  if (!file_exists("./$directory")) {
    if (!mkdir("./$directory/$hathitrust_id", 0777, TRUE)) {
      exit("ERROR: Unable to create required ./$directory/$hathitrust_id directory.\n");
    }
  }
}

// Config for all cURL requests.
$curl_default_options = array(
  CURLOPT_AUTOREFERER     => TRUE,
  CURLOPT_FOLLOWLOCATION  => TRUE,
  CURLOPT_RETURNTRANSFER  => TRUE,
  CURLOPT_USERAGENT       => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.0.2661.94 Safari/537.36',
);

// Load the HathiTrust metadata for the passed ID. The most important bit of
// this data is "total_items", which tells us how many pages (images) are in
// this document. This is useful because HathiTrust appears to return HTTP
// status code 503 for missing images AND server errors, so just incrementing
// a page number until we get 404s doesn't work all too well.
$ch = curl_init("https://babel.hathitrust.org/cgi/imgsrv/meta?id=$hathitrust_id");
curl_setopt_array($ch, $curl_default_options);
$hathitrust_id_meta_data = json_decode(curl_exec($ch));
$total_pages = $hathitrust_id_meta_data->total_items;
curl_close($ch);

print "There are $total_pages pages for HathuTrust ID $hathitrust_id.\n";

// Make an image request for each of the total number of pages.
for ($current_page = 1; $current_page <= $total_pages; $current_page++) {

  // Save filenames padded as 0001.jpg, 0009.jpg, 0032.jpg, etc., so
  // that when we rar them up later, they sort properly in CBR readers.
  $page_number = str_pad($current_page, 4, 0, STR_PAD_LEFT);
  $page_filepath = "./images/$hathitrust_id/$page_number.jpg";

  if (file_exists($page_filepath)) {
    print "[$current_page/$total_pages] Skipping already downloaded $page_filepath.\n";
    continue;
  }

  // We save images to a temporary filename first and only move them to .jpg
  // when we know HathiTrust has responded happily (HTTP status code 200).
  // This ensures that, for long-running downloads, if the script is canceled
  // before it's finished, we don't have a half-downloaded .jpg file that is
  // then skipped over (above) the next time this document is attempted.
  $fh = fopen($page_filepath . ".download", "a");

  if (!$fh) {
    exit("ERROR: Unable to create required $page_filepath file.\n");
  }

  // By default, the "width" argument is the current size of your browser
  // window when you request a HathiTrust document. If you pass in a very
  // large size, like 10000 pixels, you'll always receive the largest scan
  // available (unless, of course, it's greater than 10000 pixels wide).
  $ch = curl_init("https://babel.hathitrust.org/cgi/imgsrv/image?id=$hathitrust_id;seq=$current_page;width=10000");
  curl_setopt_array($ch, $curl_default_options);
  curl_setopt($ch, CURLOPT_FILE, $fh);
  curl_exec($ch);

  if (!curl_errno($ch)) {
    switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) {
    case 200:
      rename($page_filepath . ".download", $page_filepath);
      print "[$current_page/$total_pages] Downloaded image to $page_filepath.\n";
      break;

    default:
      print "[$current_page/$total_pages] Unexpected HTTP code ($http_code). Retrying.\n";
      unlink($page_filepath . ".download");
      $current_page--;
      break;
    }
  }
  else {
    exit('ERROR: Exiting due to cURL problem: ' . curl_error($ch) . "\n");
  }

  curl_close($ch);
  fclose($fh);

  // Good scraper.
  sleep(rand(0, 6));
}

system('rar a ' . escapeshellarg("./cbrs/$hathitrust_id/$desired_name.cbr") . ' ' . escapeshellarg("./images/$hathitrust_id/*.jpg"));
