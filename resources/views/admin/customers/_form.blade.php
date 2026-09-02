<div class="space-y-4">
    <x-field name="name" label="Name" :value="$customer->name" required />

    <x-field name="slug" label="Kürzel" :value="$customer->slug"
             hint="Wird für Projekt- und Vorschau-Adressen verwendet. Leer lassen, um es aus dem Namen abzuleiten." />

    <x-field name="contact_email" label="Kontakt-E-Mail" type="email" :value="$customer->contact_email"
             hint="Optional. Wird nicht für die Anmeldung verwendet." />

    <label class="flex items-center gap-2 text-sm text-white/70">
        <input type="hidden" name="is_active" value="0">
        <input type="checkbox" name="is_active" value="1"
               @checked(old('is_active', $customer->is_active ?? true))
               class="rounded border-white/20 bg-white/5 text-accent focus:ring-accent">
        Kunde ist aktiv
    </label>
    <p class="text-xs sg-faint">
        Ein deaktivierter Kunde verliert sofort den Zugriff auf das Portal – alle Zugänge dieses Kunden
        werden bei der nächsten Anfrage abgemeldet.
    </p>
</div>
