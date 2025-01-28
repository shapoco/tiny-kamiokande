<?php

require_once __DIR__."/../../blobhive/blobhive.php";

const GENERATE_DIM = false;
const DIM_SAMPLE_IMAGE = 'dim_sample.png';

const IMAGE_URL = 'https://www-sk.icrr.u-tokyo.ac.jp/realtimemonitor/skev.gif';
const TMP_IMAGE_FILE = 'tmp.1f293fda208c7d84.gif';

const TOP_X0 = 282;
const TOP_X1 = 536;
const TOP_Y0 = 0;
const TOP_Y1 = 174;

const SIDE_X0 = 19;
const SIDE_X1 = 802;
const SIDE_Y0 = TOP_Y1;
const SIDE_Y1 = 420;

const BOTTOM_X0 = TOP_X0;
const BOTTOM_X1 = TOP_X1;
const BOTTOM_Y0 = SIDE_Y1;
const BOTTOM_Y1 = 650;

const TYP_CONTROL = 0x00;
const TYP_NUMERIC = 0x80;
const TYP_DATA = 0xc0;
const TYP_DAT_TYP_SHIFT = 4;
const TYP_DAT_TYP_MASK = 0x3;
const TYP_DAT_UINT = 0x0;
const TYP_DAT_INT = 0x1;
const TYP_DAT_FLOAT = 0x2;
const TYP_DAT_BOOL = 0x3;
const TYP_VL_STR = 0x4;
const TYP_DAT_8B = 0 << TYP_DAT_TYP_SHIFT;
const TYP_DAT_16B = 1 << TYP_DAT_TYP_SHIFT;
const TYP_DAT_32B = 2 << TYP_DAT_TYP_SHIFT;
const TYP_DAT_64B = 3 << TYP_DAT_TYP_SHIFT;
const TYP_CTL_FLG_MARKER = TYP_CONTROL | 0x20;
const TYP_CTL_MK_DOC_START = TYP_CTL_FLG_MARKER | 0x0;
const TYP_CTL_MK_DOC_END = TYP_CTL_FLG_MARKER | 0x1;
const TYP_CTL_MK_OBJ_START = TYP_CTL_FLG_MARKER | 0x2;
const TYP_CTL_MK_OBJ_END = TYP_CTL_FLG_MARKER | 0x3;
const TYP_CTL_MK_ARR_START = TYP_CTL_FLG_MARKER | 0x4;
const TYP_CTL_MK_ARR_END = TYP_CTL_FLG_MARKER | 0x5;

const MNE_VERSION = 0x0011;
const MNE_STATUS_CODE = 0x0021;
const MNE_TOP_WIDTH = 0x0201;
const MNE_TOP_HEIGHT = 0x0202;
const MNE_TOP_PIXELS = 0x0203;
const MNE_SIDE_WIDTH = 0x0401;
const MNE_SIDE_HEIGHT = 0x0402;
const MNE_SIDE_PIXELS = 0x0403;
const MNE_BOTTOM_WIDTH = 0x0601;
const MNE_BOTTOM_HEIGHT = 0x0602;
const MNE_BOTTOM_PIXELS = 0x0603;

const STS_SUCCESS = 0x00;
const STS_DIMENSION_BROKEN = 0x01;
const STS_IMAGE_DOWNLOAD_FAILED = 0x02;
const STS_UNKNOWN_ERROR = 0x80;

const VERSION_CODE = 0x0100;

