<div class="space-y-4">
    <x-field name="name" label="Name" :value="$preview->name" required />

    <x-field name="slug" label="Kürzel" :value="$preview->slug"
             hint="Eindeutig je Projekt. Leer lassen, um es aus dem Namen abzuleiten." />

    <x-field name="hostname" label="Hostname" :value="$preview->hostname"
             hint="Genau eine Subdomain unterhalb von {{ config('previews.base_domain') }}, z. B. holzmann.{{ config('previews.base_domain') }}" />

    <x-select name="target_type" label="Zieltyp" required
              :value="$preview->target_type?->value" :options="$targetTypes" />

    <x-field name="target" label="Ziel" :value="$preview->target"
             hint="Nur Werte innerhalb der serverseitig freigegebenen Verzeichnisse bzw. Upstream-Hosts. Kunden sehen dieses Feld nie." />

    <x-select name="status" label="Status" required
              :value="$preview->status?->value" :options="$statuses" />

    <p class="rounded-lg bg-white/5 px-4 py-3 text-xs text-white/40">
        Hostname und Ziel sind erst ab dem Status „Entwurf“ hinaus verpflichtend. Das Ziel wird gegen
        die Allowlist in <span class="font-mono">config/previews.php</span> geprüft – Pfade außerhalb der
        freigegebenen Wurzelverzeichnisse und nicht freigegebene Upstream-Hosts werden abgelehnt.
    </p>
</div>
