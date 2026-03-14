<?php
/**
 * Configuração e funções para JWT (JSON Web Tokens)
 * Implementação simples sem bibliotecas externas
 */

// Define as constantes diretamente (sem usar função env)
define('JWT_SECRET', 'sua_chave_secreta_super_segura_aqui_2024');
define('JWT_EXPIRATION', 86400); // 24 horas em segundos

class JWT {
    
    /**
     * Gera um token JWT
     * @param array $payload Dados a serem incluídos no token
     * @return string Token JWT
     */
    public static function generate($payload) {
        // Cabeçalho do token
        $header = json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]);
        
        // Adiciona timestamps ao payload
        $payload['iat'] = time(); // Emitido em
        $payload['exp'] = time() + JWT_EXPIRATION; // Expiração
        
        // Codifica header e payload em base64url
        $base64UrlHeader = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
        $base64UrlPayload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode(json_encode($payload)));
        
        // Cria a assinatura
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        // Retorna o token completo
        return $base64UrlHeader . "." . $base64UrlPayload . "." . $base64UrlSignature;
    }
    
    /**
     * Valida e decodifica um token JWT
     * @param string $token Token JWT
     * @return array|false Payload do token ou false se inválido
     */
    public static function validate($token) {
        // Divide o token em partes
        $parts = explode('.', $token);
        if (count($parts) != 3) {
            return false;
        }
        
        list($base64UrlHeader, $base64UrlPayload, $base64UrlSignature) = $parts;
        
        // Recalcula a assinatura
        $signature = hash_hmac('sha256', $base64UrlHeader . "." . $base64UrlPayload, JWT_SECRET, true);
        $base64UrlSignatureCheck = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
        
        // Verifica se a assinatura é válida
        if (!hash_equals($base64UrlSignatureCheck, $base64UrlSignature)) {
            return false;
        }
        
        // Decodifica o payload
        $payload = json_decode(base64_decode(str_replace(['-', '_'], ['+', '/'], $base64UrlPayload)), true);
        
        // Verifica se o token expirou
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            return false;
        }
        
        return $payload;
    }
    
    /**
     * Gera um token para recuperação de senha (opcional)
     * @param int $user_id ID do usuário
     * @return string Token
     */
    public static function generateResetToken($user_id) {
        $payload = [
            'user_id' => $user_id,
            'type' => 'reset_password',
            'exp' => time() + 3600 // 1 hora
        ];
        
        return self::generate($payload);
    }
}
?>