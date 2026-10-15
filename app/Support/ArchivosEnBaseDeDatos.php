<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\MimeTypeDetection\FinfoMimeTypeDetector;

/**
 * Disco de Laravel que guarda los archivos en la tabla `archivos`.
 *
 * El hosting (Wasmer) recrea la carpeta storage/ en cada despliegue y los
 * archivos subidos se perdían; la base de datos sí se conserva. Son pocos
 * archivos y chicos (QR y comprobantes de hasta 2 MB), así que alcanza.
 * El contenido se guarda en base64 para no depender del manejo de binarios de cada motor.
 */
class ArchivosEnBaseDeDatos implements FilesystemAdapter
{
    private const TABLA = 'archivos';

    private function consulta()
    {
        return DB::table(self::TABLA);
    }

    private function fila(string $ruta): ?object
    {
        return $this->consulta()->where('ruta', $ruta)->first();
    }

    private function filaOFalla(string $ruta): object
    {
        return $this->fila($ruta) ?? throw UnableToReadFile::fromLocation($ruta, 'El archivo no existe.');
    }

    public function fileExists(string $path): bool
    {
        return $this->consulta()->where('ruta', $path)->exists();
    }

    public function directoryExists(string $path): bool
    {
        return $this->consulta()->where('ruta', 'like', rtrim($path, '/').'/%')->exists();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $this->consulta()->updateOrInsert(['ruta' => $path], [
            'mime' => (new FinfoMimeTypeDetector)->detectMimeType($path, $contents) ?? 'application/octet-stream',
            'tamano' => strlen($contents),
            'contenido' => base64_encode($contents),
            'updated_at' => now(),
            'created_at' => $this->fila($path)?->created_at ?? now(),
        ]);
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $this->write($path, stream_get_contents($contents), $config);
    }

    public function read(string $path): string
    {
        return base64_decode($this->filaOFalla($path)->contenido);
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->consulta()->where('ruta', $path)->delete();
    }

    public function deleteDirectory(string $path): void
    {
        $this->consulta()->where('ruta', 'like', rtrim($path, '/').'/%')->delete();
    }

    // Las carpetas no existen como tales: son el prefijo de la ruta
    public function createDirectory(string $path, Config $config): void {}

    public function setVisibility(string $path, string $visibility): void {}

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, visibility: 'private');
    }

    public function mimeType(string $path): FileAttributes
    {
        $fila = $this->fila($path) ?? throw UnableToRetrieveMetadata::mimeType($path, 'El archivo no existe.');

        return new FileAttributes($path, mimeType: $fila->mime);
    }

    public function lastModified(string $path): FileAttributes
    {
        $fila = $this->fila($path) ?? throw UnableToRetrieveMetadata::lastModified($path, 'El archivo no existe.');

        return new FileAttributes($path, lastModified: strtotime($fila->updated_at));
    }

    public function fileSize(string $path): FileAttributes
    {
        $fila = $this->fila($path) ?? throw UnableToRetrieveMetadata::fileSize($path, 'El archivo no existe.');

        return new FileAttributes($path, fileSize: (int) $fila->tamano);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefijo = trim($path, '/') === '' ? '' : trim($path, '/').'/';
        $carpetas = [];

        foreach ($this->consulta()->where('ruta', 'like', $prefijo.'%')->orderBy('ruta')->get(['ruta', 'tamano', 'updated_at']) as $fila) {
            $resto = substr($fila->ruta, strlen($prefijo));

            if (! $deep && str_contains($resto, '/')) {
                $carpeta = $prefijo.strstr($resto, '/', true);
                if (! isset($carpetas[$carpeta])) {
                    $carpetas[$carpeta] = true;
                    yield new DirectoryAttributes($carpeta);
                }

                continue;
            }

            yield new FileAttributes($fila->ruta, (int) $fila->tamano, null, strtotime($fila->updated_at));
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $this->copy($source, $destination, $config);
        $this->delete($source);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $this->write($destination, $this->read($source), $config);
    }
}
