<div class="space-y-4">
    <x-field name="name" label="Name" :value="$preview->name" required
             hint="So heißt die Vorschau im Kundenportal, z. B. „Stand KW12“." />

    <x-field name="slug" label="Kürzel" :value="$preview->slug"
             hint="Eindeutig je Projekt. Leer lassen, um es aus dem Namen abzuleiten." />

    {{-- Only the label is typed; the base domain is fixed and appended server
         side, so it cannot be mistyped in the first place. --}}
    <x-field name="hostname" label="Subdomain" :value="$preview->hostname"
             suffix=".{{ config('previews.base_domain') }}"
             placeholder="holzmann"
             hint="Genau eine Subdomain. Nur für statische Verzeichnisse nötig, erst zum Bereitstellen." />

    <x-select name="target_type" label="Zieltyp" required
              :value="$preview->target_type?->value" :options="$targetTypes" />

    <x-field name="target" label="Ziel" :value="$preview->target"
             hint="Nur Werte innerhalb der serverseitig freigegebenen Verzeichnisse bzw. Upstream-Hosts. Eine Upstream-URL (z. B. https://customer.example.com/zimmerei) öffnen Kunden direkt, samt Pfad; ein Verzeichnispfad bleibt intern." />

    <p class="rounded-md bg-brand-tint px-4 py-3 ring-1 ring-inset ring-line text-xs sg-muted">
        Der Status wird hier nicht gesetzt: Eine neue Vorschau ist ein Entwurf und wird über
        „Bereitstellen“ freigegeben. Das Ziel wird gegen die Allowlist in
        <span class="font-mono">config/previews.php</span> geprüft – Pfade außerhalb der
        freigegebenen Wurzelverzeichnisse und nicht freigegebene Upstream-Hosts werden abgelehnt.
    </p>
</div>
