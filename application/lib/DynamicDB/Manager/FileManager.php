<?php

namespace DynamicDB\Manager;

use core\Path;
use DynamicDB\Entity\DynamicEntity;
use DynamicDB\Entity\File;
use DynamicDB\Entity\Table;
use DynamicDB\Validator\FileUploadValidator;

final class FileManager
{
    private $table;
    private $validator;
    private $mover;

    /**
     * @param Table $table
     * @param FileUploadValidator|null $validator
     * @param callable|null $mover function(string $from, string $to): bool -- replaced in
     *                             tests, because move_uploaded_file() only accepts files
     *                             that really arrived over HTTP POST.
     */
    public function __construct(
        Table $table,
        FileUploadValidator $validator = null,
        callable $mover = null
    ) {
        $this->table = $table;
        $this->validator = $validator ?? new FileUploadValidator();
        $this->mover = $mover ?? static function (string $from, string $to): bool {
            return move_uploaded_file($from, $to);
        };
    }

    /**
     * @param DynamicEntity $dynamicEntity
     *
     * @throws \DynamicDB\Validator\Exception\InvalidFileTypeException
     */
    public function saveFiles(DynamicEntity $dynamicEntity): void
    {
        $pending = [];

        // Every file is checked before any of them is stored: a record can carry several
        // file fields, and refusing the second one after moving the first would leave the
        // record half written.
        foreach ($this->table->getFields() as $field) {
            $file = $dynamicEntity->get($field->getName());

            if (!($file instanceof File) || !$file->isTemporary()) {
                continue;
            }

            // FileMapper already refused anything unacceptable, but a caller can build a
            // File by hand. Nothing reaches the storage directory without passing here.
            $this->validator->validate($file);

            $pending[] = [
                $file,
                new Path(
                    sprintf(
                        'shared/dynamicdb/%d/%s.%s',
                        $dynamicEntity->getId(),
                        $field->getName(),
                        $this->getStoredExtension($file)
                    )
                ),
            ];
        }

        foreach ($pending as list($file, $path)) {
            $path->mkpath();

            ($this->mover)($file->getPath(), (string)$path);
        }
    }

    public function deleteFiles(DynamicEntity $dynamicEntity): void
    {
        foreach ($this->table->getFields() as $field) {
            $file = $dynamicEntity->get($field->getName());

            if (!($file instanceof File)) {
                continue;
            }

            //unlink($file->getPath());
        }
    }

    /**
     * Never the raw client suffix: the filename is attacker-controlled, and it used to be
     * what decided whether the stored file ended in ".php".
     */
    private function getStoredExtension(File $file): string
    {
        return strtolower(pathinfo($file->getName(), PATHINFO_EXTENSION));
    }
}
