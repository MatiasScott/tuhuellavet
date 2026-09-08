<?php
namespace App\Services;

use Google\Client as GoogleClient;
use App\Models\User;
use App\Core\Database;

class GoogleAuthService
{
    public function isConfigured(): bool
    {
        return !empty($_ENV['GOOGLE_CLIENT_ID'])
            && !empty($_ENV['GOOGLE_CLIENT_SECRET'])
            && !empty($_ENV['GOOGLE_REDIRECT_URI']);
    }

    private function client(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId($_ENV['GOOGLE_CLIENT_ID'] ?? '');
        $client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET'] ?? '');
        $client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI'] ?? '');
        $client->setScopes(['openid','email','profile']);
        $client->setPrompt('select_account');
        return $client;
    }

    public function authorizationUrl(): string { return $this->client()->createAuthUrl(); }

    public function authenticateCode(string $code): ?array
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (!empty($token['error'])) return null;

        $client->setAccessToken($token);
        $oauth = new \Google\Service\Oauth2($client);
        $googleUser = $oauth->userinfo->get();

        if (!$googleUser->email || !$googleUser->verifiedEmail) return null;

        $user = (new User())->findByEmail($googleUser->email);
        if (!$user) return null;

        $this->linkIdentity((int)$user['id'],(string)$googleUser->id,(string)$googleUser->email);
        return $user;
    }

    private function linkIdentity(int $userId,string $subject,string $email): void
    {
        $db = Database::connection();
        $providerId = (int)$db->query("SELECT id FROM proveedores_autenticacion WHERE codigo='GOOGLE' LIMIT 1")->fetchColumn();

        $stmt = $db->prepare(
            "INSERT INTO usuario_identidades
             (usuario_id,proveedor_id,subject_externo,email_externo,email_verificado,ultimo_login_at,activo)
             VALUES (:usuario,:proveedor,:subject,:email,1,NOW(),1)
             ON DUPLICATE KEY UPDATE
             subject_externo=VALUES(subject_externo),
             email_externo=VALUES(email_externo),
             email_verificado=1,
             ultimo_login_at=NOW(),
             activo=1"
        );
        $stmt->execute([
            'usuario'=>$userId,'proveedor'=>$providerId,'subject'=>$subject,'email'=>$email
        ]);
    }
}
