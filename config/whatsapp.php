<?php
/**
 * Configuração da API do WhatsApp
 * Suporta múltiplos provedores (Meta Business, Twilio, Whapi.Cloud)
 */

// Função auxiliar para simular env() se não existir
if (!function_exists('env')) {
    function env($key, $default = null) {
        // Tenta obter do getenv
        $value = getenv($key);
        if ($value !== false) {
            return $value;
        }
        
        // Tenta obter do $_ENV
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        
        // Tenta obter do $_SERVER
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        
        return $default;
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | Provedor de WhatsApp
    |--------------------------------------------------------------------------
    | Opções: 'meta', 'twilio', 'whapi', 'simulacao'
    */
    'provider' => env('WHATSAPP_PROVIDER', 'simulacao'),
    
    /*
    |--------------------------------------------------------------------------
    | Meta (WhatsApp Business API)
    |--------------------------------------------------------------------------
    */
    'meta' => [
        'token' => env('META_WHATSAPP_TOKEN', ''),
        'phone_number_id' => env('META_PHONE_NUMBER_ID', ''),
        'business_account_id' => env('META_BUSINESS_ACCOUNT_ID', ''),
        'api_version' => env('META_API_VERSION', 'v18.0'),
        'base_url' => 'https://graph.facebook.com/',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Twilio WhatsApp API
    |--------------------------------------------------------------------------
    */
    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID', ''),
        'auth_token' => env('TWILIO_AUTH_TOKEN', ''),
        'from_number' => env('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Whapi.Cloud API
    |--------------------------------------------------------------------------
    */
    'whapi' => [
        'api_key' => env('WHAPI_API_KEY', ''),
        'channel_id' => env('WHAPI_CHANNEL_ID', ''),
        'base_url' => 'https://gate.whapi.cloud/',
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Templates de Mensagem
    |--------------------------------------------------------------------------
    */
    'templates' => [
        'confirmacao' => [
            'name' => 'agendamento_confirmado',
            'language' => 'pt_BR',
            'body' => "✂️ *BARBERSHOP PREMIUM*\n\nOlá {{1}}, seu agendamento foi confirmado!\n\n📅 Data: {{2}}\n⏰ Horário: {{3}}\n✂️ Serviços: {{4}}\n\n📍 Endereço: Rua dos Barbeiros, 123\n\nObrigado pela preferência!"
        ],
        'lembrete' => [
            'name' => 'lembrete_agendamento',
            'language' => 'pt_BR',
            'body' => "⏰ *LEMBRETE*\n\nOlá {{1}}, passando para lembrar do seu agendamento amanhã:\n\n📅 Data: {{2}}\n⏰ Horário: {{3}}\n✂️ Serviços: {{4}}\n\n⏱️ Chegue 5 minutos antes.\n\nBarbershop Premium"
        ],
        'cancelamento' => [
            'name' => 'agendamento_cancelado',
            'language' => 'pt_BR',
            'body' => "❌ *AGENDAMENTO CANCELADO*\n\nOlá {{1}}, seu agendamento do dia {{2}} às {{3}} foi cancelado.\n\nPara novo agendamento: {{4}}\n\nBarbershop Premium"
        ],
        'codigo_2fa' => [
            'name' => 'codigo_verificacao',
            'language' => 'pt_BR',
            'body' => "🔐 *CÓDIGO DE VERIFICAÇÃO*\n\nSeu código de verificação Barbershop é: *{{1}}*\n\nVálido por 5 minutos.\n\nBarbershop Premium"
        ]
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Configurações Gerais
    |--------------------------------------------------------------------------
    */
    'options' => [
        'simular_envio' => env('WHATSAPP_SIMULAR', true),
        'log_mensagens' => env('WHATSAPP_LOG', true),
        'timeout' => 30,
        'max_tentativas' => 3,
        'intervalo_entre_tentativas' => 5,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Números Autorizados (Modo de Teste)
    |--------------------------------------------------------------------------
    */
    'numeros_teste' => explode(',', env('WHATSAPP_NUMEROS_TESTE', '5511999999999,5511888888888')),
    
    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    */
    'rate_limit' => [
        'max_por_minuto' => 20,
        'max_por_hora' => 500,
        'max_por_dia' => 2000,
    ],
];
?>