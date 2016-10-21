<?php
// HCPHP: tiny PHP framework
// Copyright (C) 2014-2016 Yehven Matasar
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by 
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This is attempt to implement a tiny MVC framework.
 * 
 * @package    hcphp
 * @copyright  2014 Yevhen Matasar <matasar.ei@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @version    20141109
 */
require_once 'application/init.php';

use core\Application;

Application::start();