// ピクセルの座標を検出する
//
// 各ピクセルは下のような形をしている。縦または横に4つ色が連続している座標を中心座標と判断する
//
//        X
//        ↓
//        ■
//      ■■■
// Y→■■■■
//      ■■■
//
function findPixels($image, int $i0, int $i1, int $j0, int $j1, bool $rows) {
  $iList = [];
  for ($i = $i0; $i < $i1; $i++) {
    $n = 0;
    $numPixs = 0;
    $okPixs = 0;
    for ($j = $j0; $j < $j1; $j++) {
      // 色取得
      $x = $rows ? $j : $i;
      $y = $rows ? $i : $j;
      $rgb = imagecolorat($image, $x, $y) & 0xffffff;
      
      if ($rgb != 0xffffff && $rgb != 0x0) {
        // 白でも黒でもない色の連続を数える
        $n++;
      }
      else if ($n != 0) {
        if ($n % 4 == 0) {
          // 各ピクセルは 4x4px であり、隣とくっついている場合もある
          // --> n = 4の倍数 であれば
          $okPixs++;
        }
        $numPixs++;
        $n = 0;
      }
    }
    if ($numPixs >= 2 && $okPixs >= $numPixs * 9 / 10) {
      array_push($iList, $i);
    }
  }
  return $iList;
}

function readPixels($image, $xCoords, $yCoords) {
  $pixels = [];
  $whiteIndex = -1;
  foreach ($xCoords as $x) {
    foreach ($yCoords as $y) {
      $r8 = 0; $g8 = 0; $b8 = 0;
      $index = imagecolorat($image, $x, $y);
      if ($index != $whiteIndex) {
        $cols = imagecolorsforindex($image, $index);
        $r8 = $cols['red'];
        $g8 = $cols['green'];
        $b8 = $cols['blue'];
        if ($r8 == 255 && $r8 == 255 && $r8 == 255) {
          $whiteIndex = $index;
          $r8 = 0; $g8 = 0; $b8 = 0;
        }
      }
      $r5 = $r8 * 31 / 255;
      $g6 = $g8 * 63 / 255;
      $b5 = $b8 * 31 / 255;
      $rgb565 = ($r5 << 11) | ($g6 << 5) | $b5;
      array_push($pixels, $rgb565);
    }
  }
  return $pixels;
}

/*
function pushHeader(&$array, $mnemonic, $size, $type) {
  array_push($array, $mnemonic & 0xff); $mnemonic >>= 8;
  array_push($array, $mnemonic & 0xff); $mnemonic >>= 8;
  array_push($array, $size & 0xff); $size >>= 8;
  array_push($array, $size & 0xff); $size >>= 8;
  array_push($array, $size & 0xff); $size >>= 8;
  array_push($array, $size & 0xff); $size >>= 8;
  array_push($array, $type);
}

function pushUInt16(&$array, $mnemonic, $value) {
  pushHeader($array, $mnemonic, 2, TYP_DAT_16B);
  $value = floor($value);
  array_push($array, $value & 0xff); $value >>= 8;
  array_push($array, $value & 0xff); $value >>= 8;
}

function pushUInt16Array(&$array, $mnemonic, $data) {
  pushHeader($array, $mnemonic, 2, TYP_DAT_16B | TYP_DAT_FLG_ARRAY);
  foreach ($data as $value) {
    array_push($array, $value & 0xff); $value >>= 8;
    array_push($array, $value & 0xff); $value >>= 8;
  }
}
*/

$topXCoords = [
  286, 291, 297, 302, 307, 312, 317, 323, 328, 333, 338, 343, 349, 354, 359, 364,
  369, 375, 380, 385, 390, 395, 401, 406, 411, 416, 421, 427, 432, 437, 442, 447,
  453, 458, 463, 468, 473, 479, 484, 489, 494, 499, 505, 510, 515, 520, 525, 531,
];
$topYCoords = [
  3, 8, 13, 18, 23, 27, 32, 37, 42, 47, 51, 56, 61, 66, 71, 76, 
  80, 85, 90, 95, 99, 104, 109, 114, 119, 124, 128, 133, 138, 143, 
  148, 152, 157, 162, 167, 172,
];

