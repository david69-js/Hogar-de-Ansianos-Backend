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
        $tokens = $user->deviceTokens()->pluck('token')->all();

        return $this->sendToTokens($tokens, $title, $body, $data);
    }

    /**
     * Envía una notificación push a una lista de tokens de dispositivo.
     * Los tokens que Firebase reporte como inválidos/expirados se eliminan automáticamente.
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): array
    {
        if (empty($tokens)) {
            return ['sent' => 0, 'failed' => 0, 'invalid_removed' => 0, 'message' => 'No hay dispositivos registrados.'];
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData(array_map('strval', $data));

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
