<?php

declare(strict_types=1);

namespace App\Http\Requests\Routes;

/**
 * El paso intermedio: armar la propuesta sin guardar nada.
 *
 * «Nadie ve nada todavía. Tú revisas antes de que salga». Pide exactamente lo
 * mismo que la captura porque es la captura: lo que cambia es que en vez de
 * emitir, devuelve lo que el motor propone para que el asesor lo ajuste.
 */
class ProposeRouteRequest extends StoreLearningRouteRequest {}
