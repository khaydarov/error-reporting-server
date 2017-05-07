<?php

/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Developer controller handling developer login/logout/register.
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

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Network\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

/**
 * Developer controller handling developer login/logout/register.
 */
class DevelopersController extends AppController
{
    public $helpers = array('Html', 'Form');

    public $components = array(
        'GithubApi',
    );

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        $this->GithubApi->githubConfig = Configure::read('GithubConfig');
        $this->GithubApi->githubRepo = Configure::read('GithubRepoPath');
    }

    public function login()
    {
        $url = $this->GithubApi->getRedirectUrl('user:email,public_repo');
        $this->redirect($url);
    }

    public function callback()
    {
        $code = $this->request->query('code');
        $accessToken = $this->GithubApi->getAccessToken($code);
        if ($accessToken) {
            list($userInfo, $status) = $this->GithubApi->getUserInfo($accessToken);
            if ($status != 200) {
                $this->Session->setFlash($userInfo['message'],
                        array('class' => 'alert alert-error'));
            } else {
                $userInfo['has_commit_access'] = $this->GithubApi->canCommitTo(
                        $userInfo['login'], $this->GithubApi->githubRepo);

                $this->_authenticateDeveloper($userInfo, $accessToken);

                $flash_class = 'alert alert-success';
                $this->Flash->default('You have been logged in successfully',
                        array('params' => array('class' => $flash_class)));
            }
        } else {
            $flash_class = 'alert alert-error';
            $this->Flash->default('We were not able to authenticate you.'
                    . 'Please try again later',
                    array('params' => array('class' => $flash_class)));
        }
        $last_page = $this->request->session()->read('last_page');
        if (empty($last_page)) {
            $last_page = array('controller' => 'reports', 'action' => 'index');
        }
        $this->redirect($last_page);
    }

    public function logout()
    {
        $this->request->session()->destroy();

        $flash_class = 'alert alert-success';
        $this->Flash->default('You have been logged out successfully',
                array('params' => array('class' => $flash_class)));
        $this->redirect('/');
    }

    public function currentDeveloper()
    {
        $this->autoRender = false;

        return json_encode($this->GithubApi->canCommitTo('smita786',
                'smita786/phpmyadmin'));
    }

    public function create_issue($reportId)
    {
        if (!$reportId) {
            throw new \NotFoundException(__('Invalid report'));
        }

        $report = TableRegistry::get('Reports')->findById($reportId)->toArray();
        if (!$report) {
            throw new NotFoundException(__('Invalid report'));
        }

        if (empty($this->request->data)) {
            $this->set('pma_version', $report[0]['pma_version']);
            $this->set('error_name', $report[0]['error_name']);
            $this->set('error_message', $report[0]['error_message']);

            return;
        }
        $data = array(
            'title' => $this->request->data['summary'],
            'body' => $this->_augmentDescription(
                    $this->request->data['description'], $reportId),
            'labels' => $this->request->data['labels'] ? explode(',', $this->request->data['labels']) : array(),
        );
        $data['labels'][] = 'automated-error-report';
        list($issueDetails, $status) = $this->GithubApi->create_issue(
            'smita786/tic-tac-toe-php',
            $data,
            $this->request->session()->read('access_token')
        );

        $this->redirect(array('controller' => 'reports', 'action' => 'view',
                    $reportId, ));
    }

    protected function _authenticateDeveloper($userInfo, $accessToken)
    {
        $developers = $this->Developers->findByGithubId($userInfo['id']);
        $developer = $developers->all()->first();
        if (!$developer) {
            $developer = $this->Developers->newEntity();
        } else {
            $this->Developers->id = $developer['id'];
        }
        $this->Developers->id = $this->Developers->saveFromGithub($userInfo, $accessToken, $developer);
        $this->request->session()->write('Developer.id', $this->Developers->id);
        $this->request->session()->write('access_token', $accessToken);
    }

    /**
     * Returns the description with the added string to link to the report.
     *
     * @param string $description the original description submitted by the dev
     * @param string $reportId    the report id relating to the ticket
     *
     * @return string augmented description
     */
    protected function _augmentDescription($description, $reportId)
    {
        $report = TableRegistry::get('Reports');
        $report->id = $reportId;

        return '$description\n\n\nThis report is related to user submitted report '
                . '[#' . $report->id . '](' . $report->getUrl()
                . ') on the phpmyadmin error reporting server.';
    }
}
