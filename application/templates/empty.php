<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title><?php echo empty($title) ? 'HCPHP' : $title ?></title>
        <!-- display -->
        <meta name="theme-color" content="#009587">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!--[if lt IE 9]>
            <script src="/shared/js/html5.min.js" language="javascript"></script>
            <script src="/shared/js/respond.min.js" language="javascript"></script>
        <![endif]-->

        <!-- CSS -->
        <link type="text/css" rel="stylesheet" href="/shared/css/bootstrap.min.css">
        <!-- Material styles -->
        <link href='http://fonts.googleapis.com/css?family=Open+Sans:300,400&subset=cyrillic,cyrillic-ext,latin' rel='stylesheet' type='text/css'>
        <link type="text/css" rel="stylesheet" href="/shared/css/material.min.css">

    </head>

    <body>
        <div class="content">
            <div class="container">
                <!-- Reserved $content var for page content -->
                <?php echo $content ?>
            </div>
        </div>
    </body>
</html>