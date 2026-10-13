<?php

declare(strict_types=1);

namespace App\Enums;

enum CourseLevel: string
{
    case Basico = 'basico';
    case Intermedio = 'intermedio';
    case Avanzado = 'avanzado';
    case Completo = 'completo';
    case Unico = 'unico';
}
