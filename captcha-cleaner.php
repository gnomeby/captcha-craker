<?php

const FILLCOLOR = 0xFFFFFF;

if(!empty($_SERVER['QUERY_STRING']))
  $filename = $_SERVER['QUERY_STRING'];
else
  $filename = $_SERVER['argv'][1];

$im = imagecreatefrompng($filename);
if(!$im)
  exit('No image');

$width = imagesx($im);
$height = imagesy($im);

$matrix = array_fill(0, $height, 0);

// Fill Matrix
for($y = 0; $y < $height; $y++)
{
  $matrix[$y] = array_fill(0, $width, 0);
  for($x = 0; $x < $width; $x++)
  {
    $rgb = imagecolorat($im, $x, $y);
    $r = ($rgb >> 16) & 0xFF;
    $g = ($rgb >> 8) & 0xFF;
    $b = $rgb & 0xFF;
    
    $matrix[$y][$x] = $rgb;
  }
}

// Detect waves
$wavetop = detect_wave_height($matrix, 'top', 4, 10);
$wavebottom = detect_wave_height($matrix, 'bottom', 4, 10);



// Remove long linear groups
$matrix1 = remove_lines($matrix, 'x', 6, 16, 8, 40);
$matrix2 = remove_lines($matrix, 'y', 6, 24, 8, 40);
// Remove non-grayed colors
$matrix3 = remove_color($matrix, 10, 4);

// Recreate matrix
for($y = 0; $y < $height; $y++)
{
  for($x = 0; $x < $width; $x++)
  {
    if($matrix1[$y][$x] == FILLCOLOR || $matrix2[$y][$x] == FILLCOLOR || $matrix3[$y][$x] == FILLCOLOR)
      $matrix[$y][$x] = FILLCOLOR;
  }
}

// Remove borders
for($y = 0; $y < $height; $y++)
{
  for($x = 0; $x < $width; $x++)
  {
    if($y < 6 || $y > $height - 6)
      $matrix[$y][$x] = FILLCOLOR;
    if($x < 20 || $x > $width - 24)
      $matrix[$y][$x] = FILLCOLOR;
  }
}

// Remove noise
const MAX_NOISE_SIZE = 4;
for($y = 0; $y < $height; $y++)
  for($x = 0; $x < $width; $x++)
  {
    if($matrix[$y][$x] != FILLCOLOR)
    {
      // Optimization
      for($i = 1; $i + $x < $width; $i++)
	if($matrix[$y][$i] == FILLCOLOR)
	  break;
      if($i > MAX_NOISE_SIZE)
      {
	$x += $i - 1;
	continue;
      }

      $noise_size = detect_group_size($matrix, $x, $y, 4);
      if($noise_size <= MAX_NOISE_SIZE)
		$matrix[$y][$x] = FILLCOLOR;
    }
  }


if($wavetop && !$wavebottom)
  $matrix = apply_negative_wave($matrix, $wavetop);
elseif($wavebottom && !$wavetop)
  $matrix = apply_negative_wave($matrix, $wavebottom);
else
{
  $matrixtop = apply_negative_wave($matrix, $wavetop);
  $matrixbottom = apply_negative_wave($matrix, $wavebottom);
  if(detect_height($matrixtop, 10) < detect_height($matrixbottom, 10))
    $matrix = $matrixtop;
  else
    $matrix = $matrixbottom;
}

// Set pixels
$new = imagecreatetruecolor($width, $height);
for($y = 0; $y < $height; $y++)
  for($x = 0; $x < $width; $x++)
  {
    imagesetpixel($new, $x, $y, $matrix[$y][$x]);
  }
//ksort($colorstat);var_dump($colorstat);exit;
imagedestroy($im);

if(!empty($_SERVER['QUERY_STRING']))
  header('Content-Type: image/png');
imagefilter($new, IMG_FILTER_SELECTIVE_BLUR);
if(!empty($_SERVER['QUERY_STRING']))
  imagepng($new);
else
  imagepng($new, 'captcha.png');
imagedestroy($new);

