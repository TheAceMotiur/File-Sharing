<?php

use App\Middleware\AuthMiddleware;
use App\Middleware\GuestMiddleware;
use App\Middleware\AdminMiddleware;

/**
 * Routes Configuration
 */

// Public routes (guest only)
$router->get('/', 'HomeController@index', [GuestMiddleware::class]);
$router->get('login', 'AuthController@login', [GuestMiddleware::class]);
$router->post('login', 'AuthController@login', [GuestMiddleware::class]);
$router->get('register', 'AuthController@register', [GuestMiddleware::class]);
$router->post('register', 'AuthController@register', [GuestMiddleware::class]);
$router->get('forgot-password', 'AuthController@forgotPassword', [GuestMiddleware::class]);
$router->post('forgot-password', 'AuthController@forgotPassword', [GuestMiddleware::class]);
$router->get('reset-password', 'AuthController@resetPassword', [GuestMiddleware::class]);
$router->post('reset-password', 'AuthController@resetPassword', [GuestMiddleware::class]);

// Static pages
$router->get('terms', 'HomeController@terms');
$router->get('privacy', 'HomeController@privacy');
$router->get('dmca', 'HomeController@dmca');
$router->get('docs', 'HomeController@docs');

// Auth routes
$router->get('logout', 'AuthController@logout', [AuthMiddleware::class]);
$router->get('verify', 'AuthController@verify', [AuthMiddleware::class]);

// User dashboard routes (requires authentication)
$router->get('dashboard', 'DashboardController@index', [AuthMiddleware::class]);
$router->get('upload', 'DashboardController@upload', [AuthMiddleware::class]);
$router->get('profile', 'DashboardController@profile', [AuthMiddleware::class]);
$router->post('profile', 'DashboardController@profile', [AuthMiddleware::class]);

// File routes
$router->get('download/{id}', 'FileController@download');
$router->get('info/{id}', 'FileController@info');
$router->get('wait/{id}', 'FileController@wait');
$router->get('report/{id}', 'FileController@report');
$router->post('report/{id}', 'FileController@report');

// Dropbox OAuth callback
$router->get('dropbox/callback', 'DropboxController@callback', [AdminMiddleware::class]);

// API routes (requires authentication)
$router->post('api/upload', 'ApiController@upload', [AuthMiddleware::class]);
$router->post('api/delete', 'ApiController@delete', [AuthMiddleware::class]);
$router->post('api/rename', 'ApiController@rename', [AuthMiddleware::class]);
$router->get('api/folders', 'ApiController@folders', [AuthMiddleware::class]);
$router->post('api/folders', 'ApiController@createFolder', [AuthMiddleware::class]);
$router->post('api/folders/rename', 'ApiController@renameFolder', [AuthMiddleware::class]);
$router->post('api/folders/delete', 'ApiController@deleteFolder', [AuthMiddleware::class]);

// Admin routes (requires admin)
$router->get('admin', 'AdminController@dashboard', [AdminMiddleware::class]);
$router->get('admin/dashboard', 'AdminController@dashboard', [AdminMiddleware::class]);
$router->get('admin/users', 'AdminController@users', [AdminMiddleware::class]);
$router->get('admin/files', 'AdminController@files', [AdminMiddleware::class]);
$router->post('admin/deleteFile', 'AdminController@deleteFile', [AdminMiddleware::class]);
$router->get('admin/reports', 'AdminController@reports', [AdminMiddleware::class]);
$router->post('admin/reports/resolve/{id}', 'AdminController@resolveReport', [AdminMiddleware::class]);
$router->post('admin/reports/reject/{id}', 'AdminController@rejectReport', [AdminMiddleware::class]);
$router->post('admin/reports/delete-file/{id}', 'AdminController@deleteFileEverywhere', [AdminMiddleware::class]);
$router->get('admin/settings', 'AdminController@settings', [AdminMiddleware::class]);
$router->post('admin/settings', 'AdminController@settings', [AdminMiddleware::class]);
$router->get('admin/email-settings', 'AdminController@emailSettings', [AdminMiddleware::class]);
$router->post('admin/email-settings', 'AdminController@emailSettings', [AdminMiddleware::class]);
$router->get('admin/dropbox', 'AdminController@dropbox', [AdminMiddleware::class]);
$router->post('admin/dropbox', 'AdminController@dropbox', [AdminMiddleware::class]);
$router->get('admin/cron-jobs', 'AdminController@cronJobs', [AdminMiddleware::class]);
$router->post('admin/cron-jobs', 'AdminController@cronJobs', [AdminMiddleware::class]);

// Cache management
$router->get('admin/cache', 'AdminController@cache', [AdminMiddleware::class]);
$router->get('admin/cache/stats', 'AdminController@cacheStats', [AdminMiddleware::class]);
$router->post('admin/cache/clear', 'AdminController@cacheClear', [AdminMiddleware::class]);
$router->post('admin/cache/delete', 'AdminController@cacheDelete', [AdminMiddleware::class]);
