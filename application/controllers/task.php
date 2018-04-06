<?php

use core\Controller,
    core\DatabaseSQL,
    core\Path;

class ControllerTask extends Controller
{
    public function actionFirstNames()
    {
        $database = new DatabaseSQL(
            DatabaseSQL::DRIVER_MYSQL,
            '192.168.10.10',
            'sos_i18n_names',
            'root',
            'q2w3e4r5'
        );

        $sql = "SELECT first_name.name, lang_usage.iso
                FROM first_name
                    
                INNER JOIN lang_usage
                ON lang_usage.lang = first_name.lang
                
                INNER JOIN name_rank
                ON name_rank.name_id = first_name.id
                
                ORDER BY lang_usage.iso, name_rank.rank";

        $records = $database->getRecordsSQL($sql);

        $names = [];
        foreach ($records as $record) {
            $country = strtolower($record['iso']);

            if (empty($names[$country])) {
                $names[$country] = [];
            }

            $names[$country][] = $record['name'];
        }

        foreach ($names as $country => $list) {
            $path = new Path("cache\\firstnames\\{$country}");
            $path->mkpath(true);
            file_put_contents($path, implode("\n", $list));
        }

	echo "\nOK!\n";
    }


}
