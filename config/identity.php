<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asesor de desarrollo
    |--------------------------------------------------------------------------
    |
    | El módulo no autentica: lo inserta un sistema que ya tiene su propio
    | acceso. Mientras ese sistema no exista, esta cuenta es la que firma lo que
    | se crea y se publica, para que la autoría no quede vacía.
    |
    | Ver App\Services\Identity\CurrentAdvisor.
    |
    */

    'development_advisor' => env('DEVELOPMENT_ADVISOR_EMAIL', 'mercadotecnia@dpsys.com.mx'),

    /*
    |--------------------------------------------------------------------------
    | WhatsApp del asesor de desarrollo
    |--------------------------------------------------------------------------
    |
    | El cierre del gancho enlaza al WhatsApp de quien preparó el documento. Ese
    | dato vive en el perfil del asesor; mientras el sistema anfitrión no lo
    | administre, esta variable siembra el de la cuenta de desarrollo.
    |
    | Vacío es una respuesta válida: sin número, el documento no pinta el botón.
    |
    */

    'development_advisor_whatsapp' => env('DEVELOPMENT_ADVISOR_WHATSAPP'),

];