function detect_group_size($matrix, $x, $y, $allowed_level = 10, $used_pixels = array())
{
  if(!$allowed_level) 
    return 0;
  
  $used_pixels[] = "{$x}x{$y}";

  $height = count($matrix);
  $width = count($matrix[0]);

  $size = 1;
  if($x+1 < $width && $matrix[$y][$x+1] != 0xFFFFFF && !in_array(($x+1).'x'.($y), $used_pixels))
    $size += detect_group_size($matrix, $x+1, $y, $allowed_level-1, $used_pixels);
  if($y+1 < $height && $matrix[$y+1][$x] != 0xFFFFFF && !in_array(($x).'x'.($y+1), $used_pixels))
    $size += detect_group_size($matrix, $x, $y+1, $allowed_level-1, $used_pixels);
  if($x-1 >= 0 && $matrix[$y][$x-1] != 0xFFFFFF && !in_array(($x-1).'x'.($y), $used_pixels))
    $size += detect_group_size($matrix, $x-1, $y, $allowed_level-1, $used_pixels);
  if($y-1 >= 0 && $matrix[$y-1][$x] != 0xFFFFFF && !in_array(($x).'x'.($y-1), $used_pixels))
    $size += detect_group_size($matrix, $x, $y-1, $allowed_level-1, $used_pixels);

  return $size;
}

function remove_lines($matrix, $mode='x', $gray_distance=5, $min_length = 24, $min_nongray_length = 24, $saturation_limit = 10)
{
  $height = count($matrix);
  $width = count($matrix[0]);

  for($yx = 0; $yx < ($mode == 'x' ? $height : 1); $yx++)
  {
    for($x = 0; $x < $width; $x++)
    {
      for($yy = 0; $yy < ($mode == 'y' ? $height : 1); $yy++)
      {
	$y = $mode == 'x' ? $yx : $yy;
	$is_last = $mode == 'x' ? $x+1 == $width : $y+1 == $height;

	$rgb = $matrix[$y][$x];
	$r = ($rgb >> 16) & 0xFF;
	$g = ($rgb >> 8) & 0xFF;
	$b = $rgb & 0xFF;
	
	$gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);
	$saturation = (int)(max($r, $g, $b) ? 255 * (1 - min($r, $g, $b) / max($r, $g, $b)) : 0);

	if(!($mode == 'x' ? $x : $y))
	{
	  $length = 1;
	  $saturations = array();
	}
	elseif(abs($gray - $prev_color) < $gray_distance && !$is_last)
	{
	  $length++;
	  $saturations[] = $saturation;
	}
	else
	{
	  if(empty($saturations))
	    $avg_saturation = $saturation;
	  else
	    $avg_saturation = array_sum($saturations) / count($saturations);

	  if($length > $min_length || ($length > $min_nongray_length && $avg_saturation > $saturation_limit))
	  {
	    if($mode == 'x')
	      for($i = $x-$length; $i < $x; $i++)
		$matrix[$y][$i] = FILLCOLOR;

	    if($mode == 'y')
	      for($i = $y-$length; $i < $y; $i++)
		$matrix[$i][$x] = FILLCOLOR;
	  }

	  $length = 1;
	  $saturations = array();
	}
	$prev_color = $gray;

      }
    }
  }

  return $matrix;
}

