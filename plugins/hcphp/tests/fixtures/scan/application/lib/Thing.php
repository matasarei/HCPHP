<?php

class Thing
{
    public function render()
    {
        return Xml::tag('p', Globals::post('comment'));
    }

    public function search($database, $q, $id)
    {
        $database->executeSQL("SELECT * FROM item WHERE name LIKE '%" . $q . "%'");
        return $database->executeSQL("SELECT * FROM item WHERE id = $id");
    }

    public function store($file, $sheet, $value)
    {
        move_uploaded_file($file['tmp_name'], 'shared/' . $file['name']);
        $data = unserialize($value);
        shell_exec('convert ' . $file['name']);
        $sheet->setCellValue('A1', $value);
        setcookie('remember', $value, time() + 3600);

        return $data;
    }

    public function login($email, $password)
    {
        return $email !== '' && $password !== '';
    }
}
