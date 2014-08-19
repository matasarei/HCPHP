<!-- menu -->
<div class='menu'>

<?php
$currentUrl = new Url(true);

$menuitems = array();
$menuitems['Index'] = new Url('index');
$menuitems['Sandbox'] = new Url('sandbox');
$menuitems['404'] = new Url('404');


if (ModelAuth::isLoggedIn()) {
    $menuitems['Logout'] = new Url('user/logout');    
} else {
    $menuitems['Login'] = new Url('user/login');
}

foreach ($menuitems as $key => $value) {
    $args = array('class' => 'menuitem');
    if (preg_match("#{$value}#ui", $currentUrl)) {
        $args['id'] = 'selected';
    }
	echo Writer::tag('div', Writer::tag('a', $key, array('href'=>$value)), $args);
}
?>

</div>
<!-- menu -->