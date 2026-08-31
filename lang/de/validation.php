<?php

/*
 * German messages for the validation rules this application actually uses.
 * Anything not listed here falls back to APP_FALLBACK_LOCALE (English).
 */
return [

    'accepted' => ':attribute muss akzeptiert werden.',
    'boolean' => ':attribute muss "ja" oder "nein" sein.',
    'confirmed' => 'Die Bestätigung für :attribute stimmt nicht überein.',
    'current_password' => 'Das angegebene Passwort ist nicht korrekt.',
    'email' => ':attribute muss eine gültige E-Mail-Adresse sein.',
    'enum' => 'Der gewählte Wert für :attribute ist ungültig.',
    'exists' => 'Der gewählte Wert für :attribute ist ungültig.',
    'in' => 'Der gewählte Wert für :attribute ist ungültig.',
    'integer' => ':attribute muss eine ganze Zahl sein.',
    'max' => [
        'array' => ':attribute darf höchstens :max Elemente haben.',
        'file' => ':attribute darf höchstens :max Kilobytes groß sein.',
        'numeric' => ':attribute darf höchstens :max sein.',
        'string' => ':attribute darf höchstens :max Zeichen lang sein.',
    ],
    'min' => [
        'array' => ':attribute muss mindestens :min Elemente haben.',
        'file' => ':attribute muss mindestens :min Kilobytes groß sein.',
        'numeric' => ':attribute muss mindestens :min sein.',
        'string' => ':attribute muss mindestens :min Zeichen lang sein.',
    ],
    'regex' => 'Das Format von :attribute ist ungültig.',
    'required' => ':attribute muss ausgefüllt werden.',
    'string' => ':attribute muss ein Text sein.',
    'unique' => ':attribute ist bereits vergeben.',
    'url' => ':attribute muss eine gültige URL sein.',

    'password' => [
        'letters' => ':attribute muss mindestens einen Buchstaben enthalten.',
        'mixed' => ':attribute muss Groß- und Kleinbuchstaben enthalten.',
        'numbers' => ':attribute muss mindestens eine Ziffer enthalten.',
        'symbols' => ':attribute muss mindestens ein Sonderzeichen enthalten.',
        'uncompromised' => ':attribute kommt in bekannten Datenlecks vor. Bitte wählen Sie ein anderes.',
    ],

    'custom' => [
        'password' => [
            'min' => 'Das Passwort muss mindestens :min Zeichen lang sein.',
        ],
    ],

    'attributes' => [
        'name' => 'Name',
        'email' => 'E-Mail-Adresse',
        'password' => 'Passwort',
        'password_confirmation' => 'Passwortbestätigung',
        'current_password' => 'Aktuelles Passwort',
        'slug' => 'Kürzel',
        'contact_email' => 'Kontakt-E-Mail',
        'is_active' => 'Status',
        'customer_id' => 'Kunde',
        'description' => 'Beschreibung',
        'status' => 'Status',
        'hostname' => 'Hostname',
        'target' => 'Ziel',
        'target_type' => 'Zieltyp',
        'token' => 'Token',
    ],

];
