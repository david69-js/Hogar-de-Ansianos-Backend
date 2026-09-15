<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Redimensiona y recomprime una imagen ANTES de guardarla.
 *
 * Por qué existe: el almacenamiento de R2 se paga por lo que ocupa, y una foto
 * recién tomada con un teléfono pesa entre 5 y 12 MB. Guardarlas tal cual
 * llenaría el bucket en pocas semanas, cuando para verlas en pantalla basta con
 * una fracción de esa resolución.
 *
 * Usa GD (viene compilado en la imagen de Docker), así que no agrega ninguna
 * dependencia de Composer. Si el formato no se puede procesar —WebP, porque
 * esta build de GD no lo soporta— o algo falla, se guarda el archivo original:
 * es preferible una foto pesada a perder la foto.
 */
class ImageOptimizer
{
    /**
     * Optimiza y guarda la imagen; devuelve la ruta dentro del disco.
     *
     * @param  int  $maxDimension  Lado mayor en píxeles (se conserva la proporción).
     * @param  int  $quality       Calidad JPEG (0-100).
     */
    public static function store(
        UploadedFile $file,
        string $directory,
        string $disk,
        int $maxDimension = 1600,
        int $quality = 82
    ): string {
        $optimized = self::optimize($file, $maxDimension, $quality);

        if ($optimized === null) {
            return $file->store($directory, $disk);
        }

        // Si comprimir no ayudó (una imagen ya pequeña y muy optimizada puede
        // crecer al reconvertirse), se guarda la original.
        if (filesize($optimized) >= $file->getSize()) {
            @unlink($optimized);
            return $file->store($directory, $disk);
        }

        $path = $directory . '/' . Str::random(40) . '.jpg';
        Storage::disk($disk)->put($path, file_get_contents($optimized));
        @unlink($optimized);

        return $path;
    }

    /**
     * Devuelve la ruta de un archivo temporal ya optimizado, o null si no se
     * pudo procesar (formato no soportado por esta build de GD, archivo dañado).
     */
    private static function optimize(UploadedFile $file, int $maxDimension, int $quality): ?string
    {
        if (!extension_loaded('gd')) {
            return null;
        }

        $realPath = $file->getRealPath();
        if (!$realPath) {
            return null;
        }

        // GD decodifica el mapa de bits completo (~4 bytes por pixel) y un fallo
        // por falta de memoria es un error FATAL que try/catch no puede atrapar:
        // sería un 500 en la cara del usuario. Por eso se mide antes y, si no
        // cabe con holgura, se guarda la original sin optimizar.
        $info = @getimagesize($realPath);
        if (!$info) {
            return null;
        }

        $estimated = (int) ($info[0] * $info[1] * 4 * 1.6);
        if ($estimated > self::availableMemoryBytes()) {
            Log::info('Imagen demasiado grande para optimizar en memoria; se guarda la original', [
                'dimensiones' => $info[0] . 'x' . $info[1],
            ]);
            return null;
        }

        try {
            $source = match ($file->getMimeType()) {
                'image/jpeg' => @imagecreatefromjpeg($realPath),
                'image/png' => @imagecreatefrompng($realPath),
                default => null,
            };

            if (!$source) {
                return null;
            }

            $source = self::applyExifOrientation($source, $realPath, $file->getMimeType());

            $width = imagesx($source);
            $height = imagesy($source);
            $longest = max($width, $height);

            if ($longest > $maxDimension) {
                $scaled = imagescale($source, (int) round($width * $maxDimension / $longest));
                if ($scaled) {
                    imagedestroy($source);
                    $source = $scaled;
                }
            }

            // JPEG no tiene transparencia: un PNG transparente saldría con
            // fondo negro, así que solo en ese caso se aplana sobre blanco.
            // Hacerlo únicamente cuando hace falta evita una copia completa del
            // mapa de bits, que es lo que más memoria consume.
            if ($file->getMimeType() === 'image/png') {
                $canvas = imagecreatetruecolor(imagesx($source), imagesy($source));
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
                imagecopy($canvas, $source, 0, 0, 0, 0, imagesx($source), imagesy($source));
                imagedestroy($source);
                $source = $canvas;
            }

            $temp = tempnam(sys_get_temp_dir(), 'img-opt-');
            $ok = imagejpeg($source, $temp, $quality);
            imagedestroy($source);

            if (!$ok) {
                @unlink($temp);
                return null;
            }

            return $temp;
        } catch (\Throwable $e) {
            Log::warning('No se pudo optimizar la imagen; se guarda la original', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /** Memoria que queda disponible, dejando un 20% de margen para el resto de la petición. */
    private static function availableMemoryBytes(): int
    {
        $limit = trim((string) ini_get('memory_limit'));

        if ($limit === '' || $limit === '-1') {
            return PHP_INT_MAX;
        }

        $unit = strtolower(substr($limit, -1));
        $bytes = (int) $limit;
        $bytes *= match ($unit) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        };

        return (int) (($bytes - memory_get_usage(true)) * 0.8);
    }

    /**
     * Las fotos de teléfono guardan la rotación en los metadatos EXIF en vez de
     * girar los píxeles. GD los ignora, así que sin esto una foto vertical se
     * vería acostada después de convertirla.
     *
     * @param  \GdImage  $image
     * @return \GdImage
     */
    private static function applyExifOrientation($image, string $path, ?string $mime)
    {
        if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? null;

        $degrees = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($degrees === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $degrees, 0);
        if (!$rotated) {
            return $image;
        }

        imagedestroy($image);
        return $rotated;
    }
}