$sideXCoords = [
  23, 28, 34, 39, 44, 49, 54, 59, 65, 70, 75, 80, 86, 91, 96, 101,
  107, 112, 117, 122, 127, 133, 138, 143, 148, 153, 159, 164, 169, 174, 179, 184,
  190, 195, 200, 205, 211, 216, 221, 226, 231, 237, 242, 247, 252, 257, 263, 268,
  273, 278, 284, 289, 294, 299, 304, 310, 315, 320, 325, 330, 336, 341, 346, 351,
  356, 361, 367, 372, 377, 382, 388, 393, 398, 403, 409, 414, 419, 424, 429, 434,
  440, 445, 450, 455, 460, 466, 471, 476, 481, 486, 492, 497, 502, 507, 513, 518,
  523, 528, 534, 539, 544, 549, 554, 559, 565, 570, 575, 580, 585, 590, 596, 601,
  606, 611, 617, 622, 627, 632, 638, 643, 648, 653, 658, 664, 669, 674, 679, 684,
  690, 695, 700, 705, 710, 715, 721, 726, 731, 736, 742, 747, 752, 757, 763, 768,
  773, 778, 783, 789, 794, 799,
];
$sideYCoords = [
  177, 181, 186, 191, 196, 200, 206, 210, 215, 220, 225, 229, 234, 239, 244, 249,
  253, 258, 263, 268, 272, 278, 282, 287, 292, 297, 301, 307, 311, 316, 321, 326,
  330, 335, 340, 345, 350, 354, 359, 364, 369, 373, 379, 383, 388, 393, 398, 402,
  408, 412, 417
];

$bottomXCoords = [
  286, 291, 297, 302, 307, 312, 317, 323, 328, 333, 338, 343, 349, 354, 359, 364,
  369, 375, 380, 385, 390, 395, 401, 406, 411, 416, 421, 427, 432, 437, 442, 447,
  453, 458, 463, 468, 473, 479, 484, 489, 494, 499, 505, 510, 515, 520, 525, 531,
];
$bottomYCoords = [
  422, 427, 431, 436, 441, 446, 451, 455, 460, 465, 470, 475, 479, 484, 489, 494,
  499, 503, 508, 513, 518, 523, 527, 532, 537, 542, 547, 552, 556, 561, 566, 571,
  576, 580, 585, 590, 595, 600, 604, 609, 614, 619, 624, 628, 633, 638, 643, 648,
];

$topPixels = [];
$sidePixels = [];
$bottomPixels = [];

$statusCode = STS_UNKNOWN_ERROR;
$errorMsg = '';

try {
  if (GENERATE_DIM) {
    $image = imagecreatefrompng(DIM_SAMPLE_IMAGE);
    if (!$image) {
      $statusCode = STS_DIMENSION_BROKEN;
      throw ErrorException('Image load failed.');
    }
    $topXCoords = findPixels($image, TOP_X0, TOP_X1, TOP_Y0, TOP_Y1, false);
    $topYCoords = findPixels($image, TOP_Y0, TOP_Y1, TOP_X0, TOP_X1, true);
    $sideXCoords = findPixels($image, SIDE_X0, SIDE_X1, SIDE_Y0, SIDE_Y1, false);
    $sideYCoords = findPixels($image, SIDE_Y0, SIDE_Y1, SIDE_X0, SIDE_X1, true);
    $bottomXCoords = findPixels($image, BOTTOM_X0, BOTTOM_X1, BOTTOM_Y0, BOTTOM_Y1, false);
    $bottomYCoords = findPixels($image, BOTTOM_Y0, BOTTOM_Y1, BOTTOM_X0, BOTTOM_X1, true);
    imagedestroy($image);
  }
  
  if (!file_put_contents(TMP_IMAGE_FILE, file_get_contents(IMAGE_URL))) {
    $statusCode = STS_IMAGE_DOWNLOAD_FAILED;
    throw ErrorException('Image download failed.');
  }
  
  $image = imagecreatefromgif(TMP_IMAGE_FILE);
  if (!$image) {
    $statusCode = STS_IMAGE_DOWNLOAD_FAILED;
    throw ErrorException('Image load failed.');
  }
  $topPixels = readPixels($image, $topXCoords, $topYCoords);
  $sidePixels = readPixels($image, $sideXCoords, $sideYCoords);
  $bottomPixels = readPixels($image, $bottomXCoords, $bottomYCoords);
  imagedestroy($image);
  
  $statusCode = STS_SUCCESS;
}
catch(Exception $ex) {
  $errorMsg = (string)$ex;
}

