<?php

namespace App\Console\Commands;

use App\Models\DeviceToken;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Deja un solo token de notificaciones por persona y plataforma.
 *
 * Firebase cambia el token de un mismo aparato cada cierto tiempo (al reinstalar
 * la app, al volver a conceder el permiso, o cuando el navegador rehace la
 * suscripción). El token viejo sigue siendo válido un buen rato, así que el
 * mismo teléfono termina recibiendo CADA aviso dos veces.
 *
 * DeviceTokenController::store() ya descarta el token anterior al registrar uno
 * nuevo, pero solo cuando coinciden plataforma y user_agent. Si el user_agent
 * cambió (otra versión del sistema, Safari contra la app instalada), los dos
 * quedan vivos y hace falta esta limpieza.
 *
 * Sin argumentos solo informa. Con --limpiar borra, conservando el más reciente.
 */
class LimpiarTokensDuplicados extends Command
{
    protected $signature = 'app:limpiar-tokens-duplicados {--limpiar : Borrar de verdad (sin esto solo informa)}';

    protected $description = 'Encuentra personas con más de un token de notificaciones en la misma plataforma y deja solo el más reciente.';

    public function handle(): int
    {
        $limpiar = (bool) $this->option('limpiar');

        // Se agrupa por persona + plataforma + USER_AGENT, no solo por plataforma.
        //
        // Sin el user_agent, una Mac y un iPhone contaban como duplicados —los dos
        // son plataforma "web"— y el comando proponía borrar uno de los dos. Son
        // aparatos distintos: borrar el de la Mac la dejaba sin avisos. El
        // user_agent es lo único que los distingue.
        $grupos = DeviceToken::orderByDesc('last_used_at')->orderByDesc('id')->get()
            ->groupBy(fn ($t) => $t->user_id . '|' . $t->platform . '|' . $t->user_agent)
            ->filter(fn ($tokens) => $tokens->count() > 1);

        if ($grupos->isEmpty()) {
            $this->info('No hay tokens duplicados: cada persona tiene como máximo uno por plataforma.');
            return self::SUCCESS;
        }

        $nombres = User::whereIn('id', $grupos->map(fn ($t) => $t->first()->user_id))->get()
            ->mapWithKeys(fn ($u) => [$u->id => trim("{$u->first_name} {$u->last_name}") ?: $u->email]);

        // El aparato se identifica por su user_agent, así que se muestra recortado
        // para poder confirmar a ojo que son el mismo antes de borrar nada.

        $borrados = 0;

        foreach ($grupos as $clave => $tokens) {
            [$userId, $plataforma] = explode('|', $clave, 3);
            $this->line('');
            $this->line(sprintf(
                '%s (%s): %d tokens',
                $nombres[$userId] ?? "Usuario #{$userId}",
                $plataforma,
                $tokens->count()
            ));

            foreach ($tokens as $i => $token) {
                $accion = $i === 0 ? 'CONSERVA' : ($limpiar ? 'BORRA   ' : 'borraría');

                $this->line(sprintf(
                    '  %s  #%d  usado: %s  agente: %s',
                    $accion,
                    $token->id,
                    $token->last_used_at?->format('Y-m-d H:i') ?? 'nunca',
                    mb_strimwidth((string) $token->user_agent, 0, 45, '…') ?: '(sin registrar)'
                ));

                if ($i > 0 && $limpiar) {
                    $token->delete();
                    $borrados++;
                }
            }
        }

        $this->line('');

        if ($limpiar) {
            $this->info("Listo: se borraron {$borrados} tokens duplicados.");
        } else {
            $this->warn('Esto fue solo un informe. Para borrarlos, volvé a correrlo con --limpiar');
        }

        return self::SUCCESS;
    }
}
