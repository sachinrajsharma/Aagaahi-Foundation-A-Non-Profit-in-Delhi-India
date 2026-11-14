<?php
/**
 * includes/images.php — safe image uploads for the dashboard.
 *
 * Every upload is checked (real file type, size, pixel count) and then RE-ENCODED with GD, so what is stored is always
 * a clean image: any hidden script inside the file is gone, and camera metadata such as GPS location is stripped.
 * Output: a web-sized image (max 1400 px) and a 480 px thumbnail, as WebP (or JPEG if this server's GD has no WebP).
 */

const UPLOAD_MAX_BYTES  = 12 * 1024 * 1024;   // 12 MB per file
const UPLOAD_MAX_PIXELS = 50000000;           // 50 megapixels
const UPLOAD_ALLOWED    = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

function uploads_dir(): string { return dirname(__DIR__) . '/uploads'; }

function image_supported(): bool { return function_exists('imagecreatefromstring') && function_exists('imagejpeg'); }

/**
 * @param array $file  one entry of $_FILES (name, tmp_name, error, size)
 * @param bool  $trusted  tests only: skip the is_uploaded_file() check
 * @return array{path:string, thumb_path:string, width:int, height:int, mime:string, size:int}
 * @throws RuntimeException with a message that is safe to show to staff
 */
function process_upload(array $file, bool $trusted = false): array
{
    if (!image_supported()) throw new RuntimeException('This server cannot process images (the GD extension is missing).');
    $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('That file is too large for this server. Please use a smaller photo.');
    if ($err !== UPLOAD_ERR_OK) throw new RuntimeException('The file did not upload. Please try again.');
    $tmp = (string)$file['tmp_name'];
    if (!$trusted && !is_uploaded_file($tmp)) throw new RuntimeException('Invalid upload.');
    if (filesize($tmp) > UPLOAD_MAX_BYTES) throw new RuntimeException('That photo is larger than ' . (UPLOAD_MAX_BYTES / 1048576) . ' MB. Please resize it and try again.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    if (!isset(UPLOAD_ALLOWED[$mime])) throw new RuntimeException('Only JPG, PNG, WebP and GIF images are allowed.');
    $info = @getimagesize($tmp);
    if (!$info || $info[0] < 1 || $info[1] < 1) throw new RuntimeException('That file does not look like a valid image.');
    if ($info[0] * $info[1] > UPLOAD_MAX_PIXELS) throw new RuntimeException('That image has too many pixels. Please use a smaller photo.');

    $src = @imagecreatefromstring((string)file_get_contents($tmp));
    if (!$src) throw new RuntimeException('That image could not be read. Try saving it again as a JPG or PNG.');
    $src = image_fix_orientation($src, $tmp, $mime);

    $webp = function_exists('imagewebp');
    $ext = $webp ? 'webp' : 'jpg';
    $sub = date('Y') . '/' . date('m');
    $dir = uploads_dir() . '/' . $sub;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) throw new RuntimeException('The uploads folder is not writable. Check its permissions on the server.');
    $base = bin2hex(random_bytes(8));
    $full = image_resized($src, 1400);
    $thumb = image_resized($src, 480);
    $okFull = image_save($full, $dir . "/$base.$ext", $webp, 72);
    $okThumb = image_save($thumb, $dir . "/$base-t.$ext", $webp, 68);
    $w = imagesx($full); $h = imagesy($full);
    imagedestroy($src);
    if ($thumb !== $full) imagedestroy($thumb);
    imagedestroy($full);
    if (!$okFull || !$okThumb) { @unlink($dir . "/$base.$ext"); @unlink($dir . "/$base-t.$ext"); throw new RuntimeException('Could not save the image on the server.'); }

    return [
        'path' => "/uploads/$sub/$base.$ext", 'thumb_path' => "/uploads/$sub/$base-t.$ext", 'width' => $w, 'height' => $h,
        'mime' => $webp ? 'image/webp' : 'image/jpeg', 'size' => (int)filesize($dir . "/$base.$ext"),
    ];
}

function image_fix_orientation($img, string $file, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) return $img;
    $exif = @exif_read_data($file);
    $o = (int)($exif['Orientation'] ?? 1);
    $rot = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
    if ($rot) { $r = imagerotate($img, $rot, 0); if ($r) { imagedestroy($img); return $r; } }
    return $img;
}

function image_resized($src, int $max)
{
    $w = imagesx($src); $h = imagesy($src);
    if ($w <= $max && $h <= $max) return $src;
    $scale = $max / max($w, $h);
    $nw = max(1, (int)round($w * $scale)); $nh = max(1, (int)round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false); imagesavealpha($dst, true);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, imagecolorallocatealpha($dst, 255, 255, 255, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

function image_save($img, string $file, bool $webp, int $quality): bool
{
    if (!imageistruecolor($img)) imagepalettetotruecolor($img);
    if ($webp) { imagealphablending($img, false); imagesavealpha($img, true); return imagewebp($img, $file, $quality); }
    // JPEG has no transparency: flatten onto white
    $w = imagesx($img); $h = imagesy($img);
    $flat = imagecreatetruecolor($w, $h);
    imagefill($flat, 0, 0, imagecolorallocate($flat, 255, 255, 255));
    imagecopy($flat, $img, 0, 0, 0, 0, $w, $h);
    $ok = imagejpeg($flat, $file, $quality + 8);
    imagedestroy($flat);
    return $ok;
}

/** Deletes stored image files, but only inside the uploads folder. */
function delete_upload_files(string ...$paths): void
{
    $root = realpath(uploads_dir());
    if ($root === false) return;
    foreach ($paths as $p) {
        if ($p === '' || !str_starts_with($p, '/uploads/')) continue;      // never touch assets/ or anything else
        $f = realpath(dirname(__DIR__) . $p);
        if ($f !== false && str_starts_with($f, $root . DIRECTORY_SEPARATOR)) @unlink($f);
    }
}