header('Cache-Control: no-cache');

if (isset($_GET['fmt']) && $_GET['fmt'] == 'bin') {
  $doc = new blobhive\Document();
  
  $doc->objectStart();
  
  $doc->str('status'); $doc->u16($statusCode);
  $doc->str('planes'); $doc->objectStart(); {
    $doc->str('top'); $doc->objectStart(); {
      $doc->str('w'); $doc->u16(count($topXCoords));
      $doc->str('h'); $doc->u16(count($topYCoords));
    } $doc->objectEnd();
    $doc->str('side'); $doc->objectStart(); {
      $doc->str('w'); $doc->u16(count($sideXCoords));
      $doc->str('h'); $doc->u16(count($sideYCoords));
    } $doc->objectEnd();
    $doc->str('bottom'); $doc->objectStart(); {
      $doc->str('w'); $doc->u16(count($bottomXCoords));
      $doc->str('h'); $doc->u16(count($bottomYCoords));
    } $doc->objectEnd();
  } $doc->objectEnd();
  
  $doc->objectEnd();
  
  //$bytes = [];
  //
  //pushUInt16($bytes, MNE_VERSION, VERSION_CODE);
  //pushUInt16($bytes, MNE_STATUS_CODE, $statusCode);
  //
  //pushUInt16($bytes, MNE_TOP_WIDTH, count($topXCoords));
  //pushUInt16($bytes, MNE_TOP_HEIGHT, count($topYCoords));
  //pushUInt16Array($bytes, MNE_TOP_PIXELS, $topPixels);
  //
  //pushUInt16($bytes, MNE_SIDE_WIDTH, count($sideXCoords));
  //pushUInt16($bytes, MNE_SIDE_HEIGHT, count($sideYCoords));
  //pushUInt16Array($bytes, MNE_SIDE_PIXELS, $sidePixels);
  //
  //pushUInt16($bytes, MNE_BOTTOM_WIDTH, count($bottomXCoords));
  //pushUInt16($bytes, MNE_BOTTOM_HEIGHT, count($bottomYCoords));
  //pushUInt16Array($bytes, MNE_BOTTOM_PIXELS, $bottomPixels);
  //
  header('Content-Type: application/octet-stream');
  header('Content-Disposition: attachment; filename="skrm_pixels.bin"');
  echo $doc->getString();
}
else {
  $json = [
    'version' => VERSION_CODE,
    'statusCode' => $statusCode,
    'errorMsg' => $errorMsg,
  ];
  
  if (GENERATE_DIM) {
    $json['dimension'] = [
      'top' => [
        'xCoords' => $topXCoords,
        'yCoords' => $topYCoords,
      ],
      'side' => [
        'xCoords' => $sideXCoords,
        'yCoords' => $sideYCoords,
      ],
      'bottom' => [
        'xCoords' => $bottomXCoords,
        'yCoords' => $bottomYCoords,
      ],
    ];
  }
  
  $json['planes'] = [
    'top' => [
      'width' => count($topXCoords),
      'height' => count($topYCoords),
      'pixels' => $topPixels,
    ],
    'side' => [
      'width' => count($sideXCoords),
      'height' => count($sideYCoords),
      'pixels' => $sidePixels,
    ],
    'bottom' => [
      'width' => count($bottomXCoords),
      'height' => count($bottomYCoords),
      'pixels' => $bottomPixels,
    ],
  ];

  header('Content-Type: application/json');
  echo json_encode($json);
}
