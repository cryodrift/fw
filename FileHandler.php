<?php

//declare(strict_types=1);

namespace cryodrift\fw;

use SplFileInfo;
use cryodrift\fw\interface\Configs;
use cryodrift\fw\interface\Handler;

class FileHandler implements Handler, Configs
{


    public function __construct(protected Config $config)
    {
    }

    public function handle(Context $ctx): Context
    {
        $file = $ctx->request()->path()->getString();
        $pathname = Core::getValue($file, $this->config['files']);
        if ($pathname) {
            $ctx = $this->handleFile($pathname, clone $ctx);
        } else {
            if (str_contains($pathname, '.')) {
                Core::echo(__METHOD__, 'Not Found:', $file);
            }
        }
        return $ctx;
    }


    public static function getHeaders(\SplFileInfo $fileInfo, int $duration): array
    {
        $header = [];
        $mimeType = self::getFileType($fileInfo);

        // Set the appropriate headers
        $header[] = "Content-Type: $mimeType";


        $lastModifiedTime = $fileInfo->getMTime();
        $md5 = md5($fileInfo->getPathname() . $lastModifiedTime);

        $header[] = 'Cache-Control: public, max-age=' . $duration;
        $header[] = 'Expires: ' . gmdate('D, d M Y H:i:s', time() + $duration) . ' GMT';
        $header[] = 'Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModifiedTime) . ' GMT';
        $header[] = 'ETag: "' . $md5 . '"';

        return $header;
    }

    public static function getDownloadHeader($mimeType, $filename): array
    {
        $header=[];
        $isDownloadable = preg_match('/^(application|image|video|audio)\//', $mimeType);
        if ($isDownloadable) {
            $header[] = 'Content-Disposition: attachment; filename="' . $filename . '"';
        } else {
            $header[] = 'Content-Disposition: inline; filename="' . $filename . '"';
        }
        $header[] = 'Content-Transfer-Encoding: binary';
        return $header;
    }

    public static function isCached(SplFileInfo $fileInfo, Context $ctx): bool
    {
        if ($ctx->request()->getHeaders('pragma') == 'no-cache') {
            return false;
        }
        $lastModifiedTime = $fileInfo->getMTime();
        $md5 = md5($fileInfo->getPathname() . $lastModifiedTime);

        // Check if the browser has a cached version and it matches the current file's ETag or Last-Modified
        $ifModifiedSince = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ? strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']) : false;
        $ifNoneMatch = isset($_SERVER['HTTP_IF_NONE_MATCH']) ? trim($_SERVER['HTTP_IF_NONE_MATCH']) : false;

        if (($ifNoneMatch && $ifNoneMatch === '"' . $md5 . '"') || ($ifModifiedSince && $ifModifiedSince >= $lastModifiedTime)) {
            return true;
        }
        return false;
    }

    /**
     * http://svn.apache.org/repos/asf/httpd/httpd/trunk/docs/conf/mime.types
     */
    public static function getFileType(\SplFileInfo $finfo): string
    {
        $ext = $finfo->getExtension();

        $search = strtolower($ext);
        $map = self::mimetypes();
        switch (true) {
            case array_key_exists($search, $map):
                $type = $map[$search];
                break;
            default:
                // ! not using 3 lines of fileinfo
                $type = mime_content_type($finfo->getPathname());
        }
        return $type;
    }

    public static function mimetypes(): array
    {
        return [
          'css' => 'text/css',
          'js' => 'application/javascript',
          'json' => 'application/json',
          'jsx' => 'application/javascript',
          'text/javascript' => 'application/javascript',
          'jpg' => 'image/jpeg',
          'jpeg' => 'image/jpeg',
          'png' => 'image/png',
          'gif' => 'image/gif',
          'html' => 'text/html',
          'htm' => 'text/html',
          'tpl' => 'text/html',
          'txt' => 'text/plain',
          'pdf' => 'application/pdf',
          'yml' => 'text/yaml',
          'yaml' => 'text/yaml',
            // not allowed
          'php' => 'text/plain'
        ];
    }

    public function files(Context $ctx): Context
    {
        $ctx->response()->setData(array_merge($ctx->response()->getData(), Core::getValue('files', Core::getValue(self::class, $ctx->config('Handler')))));
        return $ctx;
    }

    public function folder(Context $ctx, string $assetdir, int $filepos = -1): Context
    {
        $file = $ctx->request()->path()->getString('/', $filepos);
        $pathname = $assetdir . '/' . $file;
//        Core::echo(__METHOD__, $pathname);
        $this->handleFile($pathname, $ctx);
        return $ctx;
    }

    public static function addConfigs(Context $ctx, array $data, string $typ = ''): void
    {
        $config = $ctx->config()->getHandler(self::class);
        foreach ($data as $route => $pathname) {
            $config['files'][$route] = $pathname;
        }
        $ctx->config()->addHandler(self::class, $config);
    }

    protected function handleFile(string $pathname, Context $ctx): Context
    {
        $pathname = Main::path($pathname);
        if ($pathname) {
            $fileInfo = new SplFileInfo($pathname);
            if (!$fileInfo->isFile() || $fileInfo->getSize() < 1) {
                header(Response::HEADER_NOTFOUND);
                exit;
            }
            $headers = self::getHeaders($fileInfo, $this->config->cacheDuration);

            if (self::isCached($fileInfo, $ctx)) {
                $headers[] = Response::HEADER_NOTMODIFIED;
            } else {
                $ctx->response()->setContent(Core::fileReadOnce($pathname));
            }

            $ctx->response()->setHeaders($headers);
            $ctx->response()->status(Response::STATUS_VALID);
        }
        return $ctx;
    }

}
