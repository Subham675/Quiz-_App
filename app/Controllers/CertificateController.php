<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Models\Certificate;

class CertificateController extends Controller
{
    public function index(Request $request): void
    {
        $userId = $_SESSION['user_id'];
        $certs = Certificate::getUserCertificates($userId);

        $this->render('certificates/index', [
            'pageTitle' => 'My Certificates',
            'activeNav' => 'certificates',
            'certs'     => $certs,
        ], 'main');
    }
}
