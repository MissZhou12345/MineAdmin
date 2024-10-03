<?php

declare(strict_types=1);
/**
 * This file is part of MineAdmin.
 *
 * @link     https://www.mineadmin.com
 * @document https://doc.mineadmin.com
 * @contact  root@imoi.cn
 * @license  https://github.com/mineadmin/MineAdmin/blob/master/LICENSE
 */
use App\Http\Common\Middleware\AccessTokenMiddleware;
use App\Ws\Admin\Controller\ServerController;
use Hyperf\HttpServer\Router\Router;

Router::get('/', static function () {
    return 'welcome use mineAdmin';
});

Router::get('/favicon.ico', static function () {
    return '';
});

// 消息ws服务器
Router::addServer('message', static function () {
    Router::get('/message.io', ServerController::class, [
        'middleware' => [AccessTokenMiddleware::class],
    ]);
});
