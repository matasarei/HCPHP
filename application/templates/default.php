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
        <link type="text/css" rel="stylesheet" href="/shared/css/ripples.min.css">
        <link type="text/css" rel="stylesheet" href="/shared/css/snackbar.min.css">
        <link type="text/css" rel="stylesheet" href="/shared/css/magnific-popup.css">
        <!-- Custom styles -->
        <link type="text/css" rel="stylesheet" href="/shared/css/style.css">

    </head>

    <body>
        <div id="page">
            <header class="shadow-z-2">
                <div class="container">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="navbar navbar-default">
                                <div class="navbar-header">
                                    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-responsive-collapse">
                                        <span class="icon-bar"></span>
                                        <span class="icon-bar"></span>
                                        <span class="icon-bar"></span>
                                    </button>
                                    <a class="navbar-brand" href="/">HCPHP</a>
                                </div>
                                <div class="navbar-collapse collapse navbar-responsive-collapse">
                                    <ul class="nav navbar-nav">
                                        <?php foreach ($mainMenu as $item): ?>
                                            <li>
                                                <a href="<?php echo $item->url ?>"><?php echo $item->name ?></a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            <div class="content">
                <div class="container">
                    <!-- Reserved $content var for page content -->
                    <?php echo $content ?>
                </div>
            </div>
        </div>
        <footer>
            <div class="container">
                <div class="row">
                    <div class="col-xs-3">
                        <a href="mailto:matasar.ei@gmail.com">Yevhen Matasar</a>, 2016
                    </div>
                    <div class="col-xs-9">
                        <menu>
                            <li>
                                <a href="http://fezvrasta.github.io/bootstrap-material-design/bootstrap-elements.html">
                                    Material Design
                                </a>
                            </li>
                            <?php foreach ($mainMenu as $item): ?>
                                <li>
                                    <a href="<?php echo $item->url ?>"><?php echo $item->name ?></a>
                                </li>
                            <?php endforeach; ?>
                        </menu>
                    </div>
                </div>
            </div>
        </footer>

        {**** JS ****}
        <script src="/shared/js/jquery-2.1.3.min.js"></script>
        <script src="/shared/js/bootstrap.min.js"></script>
        {* Material scripts *}
        <script src="/shared/js/material.min.js"></script>
        <script src="/shared/js/ripples.min.js"></script>
        <script src="/shared/js/snackbar.min.js"></script>
        <script src='https://www.google.com/recaptcha/api.js'></script>
        {* Popup *}
        <script src="/shared/js/magnific-popup.min.js"></script>
        {* Custom *}
        <script src="/shared/js/start.js"></script>
    </body>
</html>