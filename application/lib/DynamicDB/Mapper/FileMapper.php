<?php

namespace DynamicDB\Mapper;

use core\MapperInterface;
use DynamicDB\Entity\File;
use DynamicDB\Validator\FileUploadValidator;
use RuntimeException;
use UnexpectedValueException;

class FileMapper implements MapperInterface
{
    /**
     * @var FileUploadValidator
     */
    private $validator;

    public function __construct(?FileUploadValidator $validator = null)
    {
        $this->validator = $validator ?? new FileUploadValidator();
    }

    /**
     * @param array $data
     *
     * @return File
     *
     * @throws UnexpectedValueException When the upload itself failed
     * @throws \DynamicDB\Validator\Exception\InvalidFileTypeException When it is not storable
     */
    public function mapToEntity(array $data)
    {
        // The upload error is reported first: a failed upload has no readable temporary
        // file, and EntityMapper::resolveFile() keys off UPLOAD_ERR_NO_FILE to leave an
        // optional field alone.
        if (!empty($data['error'])) {
            throw new UnexpectedValueException('File cannot be uploaded', (int)$data['error']);
        }

        $file = new File(
            $data['name'],
            $data['type'],
            $data['tmp_name'],
            $data['size'],
            true
        );

        $this->validator->validate($file);

        return $file;
    }

    public function mapFromEntity($entity): array
    {
        throw new RuntimeException('Not supported');
    }
}
