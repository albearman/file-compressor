<?php

declare(strict_types=1);

namespace Bearman\FileCompressor;

use Bearman\FileCompressor\Exception\EmptyKeyException;
use Bearman\FileCompressor\Exception\EmptyNewFileException;
use Bearman\FileCompressor\Exception\FileNotFoundException;
use Bearman\FileCompressor\Exception\ModeNotSupportException;
use Bearman\FileCompressor\Tinify\Client;
use Exception;
use Ilovepdf\Ilovepdf;
use Tinify\ClientException;
use Tinify\Source;
use Tinify\Tinify;

class Compressor
{
    public const MODE_FILE = 'file';
    public const MODE_URL = 'url';

    public const COMPRESSION_LEVEL_LOW = 'low';
    public const COMPRESSION_LEVEL_RECOMMENDED = 'recommended';
    public const COMPRESSION_LEVEL_EXTREME = 'extreme';

    /** @var Client */
    public $tinyPngClient;

    /** @var Ilovepdf */
    public $iLovePDF;


    /**
     * Compressor constructor.
     *
     * @param string $tinyPngKey
     * @param string $iLovePDFPair ProjectId:ProjectKey
     *
     * @throws ClientException
     */
    public function __construct(
        string $tinyPngKey = 'crazy',
        string $iLovePDFPair = ''
    ) {
        $this->tinyPngClient = new Client($tinyPngKey);

        Tinify::setKey($tinyPngKey);
        Tinify::setClient($this->tinyPngClient);

        if (false === empty($iLovePDFPair)) {
            list($iLovePDFProjectId, $iLovePDFProjectKey) = explode(
                ':',
                $iLovePDFPair
            );
            $this->iLovePDF = new Ilovepdf(
                $iLovePDFProjectId,
                $iLovePDFProjectKey
            );
        }
    }

    /**
     * @param string $file
     * @param string $newFile
     * @param string $mode
     *
     * @return string
     * @throws EmptyNewFileException
     * @throws FileNotFoundException
     * @throws ModeNotSupportException
     */
    public function compressionImage(
        string $file,
        string $newFile = '',
        string $mode = self::MODE_FILE
    ): string {
        switch ($mode) {
            case self::MODE_FILE:
                if (file_exists($file) === false) {
                    throw new FileNotFoundException($file);
                }
                if (empty($newFile)) {
                    $newFile = $file;
                }
                $source = Source::fromFile($file);
                break;
            case self::MODE_URL:
                if (empty($newFile)) {
                    throw new EmptyNewFileException();
                }
                $source = Source::fromUrl($file);
                break;
            default:
                throw new ModeNotSupportException();
        }

        $source->toFile($newFile);

        return $newFile;
    }

    /**
     * @param string $file
     * @param string $level
     *
     * @return string
     * @throws EmptyKeyException
     * @throws FileNotFoundException
     * @throws Exception
     */
    public function compressionPDF(
        string $file,
        string $level = self::COMPRESSION_LEVEL_RECOMMENDED
    ): string {
        if (empty($this->iLovePDF)
            || ($this->iLovePDF instanceof Ilovepdf) == false
        ) {
            throw new EmptyKeyException('ILovePDF');
        }

        if (!file_exists($file)) {
            throw new FileNotFoundException($file);
        }

        if (!in_array(
            $level,
            [
                self::COMPRESSION_LEVEL_LOW,
                self::COMPRESSION_LEVEL_RECOMMENDED,
                self::COMPRESSION_LEVEL_EXTREME
            ]
        )
        ) {
            $level = self::COMPRESSION_LEVEL_RECOMMENDED;
        }

        $task = $this->iLovePDF->newTask('compress');

        $task->setCompressionLevel($level);
        $task->addFile($file);
        $task->execute();
        $task->download(
            pathinfo($file, PATHINFO_DIRNAME)
        );

        return $file;
    }
}
