<?php

declare(strict_types=1);

namespace App\Services\RouteComposition\Exceptions;

use App\Models\DocumentType;
use App\Models\Specialization;
use RuntimeException;

/**
 * El generador no pudo ni siquiera proponer una ruta.
 *
 * Es distinto de que el validador la rechace: ahí hubo una propuesta y quedó
 * asentada en la bitácora. Aquí falta un insumo, y el mensaje está redactado para
 * que el asesor lea qué le falta, no para que abra un archivo de registro.
 */
final class CompositionException extends RuntimeException
{
    public static function anchorNotAllowed(Specialization $specialization): self
    {
        return new self(
            "«{$specialization->name}» no puede ser la especialización principal de una ruta enfocada: "
            .'su ficha no admite ese rol. Elige otra especialización como ancla; '
            .'esta seguirá apareciendo bajo su propio título en el documento anual.',
        );
    }

    public static function anchorWithoutCourses(Specialization $specialization): self
    {
        return new self(
            "«{$specialization->name}» no tiene cursos que se puedan recomendar hoy. "
            .'Revisa el catálogo antes de generar el documento.',
        );
    }

    /**
     * El embudo existe pero no tiene con qué armar una ruta.
     *
     * Solo pasa si alguien lo vació mientras el asesor tenía el formulario
     * abierto: el selector no ofrece embudos sin cursos.
     */
    public static function funnelWithoutCourses(string $name): self
    {
        return new self(
            "«{$name}» no tiene cursos. Pídele a quien armó la ruta que le agregue los suyos, "
            .'o elige otra para este lead.',
        );
    }

    public static function noComposerFor(DocumentType $documentType): self
    {
        return new self(
            "No hay compositor para el documento «{$documentType->name}» ({$documentType->slug}). "
            .'Un tipo de documento nuevo necesita su compositor registrado.',
        );
    }

    public static function membershipRequired(DocumentType $documentType): self
    {
        return new self(
            "«{$documentType->name}» exige los datos de la membresía: nombre completo, ocupación, "
            .'fecha de inicio y fecha de fin de vigencia.',
        );
    }
}
