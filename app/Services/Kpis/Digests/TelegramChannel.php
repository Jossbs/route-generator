<?php

declare(strict_types=1);

namespace App\Services\Kpis\Digests;

use Illuminate\Http\Client\Factory as Http;
use RuntimeException;

/**
 * El grupo de ventas en Telegram.
 *
 * Un POST a la API de bots, con el cliente HTTP que ya trae Laravel: no hace
 * falta un SDK para un solo método.
 */
final class TelegramChannel
{
    /** El límite de Telegram por mensaje. */
    public const MAX_LENGTH = 4096;

    public function __construct(private readonly Http $http) {}

    public function isConfigured(): bool
    {
        return filled(config('kpis.telegram.bot_token')) && filled(config('kpis.telegram.chat_id'));
    }

    /** @throws RuntimeException si no está configurado o Telegram lo rechaza. */
    public function send(string $html): void
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Falta configurar TELEGRAM_BOT_TOKEN y TELEGRAM_KPI_CHAT_ID.');
        }

        $respuesta = $this->http
            ->timeout(15)
            ->post('https://api.telegram.org/bot'.config('kpis.telegram.bot_token').'/sendMessage', [
                'chat_id' => config('kpis.telegram.chat_id'),
                'text' => mb_substr($html, 0, self::MAX_LENGTH),
                'parse_mode' => 'HTML',
                'disable_web_page_preview' => true,
            ]);

        if ($respuesta->failed() || $respuesta->json('ok') !== true) {
            throw new RuntimeException('Telegram respondió '.$respuesta->status().': '.($respuesta->json('description') ?? 'sin detalle'));
        }
    }
}
