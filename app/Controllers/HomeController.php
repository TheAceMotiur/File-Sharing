<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Home Controller
 * Handles home and static pages
 */
class HomeController extends Controller
{
    /**
     * Show home page
     */
    public function index()
    {
        // Redirect if logged in
        if (isset($_SESSION['user_id'])) {
            $this->redirect('/dashboard');
        }
        
        $data = [
            'title' => 'Fast & Secure File Sharing Platform',
            'siteName' => getSiteName()
        ];
        
        $this->view('pages/home', $data);
    }
    
    /**
     * Show terms page
     */
    public function terms()
    {
        $data = [
            'title' => 'Terms of Service',
            'siteName' => getSiteName()
        ];
        
        $this->view('pages/terms', $data);
    }
    
    /**
     * Show privacy page
     */
    public function privacy()
    {
        $data = [
            'title' => 'Privacy Policy',
            'siteName' => getSiteName()
        ];
        
        $this->view('pages/privacy', $data);
    }
    
    /**
     * Show DMCA page
     */
    public function dmca()
    {
        $data = [
            'title' => 'DMCA',
            'siteName' => getSiteName()
        ];
        
        $this->view('pages/dmca', $data);
    }
    
    /**
     * Show documentation page
     */
    public function docs()
    {
        $data = [
            'title' => 'Documentation',
            'siteName' => getSiteName()
        ];
        
        $this->view('pages/docs', $data);
    }
}
