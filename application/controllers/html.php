<?php
/**
 * Main controller
 *
 * @package    hcphp
 * @copyright  Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core\Controller,
    core\View,
    core\Url,
    core\Html,
    html\Table;

class ControllerHtml extends Controller {
    
    /**
     * /html/test/
     */
    public function actionTest() {
        $view = new View();
        $view->layout->set('title', 'html test');
        
        $view->set('description', Html::tag('p', 'Tricks with HTML generator', [
            'class' => 'text-success'
        ]));
        
        $url = 'http://fezvrasta.github.io/bootstrap-material-design/bootstrap-elements.html';
        $view->set('link', Html::link($url, 'Material design', [
            'class' => 'btn btn-default'
        ]));
        
        $table = new Table();
        $table->addAttribute('class', 'table table-striped table-hover');
        $table->caption = 'Table test';
        $table->head = ['#', 'col1', 'col2', 'col3', 'col4'];
        $table->data = [
            ['1', 'cell1', 'cell2', 'cell3', 'cell4'],
            ['1', 'cell5', 'cell6', 'cell7', 'cell8']
        ];
        
        $view->setData([
            'hello' => Html::tag('button', 'Say hello!', [
                'type' => 'button',
                'data-content' => 'Hello! Wellcome to HCPHP!',
                'class' => 'btn btn-default',
                'data-toggle' => 'snackbar',
                'data-timeout' => '0'
            ]),
            'image' => Html::thumbnail(new Url('/shared/img/code.jpg')),
            'table' => $table,
            'list' => Html::htmlList(['one', 'two', 'three', 'four' => [
                '4.1', '4.2', '4.3' => [
                    '4.3.1', '4.3.2'
                ]
            ]])
        ]);
        
        $view->display();
    }
    
}