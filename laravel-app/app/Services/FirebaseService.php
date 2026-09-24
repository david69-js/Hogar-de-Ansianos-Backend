<?php

namespace App\Services;

use App\Models\DeviceToken;
use App\Models\User;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

/**
 * Envoltura sobre el SDK de Firebase Cloud Messaging. La usan
 * CheckPendingMedications, CheckMedicationStock y DeviceTokenController::test()
 * para mandar pushes — nadie llama al SDK de Firebase directo fuera de aquí.
 * Se auto-limpia: si Firebase reporta un token como inválido/expirado
 * (dispositivo desinstaló la app, token vencido), lo borra de device_tokens
 * en el mismo envío, sin esperar a que alguien lo note.
 */
class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        $factory = (new Factory())->withServiceAccount(config('services.firebase.credentials'));
        $this->messaging = $factory->createMessaging();
    }

    /**
     * Envía una notificación push a todos los dispositivos registrados de un usuario.
     */
    public function sendToUser(User $user, string $title, string $body, array $data = []): array
    {
        // Igual que en CheckPendingMedications: al navegador se manda sin bloque
        // "notification" para que no se muestre dos veces (una el SDK y otra el
        // service worker). Si no se separara acá, la notificación de prueba
        // seguiría llegando duplicada aunque las de medicamentos ya no lo hagan.
        $porPlataforma = $user->deviceTokens()->get(['token', 'platform'])
            ->groupBy(fn ($t) => $t->platform === 'web' ? 'web' : 'nativo')
            ->map(fn ($grupo) => $grupo->pluck('token')->all());

        $resultados = [];
        foreach (['web' => true, 'nativo' => false] as $grupo => $soloDatos) {
            $tokens = $porPlataforma->get($grupo, []);
            if (!empty($tokens)) {
                $resultados[] = $this->sendToTokens($tokens, $title, $body, $data, $soloDatos);
            }
        }

        if (empty($resultados)) {
            return ['sent' => 0, 'failed' => 0, 'invalid_removed' => 0, 'message' => 'No hay dispositivos registrados.'];
        }

        return [
            'sent' => array_sum(array_column($resultados, 'sent')),
            'failed' => array_sum(array_column($resultados, 'failed')),
            'invalid_removed' => array_sum(array_column($resultados, 'invalid_removed')),
        ];
    }

    /**
     * Envía una notificación push a una lista de tokens de dispositivo.
     * Los tokens que Firebase reporte como inválidos/expirados se eliminan automáticamente.
     */
    /**
     * @param  bool  $soloDatos  Manda el mensaje SIN bloque "notification".
     *
     * Hace falta para los navegadores. Con bloque "notification", el SDK de
     * Firebase muestra la notificación por su cuenta Y ADEMÁS llama a nuestro
     * service worker, que la muestra otra vez: el mismo aviso aparecía dos veces
     * en el teléfono. En Chrome de escritorio no se notaba porque colapsa las
     * dos por el "tag"; en un iPhone con la aplicación instalada, no.
     *
     * Sin ese bloque, mostrar la notificación queda solo en manos del service
     * worker, que es quien le pone el ícono, el tag y los datos para saber a
     * dónde llevar al tocarla. El título y el cuerpo viajan dentro de data.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = [], bool $soloDatos = false): array
    {
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'invalid_removed' => 0, 'message' => 'No hay dispositivos registrados.'];
        }

        $payload = array_map('strval', $data);

        if ($soloDatos) {
            $payload['title'] = $title;
            $payload['body'] = $body;
            $message = CloudMessage::new()->withData($payload);
        } else {
            $message = CloudMessage::new()
                ->withNotification(Notification::create($title, $body))
                ->withData($payload);
        }

        $report = $this->messaging->sendMulticast($message, $tokens);

        $invalidTokens = $report->invalidTokens();
        if (!empty($invalidTokens)) {
            DeviceToken::whereIn('token', $invalidTokens)->delete();
        }

        return [
            'sent' => $report->successes()->count(),
            'failed' => $report->failures()->count(),
            'invalid_removed' => count($invalidTokens),
        ];
    }
}