function remove_color($matrix, $saturation_limit = 10, $neibor_saturation_limit = 5)
{
  $height = count($matrix);
  $width = count($matrix[0]);

  for($y = 0; $y < $height; $y++)
  {
    for($x = 0; $x < $width; $x++)
    {
      $rgb = $matrix[$y][$x];
      $r = ($rgb >> 16) & 0xFF;
      $g = ($rgb >> 8) & 0xFF;
      $b = $rgb & 0xFF;

      $saturation = (int)(max($r, $g, $b) ? 255 * (1 - min($r, $g, $b) / max($r, $g, $b)) : 0);

      if($saturation > $saturation_limit)
      {
	if($x > 0)
	{
	  $rgb = $matrix[$y][$x-1];
	  $r = ($rgb >> 16) & 0xFF;
	  $g = ($rgb >> 8) & 0xFF;
	  $b = $rgb & 0xFF;
	  $saturation = (int)(max($r, $g, $b) ? 255 * (1 - min($r, $g, $b) / max($r, $g, $b)) : 0);
	  if($saturation <= $neibor_saturation_limit)
	    continue;
	}

	if($x+1 < $width)
	{
	  $rgb = $matrix[$y][$x+1];
	  $r = ($rgb >> 16) & 0xFF;
	  $g = ($rgb >> 8) & 0xFF;
	  $b = $rgb & 0xFF;
	  $saturation = (int)(max($r, $g, $b) ? 255 * (1 - min($r, $g, $b) / max($r, $g, $b)) : 0);
	  if($saturation <= $neibor_saturation_limit)
	    continue;
	}

	if($y > 0)
	{
	  $rgb = $matrix[$y-1][$x];
	  $r = ($rgb >> 16) & 0xFF;
	  $g = ($rgb >> 8) & 0xFF;
	  $b = $rgb & 0xFF;
	  $saturation = (int)(max($r, $g, $b) ? 255 * (1 - min($r, $g, $b) / max($r, $g, $b)) : 0);
	  if($saturation <= $neibor_saturation_limit)
	    continue;
	}

	if($y+1 < $height)
	{
	  $rgb = $matrix[$y+1][$x];
	  $r = ($rgb >> 16) & 0xFF;
	  $g = ($rgb >> 8) & 0xFF;
	  $b = $rgb & 0xFF;
	  $saturation = (int)(max($r, $g, $b) ? 255 * (1 - min($r, $g, $b) / max($r, $g, $b)) : 0);
	  if($saturation <= $neibor_saturation_limit)
	    continue;
	}

	$matrix[$y][$x] = FILLCOLOR;
      }
    }
  }

    return $matrix;
}

function detect_wave_height($matrix, $direction = 'top', $gray_distance = 5, $min_wave_length = 10)
{
  $height = count($matrix);
  $width = count($matrix[0]);

  $allregions = array();

  for($y = 0; $y < $height; $y++)
  {
    for($x = 0; $x < $width; $x++)
    {
      $is_last = $x+1 == $width;

      $rgb = $matrix[$y][$x];
      $r = ($rgb >> 16) & 0xFF;
      $g = ($rgb >> 8) & 0xFF;
      $b = $rgb & 0xFF;
      
      $gray = (int)(0.299 * $r + 0.587 * $g + 0.114 * $b);

      if(!$x)
      {
	$length = 1;
	$regions = array();
	$grays = array();
      }
      elseif(abs($gray - $prev_color) < $gray_distance && !$is_last)
      {
	$length++;
	$grays[] = $gray;
      }
      else
      {
	if(empty($grays))
	  $grays[] = $gray;

	$region = array(
	  'min' => $x - $length,
	  'max' => $x - ($is_last==false),
	  'mingray' => min($grays),
	  'maxgray' => max($grays),
	  'height' => 1,
	  );
	$region['minbase'] = $region['min'];
	$region['maxbase'] = $region['max'];
	$region['center'] = $region['min'] + ($region['max'] - $region['min']) / 2;
	$regions[] = $region;

	$length = 1;
	$grays = array();
      }
      $prev_color = $gray;

      if($is_last)
	$allregions[] = $regions;

    }
  }

  $possible_waves = array();
  foreach($allregions[$direction == 'top' ? 0 : $height - 1] as $region)
    if(($region['max'] - $region['min'] + 1) >= $min_wave_length)
    {	
      $region['centers'] = 1;
      $possible_waves[] = $region;
    }

  for($y = ($direction == 'top' ? 1 : $height - 2); ($direction == 'top' ? $y < count($allregions) : $y >= 0); ($direction == 'top' ? $y++ : $y--))
  {
    foreach($allregions[$y] as $region)
    {
      foreach($possible_waves as &$wave)
      {
	if($region['min'] >= $wave['min'] && $region['max'] <= $wave['max'])
	{
	  if(abs($region['mingray'] - $wave['mingray']) < $gray_distance && abs($region['maxgray'] - $wave['maxgray']) < $gray_distance)
	  {
	    $wave['height']++;
	    $wave['min'] = $region['min'];
	    $wave['max'] = $region['max'];
	    $wave['mingray'] = $region['mingray'];
	    $wave['maxgray'] = $region['maxgray'];
	    
	    $wave['center'] += $region['center'];
	    $wave['centers']++;

	    if($region['max'] != $wave['max'] && $region['min'] != $wave['min'])
	    {
	      $wave['center'] += $region['center'];
	      $wave['centers']++;
	    }
	    
	  }
	}
      }
    }
  }

  $centerwave = NULL;
  $centercanvas = $width / 2;
  foreach($possible_waves as $key => $wave)
  {
    if($wave['height'] <= 2)
    {
      unset($possible_waves[$key]);
      continue;
    }

    if($wave['minbase'] < $centercanvas && $wave['maxbase'] > $centercanvas)
      $centerwave = array(
	'height' => $wave['height'], 
	'length' => (int)(($wave['center'] / $wave['centers']) * 2), 
	'phasex' => $direction == 'top' ? 0 : pi(), 
	'phasey' => $direction == 'top' ? 10 : -10);
  }

  return $centerwave;
}

