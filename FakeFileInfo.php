<?php

//declare(strict_types=1);

namespace cryodrift\fw;

class FakeFileInfo extends \SplTempFileObject
{

    protected string $fextension = '';
    protected int $size = 0;

    public function fwrite(string $data, int $length = 0): int|false
    {
        $this->size += strlen($data);
        return parent::fwrite($data, $length);
    }

    public function setFextension(string $fextension): void
    {
        $this->fextension = $fextension;
    }

    public function getMTime(): int|false
    {
        $time = new \DateTime();
        return $time->getTimestamp();
    }


    public function getSize(): int
    {
        return $this->size;
    }

    public function getExtension(): string
    {
        return $this->fextension;
    }

}
