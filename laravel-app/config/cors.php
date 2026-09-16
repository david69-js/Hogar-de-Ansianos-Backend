<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
     * Qué sitios web pueden llamar a esta API desde el navegador.
     *
     * Estaba en '*' (cualquiera). Se limita a los dominios propios; la lista se
     * puede cambiar sin tocar código con CORS_ALLOWED_ORIGINS, separando por comas.
     *
     * Ojo: esto NO afecta a la APK de Android ni a las pruebas con curl — CORS es
     * una regla que aplica el navegador, y las apps nativas no mandan cabecera
     * Origin. Solo afecta al sitio web.
     */
    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            'https://sorherminia.com,https://www.sorherminia.com,http://localhost:8081,http://localhost:19006'
        ))
    ))),

    /*
     * Para las vistas previas de Cloudflare Pages, que cambian de subdominio en
     * cada despliegue. Ejemplo de valor:
     *   CORS_ALLOWED_ORIGIN_PATTERNS=#^https://[a-z0-9-]+\.sorherminia-frontend\.pages\.dev$#
     */
    'allowed_origins_patterns' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CORS_ALLOWED_ORIGIN_PATTERNS', ''))
    ))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