function apply_negative_wave($matrix, $wave)
{
    $height = count($matrix);
    $width = count($matrix[0]);

    $newmatrix = $matrix;

    $rand2 = 0.00019 * $wave['length'];
    $rand4 = 0;
    // фазы
    $rand7 = $wave['phasex'];
    $rand8 = $wave['phasey'];
    // амплитуды
    $rand10 = $wave['height'] * 3;

    for($y = 0; $y < $height; $y++)
    {
      for($x = 0; $x < $width; $x++)
      {
	$sx = $x;
	//  $sx = $x + ( sin($x * $rand1 + $rand5) + sin($y * $rand3 + $rand6) ) * $rand9;
	$sy = $y + ( sin($x * $rand2 + $rand7) + sin($y * $rand4 + $rand8) ) * $rand10;

	$frsx = $sx - floor($sx);
	$frsy = $sy - floor($sy);
	$frsx1 = 1 - $frsx;
	$frsy1 = 1 - $frsy;

	$rgb = $matrix[mtb($sy,$height)][mtb($sx,$width)];
	$r = ($rgb >> 16) & 0xFF;
	$g = ($rgb >> 8) & 0xFF;
	$b = $rgb & 0xFF;
	$rgb_x = $matrix[mtb($sy,$height)][mtb($sx,$width)];
	$r_x = ($rgb_x >> 16) & 0xFF;
	$g_x = ($rgb_x >> 8) & 0xFF;
	$b_x = $rgb_x & 0xFF;
	$rgb_y = $matrix[mtb($sy,$height)][mtb($sx,$width)];
	$r_y = ($rgb_y >> 16) & 0xFF;
	$g_y = ($rgb_y >> 8) & 0xFF;
	$b_y = $rgb_y & 0xFF;
	$rgb_xy = $matrix[mtb($sy,$height)][mtb($sx,$width)];
	$r_xy = ($rgb_xy >> 16) & 0xFF;
	$g_xy = ($rgb_xy >> 8) & 0xFF;
	$b_xy = $rgb_xy & 0xFF;

	$newcolor_r = floor($r    * $frsx1 * $frsy1 +
			    $r_x  * $frsx  * $frsy1 +
			    $r_y  * $frsx1 * $frsy  +
			    $r_xy * $frsx  * $frsy );
	$newcolor_g = floor($g    * $frsx1 * $frsy1 +
			    $g_x  * $frsx  * $frsy1 +
			    $g_y  * $frsx1 * $frsy  +
			    $g_xy * $frsx  * $frsy );
	$newcolor_b = floor($b    * $frsx1 * $frsy1 +
			    $b_x  * $frsx  * $frsy1 +
			    $b_y  * $frsx1 * $frsy  +
			    $b_xy * $frsx  * $frsy );

	$newmatrix[$y][$x] = $newcolor_r*256*256+$newcolor_g*256+$newcolor_b;
//	$newmatrix[$y][$x] = $rgb;
      }
    }

    return $newmatrix;
}

function mtb($i, $max)
{
  if($i < 0)
    return 0;
  elseif($i > $max - 1)
    return $max - 1;
  else
    return $i;
}

function detect_height($matrix, $min_ppl = 10)
{
  $height = count($matrix);
  $width = count($matrix[0]);

  $ppl = array_fill(0, $height, 0);
  $usefullheight = 0;
  for($y = 0; $y < $height; $y++)
  {
    for($x = 0; $x < $width; $x++)
    {
      if($matrix[$y][$x] != FILLCOLOR)
	$ppl[$y]++;
    }
    if($ppl[$y] > $min_ppl)
      $usefullheight++;
  }

  return $usefullheight;
}