<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Error Controller.
 *
 * This file is error controller file. It is required only because
 * AppController::beforeFilter() does not get called in case of errors
 * and exceptions.
 *
 * Ref: https://www.bradezone.com/2009/05/21/cakephp-beforefilter-and-the-error-error/
 *
 * phpMyAdmin Error reporting server
 * Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) phpMyAdmin project (https://www.phpmyadmin.net/)
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 *
 * @see      https://www.phpmyadmin.net/
 */
namespace App\Controller;

use App\Controller\AppController;
use Cake\Event\Event;

class ErrorController extends AppController
{

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
    }

    public function beforeRender(Event $event)
    {
        parent::beforeRender($event);
        $this->viewBuilder()->templatePath('Error');
    }

}
