<?php

/**
 * 
 */
class ModelFile extends Modell {
    
    static function getFile() {
        if (isset($_FILES[$name])) {
            $file = new stdClass;
            $file->name = $_FILES[$name]['name'];
            $file->type = $_FILES[$name]['type'];
            $file->size = $_FILES[$name]['size'];
            $file->tmp_name = $_FILES[$name]['tmp_name'];
            $file->error = $_FILES[$name]['error'];
            return $file;
        }
        else return false;
    }
    
}
